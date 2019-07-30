<?php

class MyLib
{
    /**
     * Проверка на пустое занчение, включая проверку на NULL.
     *
     * При проверке у строк НЕ выполняется trim (НЕ удаляются пробелы по бокам строки).
     * Также включена проверка на пустой массив, если он передан в качестве аргумента.
     * При NULL или пустом значении - возвращается true.
     *
     * @param mixed $value Проверяемое значение.
     * @return bool
     */
    public static function trueIsEmpty($value)
    {
        if (
            (is_null($value)) or
            (is_array($value) && count($value) == 0) or
            (!is_array($value) && (string)$value == '') )
        {
            return true;
        }

        return false;
    }

}