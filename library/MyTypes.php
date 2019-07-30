<?php

class MyTypes
{
    const UUID = 'UUID';
    const rus100 = 'rus-100';
    const date = 'date';
    const dateTime = 'dateTime';
    const UPNType = 'UPNType';
    const SNILSType = 'SNILSType';
    const GenderType = 'GenderType';
    const EmailAddressType = 'EmailAddressType';
    const PhoneNumberType = 'PhoneNumberType';
    const string = 'string';
    const string50 = 'string-50';
    const string255 = 'string-255';
    const string500 = 'string-500';
    const boolean = 'boolean';



    /**
     * @return string[]
     * @throws ReflectionException if the class does not exist.
     */
    public static function getTypes()
    {
        $reflectionClass = new ReflectionClass(__CLASS__);
        return $reflectionClass->getConstants();
    }

    /**
     * Проверка на корректность переданного названия типа данных
     * @param $type
     * @throws ReflectionException
     */
    public static function checkType($type)
    {
        if (!in_array($type, MyTypes::getTypes()))
        {
            throw new Exception('Code: ['. MyExceptionCodes::WrongType .'] MyTypes->checkType: Wrong MyType: '. $type, MyExceptionCodes::WrongType);
        }
    }
}