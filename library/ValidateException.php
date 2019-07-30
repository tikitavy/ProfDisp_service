<?php

class ValidateException extends Exception
{
    /*
     * Имя поля (путь к элеменету и т.п.), в котором возникла ошибка
     * @var string
     */
    public $fieldName = null;

    /**
     * Некорректное значение (то, что получили на входе)
     * @var string
     */
    public $value = null;

    public function __construct($message = '', $code = 0, Throwable $previous = null, $fieldName = '', $value = '')
    {
        $this->fieldName = $fieldName;
        $this->value = $value;

        parent::__construct($message, $code, $previous);
    }

}

