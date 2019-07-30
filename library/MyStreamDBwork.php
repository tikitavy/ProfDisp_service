<?php
require_once __DIR__ . '/Rmis-server/autoload.php';
require_once __DIR__ . '/SMSO/library/database.php';
require_once __DIR__ . '/SMSO/smsoFuncList.php';



class MyStreamDBwork
{
    public static function CancelBooking($patientId, $bookingId)
    {
        $cancelBookingResult = new CancelBookingResult();

        $db = GetDB();

        try
        {
            // Заполним параметры для отписывания
            $aParams = new params();
            $aParams->queueId = $bookingId;
            $aParams->patientId = $patientId;

            // А тут отписываемся
            $result = dequeuePatient($aParams);

            // Проверим, вернулся ли правильный ответ и вернулось ли сообщение об ошибке
            if ($result['message'] === null)
            {
                throw new WrongDataException('Ошибка при записи пациента - не получены ожидаемые данные.');
            }

            // Вырежем код ошибки, если 100 - значит ОК. Если нет - значит проблема
            $resultCode = substr(trim($result['message']), 0, 3);

            $cancelBookingResult->cancelBookingResult = $result['message'];
            $cancelBookingResult->cancelBookingResultCode = $resultCode;
        }
        catch (WrongDataException $e)
        {
            $db->Rollback();
            error_log("Error, DBWrongDataError. Message: ". $e->getMessage(), 0);
            throw new Exception('['. MyExceptionCodes::DBWrongDataError .'] Incorrect data from DB: '. $e->getMessage(), MyExceptionCodes::DBWrongDataError);
        }
        catch (Exception $e)
        {
            $db->Rollback();
            error_log("Error. Message: ". $e->getMessage(), 0);
            throw new Exception('['. MyExceptionCodes::DBGeneralError .'] Could not successfully run transaction-query from DB: '. $e->getMessage(), MyExceptionCodes::DBGeneralError);
        }

        return $cancelBookingResult;
    }

    /**
     * Запись на приём
     * @param $patientId Идентификатор пациента
     * @param ChosenServiceType $service Данные для записи пациента
     * @return BookingResult
     * @throws Exception
     */
    public static function Booking($patientId, ChosenServiceType $service)
    {
        $bookingResult = new BookingResult();

        $db = GetDB();

        try
        {
            // Проверим наличие пациента в БД
            if (MyLib::trueIsEmpty($db->GetById('Client', $patientId)))
            {
                throw new WrongDataException('Wrong patient ID (patient not found. ID: '. $patientId);
            }

            // Проверим код врача в БД МО, причём смотрим только того, кто имеет признак availableForExternalDVN = 1
            //if (MyLib::trueIsEmpty($db->GetById('Person', $resourceId)))
            if (MyLib::trueIsEmpty($db->Get('Person', 'id', "id = $service->resourceId AND availableForExternalDVN = 1")) )
            {
                throw new WrongDataException('Wrong resourceID (doctor not found, need flag availableForExternalDVN. ID: '. $service->resourceId);
            }


            // Заполним параметры для записи
            $aParams = new params();
            $aParams->patientId = $patientId;
            $aParams->personId = $service->resourceId;
            $aParams->note = 'Запись с сервиса, профосмотры';

            // Разберём строку вида <дата>|<время> на две переменные
            list($aParams->date, $aParams->time) = explode('|', $service->slotId);

            if ($aParams->date === null || $aParams->time === null)
            {
                throw new WrongDataException('Wrong date time for enque. Recieved: '. $service->slotId);
            }

            MyTypeValidate::validateDate($aParams->date);

            // Записываемся!
            $result = enqueuePatient($aParams);
            //600997

            // Проверим, вернулся ли правильный ответ и вернулось ли сообщение об ошибке
            if ($result['message'] === null)
            {
                throw new WrongDataException('Ошибка при записи пациента - не получены ожидаемые данные.');
            }

            // Вырежем код ошибки, если 100 - значит ОК. Если нет - значит проблема
            $resultCode = substr(trim($result['message']), 0, 3);

            if ($resultCode != '100')
            {
                // Тут в случае пророблемы
                $bookingResult->bookedService = new BookedServiceType();
                $resultCode == '307' ? $bookingResult->bookedService->bookingStatus = 'PLANNED' : $bookingResult->bookedService->bookingStatus = 'UNPLANNED';
                $bookingResult->bookedService->emptyBookingReason = $result['message'];
            }
            else
            {
                // Тут всё ок, заполняем ответ
                $bookingResult->bookedService = new BookedServiceType();
                $bookingResult->bookedService->bookingStatus = 'PLANNED';
                $bookingResult->bookedService->bookingData = new BookingDataType();
                $bookingResult->bookedService->bookingData->bookingId = trim($result['queueId']);
                $bookingResult->bookedService->bookingData->bookingDateTime = $aParams->date .' '. $aParams->time;
                $bookingResult->bookedService->bookingData->resource = new BookingResourceType();
                $bookingResult->bookedService->bookingData->resource->resourceId = $aParams->personId;
            }
        }
        catch (WrongDataException $e)
        {
            $db->Rollback();
            error_log("Error, DBWrongDataError. Message: ". $e->getMessage(), 0);
            throw new Exception('['. MyExceptionCodes::DBWrongDataError .'] Incorrect data from DB: '. $e->getMessage(), MyExceptionCodes::DBWrongDataError);
        }
        catch (Exception $e)
        {
            $db->Rollback();
            error_log("Error. Message: ". $e->getMessage(), 0);
            throw new Exception('['. MyExceptionCodes::DBGeneralError .'] Could not successfully run transaction-query from DB: '. $e->getMessage(), MyExceptionCodes::DBGeneralError);
        }

        return $bookingResult;
    }



