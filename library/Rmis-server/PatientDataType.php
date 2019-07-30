<?php
require_once 'autoload.php';

class PatientDataType extends class_InputDataTypes
{
    protected function initDifferences()
    {
        $differences = array();

        $differences['surname'] = new MyTypeValidate(MyTypes::rus100, false);
        $differences['name'] = new MyTypeValidate(MyTypes::rus100, false);
        $differences['patronymic'] = new MyTypeValidate(MyTypes::rus100, true);
        $differences['birthDate'] = new MyTypeValidate(MyTypes::date, false);
        $differences['unifiedPolicyNumber'] = new MyTypeValidate(MyTypes::UPNType, true);
        $differences['snils'] = new MyTypeValidate(MyTypes::SNILSType, true);
        $differences['gender'] = new MyTypeValidate(MyTypes::GenderType, false);
        $differences['email'] = new MyTypeValidate(MyTypes::EmailAddressType, true);
        $differences['phone'] = new MyTypeValidate(MyTypes::PhoneNumberType, true);

        return $differences;
    }


    /**
     * Фамилия
     * @var rus-100 $surname
     */
    public $surname = '';

    /**
     * Имя
     * @var rus-100 $name
     */
    public $name = '';

    /**
     * Отчество
     * @var rus-100 $patronymic
     */
    public $patronymic = null;

    /**
     * Дата рождения
     * @var date $birthDate
     */
    public $birthDate = '';

    /**
     * Единый номер полиса ОМС (ЕНП)
     * @var UPNType $unifiedPolicyNumber
     */
    public $unifiedPolicyNumber = null;

    /**
     * СНИЛС
     * @var SNILSType $snils
     */
    public $snils = null;

    /**
     * Пол
     * @var GenderType $gender
     */
    public $gender = '';

    /**
     * Адрес email (по этому полю нет поиска, т.к. оно отсутствует в БД)
     * @var EmailAddressType $email
     */
    public $email = null;

    /**
     * Номер телефона (по этому полю нет поиска, т.к. оно отсутствует в БД)
     * @var PhoneNumberType $phone
     */
    public $phone = null;
}
