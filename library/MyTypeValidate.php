<?php

class MyTypeValidate
{
    public $type;
    public $nullable;

    /**
     * MyTypeValidate constructor.
     * @param string $type
     * @param boolean $nullable
     * @throws Exception
     */
    public function __construct($type, $nullable = true)
    {
        MyTypes::checkType($type);
        self::checkBoolean($nullable);

        $this->type = $type;
        $this->nullable = $nullable;
    }

    public function validate($value, $fieldName)
    {
        switch ($this->type)
        {
            case MyTypes::rus100:
                self::validateRus100($value, $fieldName);
                break;
            case MyTypes::date:
                self::validateDate($value, $fieldName);
                break;
            case MyTypes::UPNType:
                self::validateUPNType($value, $fieldName);
                break;
            case MyTypes::SNILSType:
                self::validateSNILSType($value, $fieldName);
                break;
            case MyTypes::GenderType:
                self::validateGenderType($value, $fieldName);
                break;
            case MyTypes::EmailAddressType:
                self::validateEmailAddressType($value, $fieldName);
                break;
            case MyTypes::PhoneNumberType:
                self::validatePhoneNumberType($value, $fieldName);
                break;
            case MyTypes::UUID:
                self::validateUUID($value, $fieldName);
                break;
            case MyTypes::string:
                self::validateString($value, $fieldName);
                break;
            case MyTypes::string50:
                self::validateString($value, $fieldName, 50);
                break;
            case MyTypes::string255:
                self::validateString($value, $fieldName, 255);
                break;
            case MyTypes::string500:
                self::validateString($value, $fieldName, 500);
                break;
            case MyTypes::boolean:
                self::validateBoolean($value, $fieldName);
                break;
        }
    }


    /**
     * Проверка типа boolean
     * @param $value Значение для проверки
     * @param $fieldName Имя поля, значение которого проверяется (подставим в текст исключения в случае проблем, чтобы понятно было, что за поле)
     * @throws Exception
     */
    public static function validateBoolean($value, $fieldName)
    {
        $list = array( 'true', 'false', '1', '0' );
        $value = strtolower( trim($value) );

        $description = 'Допускаются: '. implode( ", ", $list );

        if (!in_array($value, $list))
        {
            throw new ValidateException('['. MyExceptionCodes::WrongArgument .'] Wrong Argument on field: '. $fieldName .'. Descr: '. $description,
                MyExceptionCodes::WrongArgument, null, $fieldName, $value);
        }
    }

    /**
     * Проверка типа string-<тут кол-во сивмолов лимит>
     * @param $value Значение для проверки
     * @param $fieldName Имя поля, значение которого проверяется (подставим в текст исключения в случае проблем, чтобы понятно было, что за поле)
     * @param $sizeMaxLimit Мкксимальный лимит символов
     * @throws Exception
     */
    public static function validateString($value, $fieldName, $sizeMaxLimit = null)
    {
        if ($sizeMaxLimit != null)
        {
            self::checkStringSize($value, $sizeMaxLimit, $fieldName);
        }

        $description = 'Строка. Допускается цифры, буквы, символы: -,.()$№"+ =*%;:';

        if (!preg_match('/^[\wа-яА-ЯёЁ\-\s,.\(\)\$\№\"\'\+\ =\*\%\;\:\|]{1,'. $sizeMaxLimit .'}$/u', $value))
        {
            throw new ValidateException('['. MyExceptionCodes::WrongArgument .'] Wrong Argument on field: '. $fieldName .'. Descr: '. $description,
                MyExceptionCodes::WrongArgument, null, $fieldName, $value);
        }
    }

    /**
     * Проверка на обязательность
     * @param $value Значение для проверки
     * @param $fieldName Имя поля, значение которого проверяется (подставим в текст исключения в случае проблем, чтобы понятно было, что за поле)
     * @param $nullable True если  может быть NULL, иначе False
     * @throws Exception
     */
    public static function checkNullable($value, $fieldName, $nullable)
    {
        if ($value!= null)
        {
            $value = trim($value);
        }

        if ($nullable != true)
        {
            if (is_null($value) || $value == '')
            {
                throw new ValidateException('Code: ['. MyExceptionCodes::WrongArgument .'] Argument may not be a null: '. $fieldName,
                    MyExceptionCodes::WrongArgument, null, $fieldName, $value);
            }
        }
    }

