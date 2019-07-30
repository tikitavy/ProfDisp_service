<?php
require_once 'autoload.php';

class BookingResult extends class_OutputDataTypes
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
        $objects['bookedService'] = 'BookedServiceType';

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
     * Информация о проведённой записи на приём
     * @var BookedServiceType $bookedService
     */
    public $bookedService = null;
}