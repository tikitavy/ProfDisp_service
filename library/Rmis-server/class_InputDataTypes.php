<?php

class class_InputDataTypes
{
    protected function initDifferences()
    {
        $differences = array();

        /*
        $differences['surname'] = new MyTypeValidate(MyTypes::rus100, false);
        $differences['birthDate'] = new MyTypeValidate(MyTypes::date, false);
        $differences['unifiedPolicyNumber'] = new MyTypeValidate(MyTypes::UPNType, true);
        $differences['snils'] = new MyTypeValidate(MyTypes::SNILSType, true);
        $differences['gender'] = new MyTypeValidate(MyTypes::GenderType, false);
        $differences['email'] = new MyTypeValidate(MyTypes::EmailAddressType, true);
        $differences['phone'] = new MyTypeValidate(MyTypes::PhoneNumberType, true);
        */

        return $differences;
    }

    protected function initObjects()
    {
        $objects = array();
        //$objects['MyObjectName'] = 'MyObjectClass';

        return $objects;
    }

    /**
     * @param object $stdClass
     * @param string $elementPpath
     * @throws Exception
     */
    public function __construct($stdClass = null, $elementPath = '')
    {
        $path = '';

        // Если передан класс - попробуем проверить наличие всех необходимых свойств
        if (!is_null($stdClass))
        {
            $differences = $this::initDifferences();

            foreach ($differences as $key => $value)
            {
                $path = $elementPath .' -> '. $key;

                // Проверка на обязательность
                MyTypeValidate::checkNullable($stdClass->$key, $path, $value->nullable);

                $stdClass->$key = trim($stdClass->$key);

                // Если не null, то проведём валидацию
                if ( !is_null($stdClass->$key) && $stdClass->$key != '')
                {
                    $value->validate($stdClass->$key, $path);

                    // Присваиваем свойство входящего объекта свойству этого класса
                    $this->$key = $stdClass->$key;
                }
            }

            // А тут обработаем вложенные объекты
            $objects = $this::initObjects();

            foreach ($objects as $key => $value)
            {
                $path = $elementPath .' -> '. $key;

                if (is_array($stdClass->$key))
                {
                    $this->$key = array();

                    // Индекс, чтобы в случае ошибки выдать, какой это по счёту элемент массива
                    $i = 1;

                    foreach ($stdClass->$key as $arrValue)
                    {
                        array_push($this->$key, new $value($arrValue, $path .' ['. $i++ .']'));
                    }
                }
                else
                {
                    $this->$key = new $value($stdClass->$key, $path);
                }
            }
        }

        $this->addValidate();
    }

    /**
     * Дополнительные проверки
     */
    protected function addValidate()
    {

    }
}
