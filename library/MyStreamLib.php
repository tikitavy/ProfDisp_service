<?php

class MyStreamLib
{
    /**
     * Удаление всех NULL-свойств у полученного объекта (включая вложенные)
     * Нужно для вывода в ответ SOAP, иначе все NULL-свойства попадают туда (в xml) пустыми полями, что не надо.
     * @param $objectData Объект, в котором нужно убрать null-свойства
     */
    public static function prepareDataForReturnBySOAP(&$objectData)
    {
        // Сколько свойств у переданного объекта? Если больше 0, значит это класс или массив..
        if (count((array)$objectData) > 1)
        {
            // Надо перебрать все свойства и опять проверить на классы.
            foreach ($objectData as $key => $value)
            {
                if ($value === null)
                {
                    unset($objectData->$key);
                }
                else
                {
                    // Рекурсия, т.к. вдруг свойства класса - другой класс тоже со свойствами?
                    if (!is_null($objectData->$key))
                    {
                        self::prepareDataForReturnBySOAP($objectData->$key);
                    }
                }
            }
        }
        else
        {
            if (is_null($objectData))
            {
                unset($objectData);
            }
        }
    }


    /**
     * @param $objectData Объект, в котором все свойства "сбрасываем" на null
     */
    public static function prepareDataForReturnBySOAP_setAllToNULL(&$objectData)
    {
        foreach ($objectData as $key => $value)
        {
            $objectData->$key = null;
        }
    }


}