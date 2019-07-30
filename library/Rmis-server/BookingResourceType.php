<?php
require_once 'autoload.php';

class BookingResourceType extends class_OutputDataTypes
{
    /**
     * Наименование медицинского ресурса
     * @var string-500 $resourceName
     */
    public $resourceName = '';

    /**
     * Уникальный идентификатор медицинского ресурса.
     * @var string-255 $resourceId
     */
    public $resourceId = null;

    /**
     * Данные медицинского работника, оказывающего услугу
     * @var EmployeeType $employee
     */
    public $employee = null;

    /**
     * Наименование кабинета, в котором будет оказана услуга
     *    Пример: Кабинет №45
     * @var string-50 $room
     */
    public $room = null;
}