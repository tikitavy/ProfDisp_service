<?php
require_once 'autoload.php';

class EmployeeType extends class_OutputDataTypes
{
    /**
     * СНИЛС медицинского работника
     * @var SNILSType $employeeSnils
     */
    public $employeeSnils = '';

    /**
     * Код должности медицинского работника.
     *    Должно соответствовать коду должности медицинского персонала согласно
     *    классификатору ФНСИ 1.2.643.5.1.13.13.11.1102 «ФРМР. Должности медицинского персонала».
     *    Пример: 109
     * @var string-50 $employeePositionCode
     */
    public $employeePositionCode = '';
}