    /**
     * Проверка типа UUID
     * @param $value Значение для проверки
     * @param $fieldName Имя поля, значение которого проверяется (подставим в текст исключения в случае проблем, чтобы понятно было, что за поле)
     * @throws Exception
     */
    public static function validateUUID($value, $fieldName)
    {
        $description = 'UUID - 16-байтный (128-битный) номер. Записывается с разделением групп, пример: 550e8400-e29b-41d4-a716-446655440000';

        if (!preg_match("/^\{?[A-Z0-9]{8}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{12}\}?$/ui", $value))
        {
            throw new ValidateException('['. MyExceptionCodes::WrongArgument .'] Wrong Argument on field: '. $fieldName .'. Descr: '. $description,
                MyExceptionCodes::WrongArgument, null, $fieldName, $value);
        }
    }

    /**
     * Проверка типа PhoneNumberType
     * @param $value Значение для проверки
     * @param $fieldName Имя поля, значение которого проверяется (подставим в текст исключения в случае проблем, чтобы понятно было, что за поле)
     * @throws Exception
     */
    public static function validatePhoneNumberType($value, $fieldName)
    {
        $sizeMaxLimit = 10;
        self::checkStringSize($value, $sizeMaxLimit, $fieldName);

        $description = 'Номер телефона в десятизначном формате, только цифры. Пример: 9164974253.';

        if (!preg_match("/\d+$/u", $value))
        {
            throw new ValidateException('['. MyExceptionCodes::WrongArgument .'] Wrong Argument on field: '. $fieldName .'. Descr: '. $description,
                MyExceptionCodes::WrongArgument, null, $fieldName, $value);
        }
    }

    /**
     * Проверка типа EmailAddressType
     * @param $value Значение для проверки
     * @param $fieldName Имя поля, значение которого проверяется (подставим в текст исключения в случае проблем, чтобы понятно было, что за поле)
     * @throws Exception
     */
    public static function validateEmailAddressType($value, $fieldName)
    {
        $sizeMaxLimit = 100;
        self::checkStringSize($value, $sizeMaxLimit, $fieldName);

        $description = 'Пример: test@rosminzdrav.ru';

        if (!preg_match("/\A[^@ ]+@([^@ \.]+\.)+[^@\.]+\z/iu", $value))
        {
            throw new ValidateException('['. MyExceptionCodes::WrongArgument .'] Wrong Argument on field: '. $fieldName .'. Descr: '. $description,
                MyExceptionCodes::WrongArgument, null, $fieldName, $value);
        }
    }

    /**
     * Проверка типа GenderType
     * @param $value Значение для проверки
     * @param $fieldName Имя поля, значение которого проверяется (подставим в текст исключения в случае проблем, чтобы понятно было, что за поле)
     * @throws Exception
     */
    public static function validateGenderType($value, $fieldName)
    {
        $description = 'Male – мужской, Female – женский';

        if (!preg_match("/^(male|female)$/iu", $value))
        {
            throw new ValidateException('['. MyExceptionCodes::WrongArgument .'] Wrong Argument on field: '. $fieldName .'. Descr: '. $description,
                MyExceptionCodes::WrongArgument, null, $fieldName, $value);
        }
    }

    /**
     * Проверка типа rus-100
     * @param $value Значение для проверки
     * @param $fieldName Имя поля, значение которого проверяется (подставим в текст исключения в случае проблем, чтобы понятно было, что за поле)
     * @throws Exception
     */
    public static function validateRus100($value, $fieldName)
    {
        $sizeMaxLimit = 100;
        self::checkStringSize($value, $sizeMaxLimit, $fieldName);

        $description = 'Русский текст. Допустимы: точка, запятая, дефис. Прочее (цифры и т.п.) не допускаются.';

        if (!preg_match("/^[а-яА-ЯёЁ\-\s,.]{1,". $sizeMaxLimit ."}$/u", $value))
        {
            throw new ValidateException('['. MyExceptionCodes::WrongArgument .'] Wrong Argument on field: '. $fieldName .'. Descr: '. $description,
                MyExceptionCodes::WrongArgument, null, $fieldName, $value);
        }
    }

    /**
     * Проверка типа date формата ГГГГ-ММ-ДД
     * @param $value Значение для проверки
     * @param $fieldName Имя поля, значение которого проверяется (подставим в текст исключения в случае проблем, чтобы понятно было, что за поле)
     * @throws Exception
     */
    public static function validateDate($value, $fieldName)
    {
        $description = 'Дата в формате ГГГГ-ММ-ДД.';

        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/u", $value))
        {
            throw new ValidateException('['. MyExceptionCodes::WrongArgument .'] Wrong Argument on field: '. $fieldName .'. Descr: '. $description,
                MyExceptionCodes::WrongArgument, null, $fieldName, $value);
        }
    }

