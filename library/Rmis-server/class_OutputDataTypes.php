<?php

class class_OutputDataTypes extends stdClass
{
    /**
     * @param $differences Массив с полями, которые нужно проверять, пример: $differences['requestId'] = new MyTypeValidate(MyTypes::UUID, false);
     * @throws Exception
     */
    protected function validateProcess($differences)
    {
        foreach ($differences as $key => $value)
        {
            $key = trim($key);

            // Проверка на обязательность
            MyTypeValidate::checkNullable($key, $key, $value->nullable);

            // Если не null, то проведём валидацию
            if ( !is_null($key) && $key != '')
            {
                $value->validate($key, $key);
            }
        }
    }
}