    /**
     * @param $patientId Идентификатор пациента
     * @param $serviceId Тип приёма (у нас он один)
     * @param $resourceId ID врача
     * @param $startDate
     * @param $endDate
     * @return GetAvailableSlotsResult
     * @throws Exception
     */
    public static function GetAvailableSlots($patientId, $serviceId, $resourceId, $startDate, $endDate)
    {
        $getAvailableSlotsResult = new GetAvailableSlotsResult();

        $db = GetDB();

        try
        {
            // Проверим наличие пациента в БД
            if (MyLib::trueIsEmpty($db->GetById('Client', $patientId)))
            {
                throw new WrongDataException('Wrong patient ID (patient not found. ID: '. $patientId);
            }

            // Проверим код врача в БД МО, причём смотрим только того, кто имеет признак availableForExternalDVN = 1
            //if (MyLib::trueIsEmpty($db->GetById('Person', $resourceId)))
            if (MyLib::trueIsEmpty($db->Get('Person', 'id', "id = $resourceId AND availableForExternalDVN = 1")) )
            {
                throw new WrongDataException('Wrong resourceID (doctor not found, need flag availableForExternalDVN. ID: '. $resourceId);
            }

            // Получаем список дат, на которые у врача есть свободные талоны
            $aParams = new params();
            $aParams->orgStructureId = false;
            $aParams->recursive = true;
            $aParams->specialityNotation = false;
            $aParams->speciality = false;
            $aParams->personId = $resourceId;
            $aParams->begDate = $startDate;
            $aParams->endDate = $endDate;
            //$aParams->date = '2019-07-16';

            $result = getTicketsAvailability($aParams);

            $arrDates = array();

            foreach ($result['list'] as $value)
            {
                self::checkVariableIsSet($value['free'], 'Неверные данные, полученные от талона (нет ожидаемых параметров: free)');
                self::checkVariableIsSet($value['date'], 'Неверные данные, полученные от талона (нет ожидаемых параметров: date)');

                if ($value['free'] > 0)
                {
                    $arrDates[] = $value['date'];
                }
            }

            // Идём по этому списку дат и получаем талоны (время), заполняем объект для возврата сервисом
            $aParams = new params();
            $aParams->personId = $resourceId;

            foreach ($arrDates as $date)
            {
                $aParams->date = $date;

                $result = getWorkTimeAndStatus($aParams);
                $result = $result['amb']['tickets'];

                if ($result != null)
                {
                    foreach ($result as $tickets)
                    {
                        self::checkVariableIsSet($tickets['free'], 'Неверные данные, полученные от талона (нет ожидаемых параметров: free)');
                        self::checkVariableIsSet($tickets['time'], 'Неверные данные, полученные от талона (нет ожидаемых параметров: time)');

                        if ($tickets['free'] === true)
                        {
                            $o = new SlotType();
                            $o->slotId = $date .'|'. $tickets['time'];
                            $o->slotDateTime = $date .' '. $tickets['time'];

                            $getAvailableSlotsResult->slot[] = $o;
                        }
                    }
                }
            }
        }
        catch (WrongDataException $e)
        {
            $db->Rollback();
            error_log("ERROR execute SQL, DBWrongDataError. Message: ". $e->getMessage(), 0);
            throw new Exception('['. MyExceptionCodes::DBWrongDataError .'] Incorrect data from DB: '. $e->getMessage(), MyExceptionCodes::DBWrongDataError);
        }
        catch (Exception $e)
        {
            $db->Rollback();
            error_log("ERROR execute SQL, transaction. Message: ". $e->getMessage(), 0);
            throw new Exception('['. MyExceptionCodes::DBGeneralError .'] Could not successfully run transaction-query from DB: '. $e->getMessage(), MyExceptionCodes::DBGeneralError);
        }

        return $getAvailableSlotsResult;
    }

