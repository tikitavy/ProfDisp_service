<?php
require_once 'autoload.php';

class SlotType extends class_OutputDataTypes
{
    protected function initDifferences()
    {
        $differences = array();

        $differences['slotDateTime'] = new MyTypeValidate(MyTypes::dateTime, false);
        $differences['slotId'] = new MyTypeValidate(MyTypes::string255, false);

        return $differences;
    }


    /**
     * Дата и время, доступные для предварительной записи
     * @var dateTime $slotDateTime
     */
    public $slotDateTime = '';

    /**
     * Идентификатор временной ячейки
     * @var string-255 $slotId
     */
    public $slotId = '';
}
