<?php
require_once __DIR__ . '/library/Rmis-server/autoload.php';

set_include_path(
    '.'
    .PATH_SEPARATOR . './library'
    .PATH_SEPARATOR . get_include_path());

require_once('library/Zend/Loader.php');

Zend_Loader::loadClass('Zend_Soap_Server');
Zend_Loader::loadClass('Zend_Soap_AutoDiscover');
Zend_Loader::loadClass('Zend_Soap_Wsdl_Strategy_ArrayOfTypeComplex');


ini_set("soap.wsdl_cache_enabled", 0);





class ProfDisp
{
    /**
     * Отмена записи пациента на медицинскую услугу
     * @param UUID $requestId Идентификатор запроса Концентратора услуг ФЭР
     * @param string-50 $patientId Уникальный идентификатор пациента в РМИС
     * @param string-255 $bookingId Идентификатор записи на приём, в РМИС
     * @return CancelBookingResult
     * @throws Exception
     */
    public function CancelBookingRequest($requestId, $patientId, $bookingId)
    {
        $cancelBookingResult = new CancelBookingResult();

        // Проверим UUID, если ок - идём дальше..
        if (!self::checkUUID($requestId, $cancelBookingResult))
        {
            // Проверим ID пациента, если не ОК, то выкинет исключение
            self::checkPatientID($patientId);

            // Проверим (валидация) идентификатор записи на приём
            self::checkString($bookingId, 'bookingId', 255, false);

            try
            {
                // А это метод конкретной обработки, получение результата (запрос к БД и всё такое)
                $cancelBookingResult = MyStreamDBwork::CancelBooking($patientId, $bookingId);
            }
            catch (ValidateException $e)
            {
                // Тут поймали ошибку при проверке входных данных. Оформляем соответственно.
                self::block_exceptionValidation($cancelBookingResult, $e);
            }
            catch (Exception $e)
            {
                // Тут поймали ошибку не проверки данных, а общую (БД и т.п.), оформляем..
                self::block_exception($cancelBookingResult, $e);
            }

            // Мы в блоке, где UUID корректен, значит подставим его в блок ответа
            $cancelBookingResult->requestId = $requestId;
        }

        // Подготовим класс для вывода (удалим все NULL-свойства - в этом случае эти свойства не попадут в XML ответа,
        // что нам и нужно, иначе они будут в XML отдельными строками)
        MyStreamLib::prepareDataForReturnBySOAP($cancelBookingResult);

        return $cancelBookingResult;
    }

    /**
     * BookingRequest Предварительная запись пациента на медицинскую услугу из плана медицинского осмотра
     * @param UUID $requestId Идентификатор запроса Концентратора услуг ФЭР
     * @param string-50 $patientId Уникальный идентификатор пациента в РМИС
     * @param ChosenServiceType $service Информация о выбранном времени записи на приём
     * @return BookingResult
     * @throws Exception
     */
    public function BookingRequest($requestId, $patientId, $service)
    {
        $bookingResult = new BookingResult();

        // Проверим UUID, если ок - идём дальше..
        if (!self::checkUUID($requestId, $bookingResult))
        {
            // Проверим ID пациента, если не ОК, то выкинет исключение
            self::checkPatientID($patientId);

            try
            {
                // Заполняем (и автоматически пройдёт валидация) класс входных параметров
                $chosenServiceType = new ChosenServiceType($service, 'BookingRequest -> service');
                // А это метод конкретной обработки, получение результата (запрос к БД и всё такое)
                $bookingResult = MyStreamDBwork::Booking($patientId, $chosenServiceType);
            }
            catch (ValidateException $e)
            {
                // Тут поймали ошибку при проверке входных данных. Оформляем соответственно.
                self::block_exceptionValidation($bookingResult, $e);
            }
            catch (Exception $e)
            {
                // Тут поймали ошибку не проверки данных, а общую (БД и т.п.), оформляем..
                self::block_exception($bookingResult, $e);
            }

            // Мы в блоке, где UUID корректен, значит подставим его в блок ответа
            $bookingResult->requestId = $requestId;
        }


        // Подготовим класс для вывода (удалим все NULL-свойства - в этом случае эти свойства не попадут в XML ответа,
        // что нам и нужно, иначе они будут в XML отдельными строками)
        MyStreamLib::prepareDataForReturnBySOAP($bookingResult);

        return $bookingResult;
    }