    /**
     * @param $var
     * @param $message
     * @throws WrongDataException
     */
    public static function checkVariableIsSet($var, $message)
    {
        if (!isset($var))
        {
            throw new WrongDataException($message);
        }
    }

    /**
     * @param FilledQuestionnaireType $filledQuestionnaire Объект с входными данными (анкета)
     * @param $patientId Идентификатор пациента
     * @return QuestioningResult
     * @throws Exception
     */
    public static function Questioning(FilledQuestionnaireType $filledQuestionnaire, $patientId)
    {
        MyTypeValidate::checkClass($filledQuestionnaire, 'FilledQuestionnaireType');

        // На у теперь идём в БД выкладывать данные
        try
        {
            $db = GetDB();

            // Проверим наличие пациента в БД
            if (MyLib::trueIsEmpty($db->GetById('Client', $patientId)))
            {
                throw new WrongDataException('Wrong patient ID (patient not found. ID: '. $patientId);
            }

            // Это тип заявления $filledQuestionnaire->questionnaireType;
            // 'qdisp_to75' - до 75 лет
            // 'qdisp_from75' - 75 лет и старше

            // начнём транзакцию
            $db->Transaction();

// [   ] ==========================================================

            // Получим из БД нужные (при создании записей анкеты в таблицах) идентификаторы
            $vEventTypeId  = $db->Translate('EventType',    'code', 'qdisp', 'id');
            $vActionTypeId = $db->Translate('ActionType',   'code', $filledQuestionnaire->questionnaireType, 'id');

            // если хоть один из них 0 - то откат
            if ($vEventTypeId == 0 || $vActionTypeId == 0)
            {
                throw new WrongDataException('Wrong DB settings (metod "Questioning" request correct id on EventType|ActionType|Organisation tables)');
            }

            $vNow = date('Y-m-d H:i:s');

            $vEventRecord  = array('createDatetime' => $vNow,
                'createPerson_id'=> NULL,
                'eventType_id' => $vEventTypeId,
                'org_id'       => NULL,
                'client_id'    => $patientId,
                'setDate'      => $vNow,
                'isPrimary'    => 1
            );

            // Вставляем запись в первую таблицу. Если вернётся FALSE - то считаем, что некая ошибка, откат
            $vEventId = $db->Insert('Event', $vEventRecord);

            if ($vEventId === FALSE)
            {
                throw new Exception('metod "Questioning". Problem witch insert data to Event table.');
            }

            $vActionRecord = array('createDatetime' => $vNow,
                'createPerson_id'=> NULL,
                'actionType_id' => $vActionTypeId,
                'event_id'      => $vEventId,
                'directionDate' => $vNow,
                'status'        => 1,
                'person_id'     => NULL,
                'setPerson_id'  => NULL,
                'note'          => 'Writed by SOAPService',
                'office'        => 'Unknown office (writed by SOAPService)',
            );

            // Вставляем запись во вторую таблицу, аналогично смотрим полученный ID вставленной записи
            $vActionId = $db->Insert('Action', $vActionRecord);

            if ($vActionId === FALSE)
            {
                throw new Exception('metod "Questioning". Problem witch insert data to Action table.');
            }


            // Тут всё, что надо вставили, теперь перебираем массив вопрос-ответ и вставляем уже их
            foreach ($filledQuestionnaire->questionnaireAnswer as $v)
            {
                // Тут дополнение: при коде QDISP_TO75_Q5 возможно получение в одном поле двух ответов, с разделителем "|".
                // Вторую часть (если она есть) надо записать отдельно с кодом QDISP_TO75_Q5_11

                if (strtoupper($v->questionCode) == 'QDISP_TO75_Q5')
                {
                    // Поищем два ответа в одном
                    list($var1, $var2) = explode('|', $v->answerValue);

                    if ($var2 != null)
                    {
                        $ActionPropID = self::QuestioningInsertToActionProperty($db, $vActionId, $filledQuestionnaire->questionnaireType, 'QDISP_TO75_Q5_11');
                        self::QuestioningInsertToActionProperty_String($db, $ActionPropID, $var2, 'QDISP_TO75_Q5_11');

                        $v->answerValue = $var1;

                        unset($var1);
                        unset($var2);
                    }
                }

                // Вставляем запись о вопрос-ответ в первую таблицу, получаем ID вставленной записи
                $ActionPropID = self::QuestioningInsertToActionProperty($db, $vActionId, $filledQuestionnaire->questionnaireType, $v->questionCode);

                // Вставляем запись о вопрос-ответ во вторую таблицу
                self::QuestioningInsertToActionProperty_String($db, $ActionPropID, $v->answerValue, $v->questionCode);

            }

// [end] ==========================================================

            $db->Commit();
        }
        catch (WrongDataException $e)
        {
            $db->Rollback();
            error_log("ERROR execute SQL, DBWrongDataError. Message: ". $e->getMessage(), 0);
            throw new Exception('['. MyExceptionCodes::DBWrongDataError .'] Incorrect data from DB: '. $e->getMessage(), MyExceptionCodes::DBWrongDataError);
        }
        catch (Exception $e)
        {
            $db->Rollback();
            error_log("ERROR execute SQL, transaction. Message: ". $e->getMessage(), 0);
            throw new Exception('['. MyExceptionCodes::DBGeneralError .'] Could not successfully run transaction-query from DB: '. $e->getMessage(), MyExceptionCodes::DBGeneralError);
        }

        $questioningResult = new QuestioningResult();

        return $questioningResult;
    }

