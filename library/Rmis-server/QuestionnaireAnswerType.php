<?php
require_once 'autoload.php';

class QuestionnaireAnswerType extends class_InputDataTypes
{
    protected function initDifferences()
    {
        $differences = array();

        $differences['questionCode'] = new MyTypeValidate(MyTypes::string50, false);
        $differences['answerValue'] = new MyTypeValidate(MyTypes::string, false);

        return $differences;
    }


    /**
     * Код вопроса
     * @var string-50 $questionCode
     */
    public $questionCode = '';

    /**
     * Значение, ответ
     * @var string $answerValue
     */
    public $answerValue = '';

}