    /**
     * Проверка типа UPNType
     * @param $value Значение для проверки
     * @param $fieldName Имя поля, значение которого проверяется (подставим в текст исключения в случае проблем, чтобы понятно было, что за поле)
     * @throws Exception
     */
    public static function validateUPNType($value, $fieldName)
    {
        $sizeMaxLimit = 16;
        $sizeMinLimit = 16;
        self::checkStringSize($value, $sizeMaxLimit, $fieldName);
        self::checkStringMinSize($value, $sizeMinLimit, $fieldName);

        $description = 'Строка из 16 цифр. Пример: 3210987654321098.';

        if (!preg_match("/\d+$/u", $value))
        {
            throw new ValidateException('['. MyExceptionCodes::WrongArgument .'] Wrong Argument on field: '. $fieldName .'. Descr: '. $description,
                MyExceptionCodes::WrongArgument, null, $fieldName, $value);
        }
    }

    /**
     * Проверка типа SNILSType
     * @param $value Значение для проверки
     * @param $fieldName Имя поля, значение которого проверяется (подставим в текст исключения в случае проблем, чтобы понятно было, что за поле)
     * @throws Exception
     */
    public static function validateSNILSType($value, $fieldName)
    {
        $sizeMaxLimit = 11;
        $sizeMinLimit = 11;
        self::checkStringSize($value, $sizeMaxLimit, $fieldName);
        self::checkStringMinSize($value, $sizeMinLimit, $fieldName);

        $description = 'Строка из 11 цифр. Пример: 16364856496.';

        if (!preg_match("/\d+$/u", $value))
        {
            throw new ValidateException('['. MyExceptionCodes::WrongArgument .'] Wrong Argument on field: '. $fieldName .'. Descr: '. $description,
                MyExceptionCodes::WrongArgument, null, $fieldName, $value);
        }

        // Проверим СНИЛС
        $sum = 0;

        for ($i = 0; $i < 9; $i++)
        {
            $sum += (int) $value{$i} * (9 - $i);
        }

        $check_digit = 0;

        if ($sum < 100)
        {
            $check_digit = $sum;
        }
        elseif ($sum > 101)
        {
            $check_digit = $sum % 101;

            if ($check_digit === 100)
            {
                $check_digit = 0;
            }
        }
        if ($check_digit === (int) substr($value, -2))
        {
            $result = true;
        }
        else
        {
            throw new ValidateException('['. MyExceptionCodes::WrongArgument .'] Wrong Argument on field: '. $fieldName .'. Descr: Некорректный снилс (не прошёл проверку на контрольное число)',
                MyExceptionCodes::WrongArgument, null, $fieldName, $value);
        }
    }


    /**
     * Проверка на размер строки
     * @param $str Значение для проверки
     * @param $size Размер, соответствие которому нужно проверить
     * @throws Exception
     */
    public static function checkStringSize($str, $size, $fieldName)
    {
        if (mb_strlen($str) > $size)
        {
            throw new ValidateException('['. MyExceptionCodes::WrongStringSize .'] Wrong string size on field: '. $fieldName .', requested '. $size .', recieve '. mb_strlen($str),
                MyExceptionCodes::WrongStringSize, null, $fieldName, $str);
        }
    }

    /**
     * Проверка на минимальный размер строки
     * @param $str Значение для проверки
     * @param $size Минимальный размер, соответствие которому нужно проверить
     * @throws Exception
     */
    public static function checkStringMinSize($str, $size, $fieldName)
    {
        if (mb_strlen($str) < $size)
        {
            throw new ValidateException('['. MyExceptionCodes::WrongStringSize .'] Wrong string size on field: '. $fieldName .', requested MINIMUM '. $size .', recieve '. mb_strlen($str),
                MyExceptionCodes::WrongStringSize, null, $fieldName, $str);
        }
    }


    /**
     * Проверка на boolean
     * @param $value Значение для проверки
     * @throws Exception
     */
    public static function checkBoolean($value)
    {
        if (!is_bool($value))
        {
            throw new Exception('['. MyExceptionCodes::WrongType .'] Wrong argument, requested boolean. Value: '. $value, MyExceptionCodes::WrongType);
        }
    }

    /**
     * Проверка на соответствие классу
     * @param $value Класс для проверки
     * @param $className Имя класса (на какой проверяется)
     * @throws Exception
     */
    public static function checkClass(&$value, $className)
    {
        if (!$value instanceof $className)
        {
            throw new Exception('['. MyExceptionCodes::WrongClass .'] Wrong CLASS, requested '. $className .', recieved: '. get_class($value), MyExceptionCodes::WrongClass);
        }
    }

}