    /**
     * Вставляем запись о вопрос-ответ во вторую таблицу
     * @param TMyDB $db Объект работы с БД
     * @param $ActionPropID Для SQL
     * @param $answerValue Для SQL
     * @param $questionCode Для сообщения об ошибке (в исключении)
     * @throws Exception
     */
    private function QuestioningInsertToActionProperty_String(TMyDB &$db, $ActionPropID, $answerValue, $questionCode)
    {
        $sql = "INSERT INTO ActionProperty_String SET id = $ActionPropID, value = '$answerValue'";

        $resultInsert = $db->InsertBySQL($sql);

        if ($resultInsert === FALSE)
        {
            throw new Exception('metod "Questioning". Problem witch insert data to ActionProperty_String table (answer). questionCode: '. $questionCode
                .', error: '. $db->ErrorMsg());
        }
    }

    /**
     * Вставляем запись о вопрос-ответ в первую таблицу, возвращаем ID вставленной записи
     * @param TMyDB $db Объект работы с БД
     * @param $vActionId Для SQL
     * @param $questionnaireType Для SQL
     * @param $questionCode Для SQL
     * @return int
     * @throws Exception
     */
    private function QuestioningInsertToActionProperty(TMyDB &$db, $vActionId, $questionnaireType, $questionCode)
    {
        $sql = "INSERT INTO ActionProperty (action_id, type_id)
                    (SELECT $vActionId, ActionPropertyType.id
                     FROM ActionPropertyType
                     JOIN ActionType
                        ON ActionType.id = ActionPropertyType.actionType_id AND ActionType.code = '$questionnaireType'
                     WHERE
                       ActionPropertyType.descr = '$questionCode');
                ";

        // Результат отработки INSERT, 0/FALSE - не прошло
        $resultInsert = $db->InsertBySQL($sql);

        if ($resultInsert === FALSE)
        {
            throw new Exception('metod "Questioning". Problem witch insert data to ActionProperty table (question). questionCode: '. $questionCode
                .', error: '. $db->ErrorMsg());
        }

        return $resultInsert;
    }

