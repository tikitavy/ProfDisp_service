<?php
require_once 'autoload.php';

class GetAvailableSlotsResult extends class_OutputDataTypes
{
    /**
     * Проверка заполненных свойств класса.
     * @throws Exception
     */
    public function validate()
    {
        $differences = array();
        $differences['requestId'] = new MyTypeValidate(MyTypes::UUID, false);

        self::validateProcess($differences);
    }

    protected function initObjects()
    {
        $objects = array();
        $objects['slot'] = 'SlotType';

        return $objects;
    }


    /**
     * Идентификатор запроса
     * @var UUID $requestId
     */
    public $requestId = '';

    /*
     * Объект, содержащий информацию об ошибках (в первую очередь валидации входящего запроса)
     * @var ErrorType $error
     */
    public $error = null;

    /**
     * Доступные слоты для предварительной записи
     * @var SlotType[] $slot
     */
    public $slot = null;
}