    /**
     * GetAvailableSlotsRequest Получение перечня доступных временных слотов для записи в РМИС
     * @param UUID $requestId Идентификатор запроса Концентратора услуг ФЭР
     * @param string-50 $patientId Уникальный идентификатор пациента в РМИС
     * @param string-255 $clinicId Код учреждения (это только для прокси, тут не будет использоваться)
     * @param string-255 $serviceId Тип приёма (у нас он один)
     * @param string-255 $resourceId ID врача
     * @param date $startDate Дата начала периода
     * @param date $endDate Конец периода
     * @return GetAvailableSlotsResult
     * @throws Exception
     */
    public function GetAvailableSlotsRequest($requestId, $patientId, $clinicId, $serviceId, $resourceId, $startDate, $endDate)
    {
        $getAvailableSlotsResult = new GetAvailableSlotsResult();

        // Проверим UUID, если ок - идём дальше..
        if (!self::checkUUID($requestId, $getAvailableSlotsResult))
        {
            try
            {
                // Проверим ID пациента, если не ОК, то выкинет исключение
                self::checkPatientID($patientId);

                // Проверим корректность ввода $clinicId, $serviceId, $resourceId - одинаково string-255
                self::checkString($clinicId, 'clinicId', 255, false);
                self::checkString($serviceId, 'serviceId', 255, false);
                self::checkString($resourceId, 'resourceId', 255, false);

                // Проверим корректность ввода дат периода
                MyTypeValidate::checkNullable($startDate, 'startDate', false);
                MyTypeValidate::validateDate($startDate, 'startDate');
                MyTypeValidate::checkNullable($endDate, 'endDate', false);
                MyTypeValidate::validateDate($endDate, 'endDate');

                $getAvailableSlotsResult = MyStreamDBwork::GetAvailableSlots($patientId, $serviceId, $resourceId, $startDate, $endDate);
            }
            catch (ValidateException $e)
            {
                // Тут поймали ошибку при проверке входных данных. Оформляем соответственно.
                self::block_exceptionValidation($getAvailableSlotsResult, $e);
            }
            catch (Exception $e)
            {
                // Тут поймали ошибку не проверки данных, а общую (БД и т.п.), оформляем..
                self::block_exception($getAvailableSlotsResult, $e);
            }

            // Мы в блоке, где UUID корректен, значит подставим его в блок ответа
            $getAvailableSlotsResult->requestId = $requestId;
        }

        // Подготовим класс для вывода (удалим все NULL-свойства - в этом случае эти свойства не попадут в XML ответа,
        // что нам и нужно, иначе они будут в XML отдельными строками)
        MyStreamLib::prepareDataForReturnBySOAP($getAvailableSlotsResult);
        return $getAvailableSlotsResult;
    }


    /**
     * IdentifyPatientRequest Запрос идентификации пациента
     * @param UUID $requestId Идентификатор запроса UUID
     * @param PatientDataType $patientData Данные пациента
     * @return IdentifyPatientResult
     */
    public function IdentifyPatientRequest($requestId, $patientData)
    {
        $identifyPatientResult = new IdentifyPatientResult();

        // Проверим UUID, если ок - идём дальше..
        if (!self::checkUUID($requestId, $identifyPatientResult))
        {
            try
            {
                // Заполняем (и автоматически пройдёт валидация) класс входных параметров
                $patientDataType = new PatientDataType($patientData, 'IdentifyPatientRequest -> patientData');
                // А это метод конкретной обработки, получение результата (запрос к БД и всё такое)
                $identifyPatientResult = MyStreamDBwork::IdentifyPatient($patientDataType);
            }
            catch (ValidateException $e)
            {
                // Тут поймали ошибку при проверке входных данных. Оформляем соответственно.
                self::block_exceptionValidation($identifyPatientResult, $e);
            }
            catch (Exception $e)
            {
                // Тут поймали ошибку не проверки данных, а общую (БД и т.п.), оформляем..
                self::block_exception($identifyPatientResult, $e);
            }

            // Мы в блоке, где UUID корректен, значит подставим его в блок ответа
            $identifyPatientResult->requestId = $requestId;
        }

        // Подготовим класс для вывода (удалим все NULL-свойства - в этом случае эти свойства не попадут в XML ответа,
        // что нам и нужно, иначе они будут в XML отдельными строками)
        MyStreamLib::prepareDataForReturnBySOAP($identifyPatientResult);
        return $identifyPatientResult;
    }


    /**
     * QuestioningRequest Запрос анкетирования пациента
     * @param UUID $requestId Идентификатор запроса UUID
     * @param string-50 $patientId Уникальный идентификатор пациента в РМИС
     * @param FilledQuestionnaireType $filledQuestionnaire Заполненная анкета опроса
     * @return QuestioningResult
     */
    public function QuestioningRequest($requestId, $patientId, $filledQuestionnaire)
    {
        $questioningResult = new QuestioningResult();

        // Проверим UUID, если ок - идём дальше..
        if (!self::checkUUID($requestId, $questioningResult))
        {
            try
            {
                // Проверим ID пациента, если не ОК, то выкинет исключение
                self::checkPatientID($patientId);

                // Заполняем (и автоматически пройдёт валидация) класс входных параметров
                $filledQuestionnaireType = new FilledQuestionnaireType($filledQuestionnaire, 'Questioning -> filledQuestionnaire');

                // А это метод конкретной обработки, получение результата (запрос к БД и всё такое)
                $questioningResult = MyStreamDBwork::Questioning($filledQuestionnaireType, $patientId);
            }
            catch (ValidateException $e)
            {
                // Тут поймали ошибку при проверке входных данных. Оформляем соответственно.
                self::block_exceptionValidation($questioningResult, $e);
            }
            catch (Exception $e)
            {
                // Тут поймали ошибку не проверки данных, а общую (БД и т.п.), оформляем..
                self::block_exception($questioningResult, $e);
            }

            // Мы в блоке, где UUID корректен, значит подставим его в блок ответа
            $questioningResult->requestId = $requestId;
        }

        // Подготовим класс для вывода (удалим все NULL-свойства - в этом случае эти свойства не попадут в XML ответа,
        // что нам и нужно, иначе они будут в XML отдельными строками)
        MyStreamLib::prepareDataForReturnBySOAP($questioningResult);
        return $questioningResult;
    }


