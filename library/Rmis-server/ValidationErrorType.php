<?php

class ValidationErrorType
{
    public function __construct($message, $path, $value)
    {
        $this->message = $message;
        $this->path = $path;
        $this->value = $value;
    }


    /**
     * Текстовое описание ошибки
     * @var string $message
     */
    public $message = null;

    /**
     * Путь к элементу
     * @var string $path
     */
    public $path = '';

    /**
     * Некорректные значения элемента
     * @var string $value
     */
    public $value = '';
}