    /**
     * @param $patientData PatientDataType
     * @return IdentifyPatientResult
     * @throws Exception
     */
    public static function IdentifyPatient(PatientDataType $patientData)
    {
        MyTypeValidate::checkClass($patientData, 'PatientDataType');

        // Меняем поле пола на то, что принимает БД
        $sex = strtolower(trim($patientData->gender)) == 'male' ? 1 : 0;


        $sqlPart = '';
        $result = null;

        // На у теперь идём в БД за данными
        try
        {
            $db = GetDB();

            $patientData->snils = trim($patientData->snils);
            if (!MyLib::trueIsEmpty($patientData->snils))
            {
                $sqlPart .= " AND Client.SNILS='$patientData->snils' \n";
            }

            $patientData->patronymic = trim($patientData->patronymic);
            if (!MyLib::trueIsEmpty($patientData->patronymic))
            {
                $sqlPart .= " AND patrName='$patientData->patronymic' \n";
            }

            $patientData->unifiedPolicyNumber = trim($patientData->unifiedPolicyNumber);
            if (!MyLib::trueIsEmpty($patientData->unifiedPolicyNumber))
            {
                $sqlPart .= " AND concat(ClientPolicy.serial, ClientPolicy.number)='$patientData->unifiedPolicyNumber' \n";
            }

            $sql = "
                SELECT Client.id AS id
                     , concat(ClientPolicy.serial, ClientPolicy.number) AS policy
                     , Client.SNILS as snils
                FROM
                  Client
                LEFT JOIN ClientPolicy
                ON ClientPolicy.id = getClientPolicyId(Client.id, 1)
                WHERE
                  lastName='$patientData->surname'
                  AND firstName='$patientData->name'
                  AND birthDate='$patientData->birthDate'
                  AND sex='$sex' 
                  $sqlPart;
            ";

            $result= $db->Query($sql);
        }
        catch (Exception $e)
        {
            error_log("ERROR SQL: ". $e->getMessage(), 0);
            error_log("ERROR SQL: ". $sql, 0);
            throw new Exception('['. MyExceptionCodes::DBGeneralError .'] Could not successfully run query from DB: '. mysql_error(), MyExceptionCodes::DBGeneralError);
        }


        if (!$result)
        {
            throw new Exception('['. MyExceptionCodes::DBGeneralError .'] Could not successfully run query from DB: '. mysql_error(), MyExceptionCodes::DBGeneralError);
        }

        if (mysql_num_rows($result) > 1)
        {
            throw new Exception('['. MyExceptionCodes::DBResultTooMuchRows .'] Too much rows in DB result.', MyExceptionCodes::DBResultTooMuchRows);
        }


        $idpr = new IdentifyPatientResult();

        if (mysql_num_rows($result) == 0)
        {
            $idpr->examinationStatus = 'Пациент не найден';
            $idpr->patientId = null;
            //error_log ('Никого не найдено.', 0);
        }
        else
        {
            $row = mysql_fetch_assoc($result);
            //error_log("SQL: ". $sql, 0);
            //error_log ($row['snils'] .' '. $row['id'] .' '. $row['policy'], 0);

            $idpr->patientId = $row['id'];
            $idpr->examinationStatus = 'заглушка';
            $idpr->examinationStatusNotes = 'заглушка';
            $idpr->isQuestionnaireFilled = 'заглушка';
        }

        $result != null ? mysql_free_result($result) : null;

        return $idpr;
    }
}

class params
{
    public $orgStructureId = '';
    public $recursive = '';
    public $specialityNotation = '';
    public $speciality = '';
    public $personId = '';
    public $begDate = '';
    public $endDate = '';
    public $date = '';
    public $time = '';
    public $note = '';
    public $patientId = '';
    public $queueId = '';
}