    private function checkPatientID($patientId)
    {
        //MyTypeValidate::checkNullable($patientId, 'patientId', false);
        //MyTypeValidate::validateString($patientId, 'patientId', 50);
        self::checkString($patientId, 'patientId', 50, false);
    }

    private function checkString($value, $fieldname, $sizeMaxLimit, $nullable)
    {
        MyTypeValidate::checkNullable($value, $fieldname, $nullable);
        MyTypeValidate::validateString($value, $fieldname, $sizeMaxLimit);
    }

    private function checkUUID($requestId, &$objectResult)
    {
        try
        {
            // Проверим UUID
            MyTypeValidate::checkNullable($requestId, 'requestId', false);
            MyTypeValidate::validateUUID($requestId, 'requestId');
        }
        catch (ValidateException $e)
        {
            // Если UUID не прошёл валидацию, оформляем ошибку для вывода SOAP особым (специальным для этого обозначенным в ТЗ) кодом и сообщением
            self::block_exceptionUUID($objectResult, $e);
            return true;
        }

        return false;
    }


    /**
     * @param $objectData Этот объект для SOAP-ответа (для формирования xml) должен содержать совойств error,
     * в нём будет формировать объект ошибки
     * @param ValidateException $e Исключение, откуда будем брать данные для объекта описания ошибки
     */
    private function block_exceptionUUID(&$objectData, &$e)
    {
        // Все свойства объекта-ответа (для формирования xml) скинем в NULL, чтобы они потом были удалены
        // по этому признаку, т.к. нам теперь в этом ответном xml нужно вывести только объект с описанием ошибки
        MyStreamLib::prepareDataForReturnBySOAP_setAllToNULL($objectData);

        $objectData->error = new ErrorType();
        $objectData->error->errorCode = 'KUFER3';
        $objectData->error->errorMessage = 'Неизвестный идентификатор запроса КУ ФЭР';
        $objectData->error->validationError = new ValidationErrorType($e->getMessage(), $e->fieldName, $e->value);
    }

    /**
     * @param $objectData Этот объект для SOAP-ответа (для формирования xml) должен содержать совойств error,
     * в нём будет формировать объект ошибки
     * @param ValidateException $e Исключение, откуда будем брать данные для объекта описания ошибки
     */
    private function block_exceptionValidation(&$objectData, &$e)
    {
        // Все свойства объекта-ответа (для формирования xml) скинем в NULL, чтобы они потом были удалены
        // по этому признаку, т.к. нам теперь в этом ответном xml нужно вывести только объект с описанием ошибки
        MyStreamLib::prepareDataForReturnBySOAP_setAllToNULL($objectData);

        $objectData->error = new ErrorType();
        $objectData->error->errorCode = 'KUFER5';
        $objectData->error->errorMessage = 'Входные данные некорректны';
        $objectData->error->validationError = new ValidationErrorType($e->getMessage(), $e->fieldName, $e->value);
    }

    /**
     * @param $objectData Этот объект для SOAP-ответа (для формирования xml) должен содержать совойств error,
     * в нём будет формировать объект ошибки
     * @param Exception $e Исключение, откуда будем брать данные для объекта описания ошибки
     */
    private function block_exception(&$objectData, &$e)
    {
        error_log('Error! '. $e . ' Code: '. $e->getCode(), 0);

        // Все свойства объекта-ответа (для формирования xml) скинем в NULL, чтобы они потом были удалены
        // по этому признаку, т.к. нам теперь в этом ответном xml нужно вывести только объект с описанием ошибки
        MyStreamLib::prepareDataForReturnBySOAP_setAllToNULL($objectData);

        $objectData->error = new ErrorType();
        $objectData->error->errorCode = 'KUFER2';
        $objectData->error->errorMessage = 'Внутренняя ошибка КУ ФЭР: '. $e->getMessage();
    }

}

if(isset($_GET['wsdl'])) {
    $autodiscover = new Zend_Soap_AutoDiscover('Zend_Soap_Wsdl_Strategy_ArrayOfTypeComplex');
    $autodiscover->setClass('ProfDisp');
    $autodiscover->handle();
}
else
{
    //$server = new Zend_Soap_Server('http://localhost/WSzf_1/server.php?wsdl');
    $server = new Zend_Soap_Server();
    $server->setUri('http://localhost/WSzf_1/server.php');
    $server->registerFaultException('Exception');
    $server->setClass('ProfDisp');
    $server->setObject(new ProfDisp());
    $server->handle();
}





