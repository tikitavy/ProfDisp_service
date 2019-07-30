<?php
require_once 'autoload.php';

class BookingDataType extends class_OutputDataTypes
{
    /**
     * Данные подразделения медицинской организации
     * @var ClinicType $clinic
     */
    public $clinic = '';

    /**
     * Данные ресурса, на который записан гражданин
     *     Элемент обязателен для услуги со статусом "PLANNED".
     * @var BookingResourceType $resource
     */
    public $resource = null;

    /**
     * Уникальный идентификатор предварительной записи на медицинскую услугу в РМИС
     * @var string-255 $bookingId
     */
    public $bookingId = null;

    /**
     * Дата и время, на которое произведена предварительная запись
     * @var dateTime $bookingDateTime
     */
    public $bookingDateTime = '';

}