<?php
require_once 'autoload.php';

class IdentifyPatientResult extends class_OutputDataTypes
{
    /**
     * Проверка заполненных свойств класса.
     * @throws Exception
     */
    public function validate()
    {
        $differences = array();

        $differences['requestId'] = new MyTypeValidate(MyTypes::UUID, false);
        $differences['patientId'] = new MyTypeValidate(MyTypes::string50, false);
        $differences['examinationStatus'] = new MyTypeValidate(MyTypes::string255, false);
        $differences['examinationStatusNotes'] = new MyTypeValidate(MyTypes::string255, true);
        $differences['isQuestionnaireFilled'] = new MyTypeValidate(MyTypes::boolean, true);

        self::validateProcess($differences);
    }


    /**
     * Идентификатор запроса
     * @var UUID $requestId
     */
    public $requestId = '';

    /**
     * Идентификатор пациента в МИС
     * @var string-50 $patientId
     */
    public $patientId = '';

    /**
     * Статус медицинского осмотра
     * Может принимать значения из ExaminationStatuses::<код>
     * @var string-255 $examinationStatus
     */
    public $examinationStatus = '';

    /**
     * Примечания к статусу медицинского осмотра
     * @var string-255 $examinationStatusNotes
     */
    public $examinationStatusNotes = null;

    /**
     * Признак наличия заполненной анкеты
     * @var boolean $isQuestionnaireFilled
     */
    public $isQuestionnaireFilled = null;

    /*
     * Объект, содержащий информацию об ошибках (в первую очередь валидации входящего запроса)
     * @var ErrorType $error
     */
    public $error = null;
}