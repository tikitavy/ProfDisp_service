<?php

class ErrorType
{
    /**
     * Код ошибки (может принимать значения из ErrorCodes::<код>)
     * @var string $errorCode
     */
    public $errorCode = '';

    /**
     * Текстовое описание ошибки
     * @var string $errorMessage
     */
    public $errorMessage = '';

    /**
     * Ошибки проверки корректности входящих данных
     * @var ValidationErrorType $validationError
     */
    public $validationError = null;
}
