<?php
require_once 'autoload.php';

class CancelBookingResult extends class_OutputDataTypes
{
    /**
     * Идентификатор запроса
     * @var UUID $requestId
     */
    public $requestId = '';

    /*
     * Результат отмены предварительной записи на медицинскую услугу.
     *     Элемент обязателен при отсутствии error.
     *     Может принимать следующие значения:
     *      - Успешно отменена;
     *      - Отмена невозможна, услуга уже оказана;
     *      - Отмена невозможна, услуга обязательна
     *  У нас это будут ответы от сервиса, т..е не эти в списке ответов.

     * @var string-255 $cancelBookingResult
     */
    public $cancelBookingResult = '';

    /*
     * Код ответа от сервиса (этого нет в ТЗ)
     * @var string-50 $cancelBookingResultCode
     */
    public $cancelBookingResultCode = '';

    /*
     * Объект, содержащий информацию об ошибках (в первую очередь валидации входящего запроса)
     * @var ErrorType $error
     */
    public $error = null;
}