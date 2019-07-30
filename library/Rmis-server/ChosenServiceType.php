<?php
require_once 'autoload.php';

class ChosenServiceType extends class_InputDataTypes
{
    protected function initDifferences()
    {
        $differences = array();

        $differences['clinicId'] = new MyTypeValidate(MyTypes::string255, false);
        $differences['serviceId'] = new MyTypeValidate(MyTypes::string255, false);
        $differences['resourceId'] = new MyTypeValidate(MyTypes::string255, false);
        $differences['slotId'] = new MyTypeValidate(MyTypes::string255, false);
        $differences['slotDateTime'] = new MyTypeValidate(MyTypes::dateTime, false);

        return $differences;
    }


    /**
     * Уникальный идентификатор подразделения МО в РМИС (не используется тут)
     * @var string-255 $clinicId
     */
    public $clinicId = '';

    /**
     * Уникальный идентификатор медицинской услуги в РМИС (не используется тут)
     * @var string-255 $serviceId
     */
    public $serviceId = '';

    /**
     * Уникальный идентификатор медицинского ресурса (ID врача)
     * @var string-255 $resourceId
     */
    public $resourceId = '';

    /**
     * Идентификатор временной ячейки для предварительной записи
     * @var string-255 $slotId
     */
    public $slotId = '';

    /**
     * Дата и время предварительной записи (по факту - не обязательно)
     * @var dateTime $slotDateTime
     */
    public $slotDateTime = '';
}
