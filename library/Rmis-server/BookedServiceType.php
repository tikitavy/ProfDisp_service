<?php
require_once 'autoload.php';

class BookedServiceType extends class_OutputDataTypes
{
    protected function initObjects()
    {
        $objects = array();
        $objects['service'] = 'MedicalServiceType';
        $objects['bookingData'] = 'BookingDataType';

        return $objects;
    }


    /**
     * Данные медицинской услуги
     * @var MedicalServiceType $service
     */
    public $service = null;

    /**
     * Статус предварительной записи
     * Может принимать следующие значения:
     *    PLANNED - Запланирована;
     *    UNPLANNED - Не запланирована
     * @var string-255 $bookingStatus
     */
    public $bookingStatus = '';

    /**
     * Причина отсутствия данных предварительной записи
     *    Элемент должен обязательно отсутствовать, если присутствует элемент bookingData.
     *    Элемент должен обязательно присутствовать, если отсутствует элемент bookingData.
     *    Может принимать следующие значения:
     *      - Медицинская организация не найдена;
     *      - Услуга не найдена;
     *      - Ресурс не найден;
     *      - Расписание не найдено;
     *      - Нет доступного времени для записи.
     * @var string-255 $emptyBookingReason
     */
    public $emptyBookingReason = null;

    /**
     * Блок данных предварительной записи
     * @var BookingDataType $bookingData
     */
    public $bookingData = null;
}