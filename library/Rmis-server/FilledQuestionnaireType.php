<?php
require_once 'autoload.php';

class FilledQuestionnaireType extends class_InputDataTypes
{
    protected function initDifferences()
    {
        $differences = array();
        $differences['questionnaireType'] = new MyTypeValidate(MyTypes::string255, false);

        return $differences;
    }

    protected function initObjects()
    {
        $objects = array();
        $objects['questionnaireAnswer'] = 'QuestionnaireAnswerType';

        return $objects;
    }

    /**
     * Добавим дополнительную проверку поля "Тип анкеты"
     * @throws ValidateException
     */
    protected function addValidate()
    {
        // Поле questionnaireType может содержать только конкретные значения, проверим их тут, и подставим нужные для SQL
        $this->questionnaireType = trim($this->questionnaireType);

        switch ($this->questionnaireType)
        {
            case 'Анкета для граждан в возрасте до 75 лет':
                $this->questionnaireType = 'qdisp_to75';
                break;
            case 'Анкета для граждан в возрасте после 75 лет':
                $this->questionnaireType = 'qdisp_from75';
                break;
            case 'qdisp_from75':
                break;
            case 'qdisp_to75':
                break;
            default:
                $description = 'Ожидается значение из списка: "Анкета для граждан в возрасте после 75 лет", "Анкета для граждан в возрасте до 75 лет", "qdisp_from75", "qdisp_to75".';
                throw new ValidateException('['. MyExceptionCodes::WrongArgument .'] Wrong Argument on field: questionnaireType. Descr: '. $description,
                    MyExceptionCodes::WrongArgument, null, 'questionnaireType', $this->questionnaireType);
        }


        // А тут проверим, не пустой ли массив вопрос-ответ нам передали
        if (MyLib::trueIsEmpty($this->questionnaireAnswer))
        {
            $description = 'Ожидается массив "код вопроса" - "ответ" (массив передан пустым).';
            throw new ValidateException('['. MyExceptionCodes::WrongArgument .'] Wrong Argument on field: questionnaireAnswer. Descr: '. $description,
                MyExceptionCodes::WrongArgument, null, 'questionnaireAnswer', $this->questionnaireAnswer);
        }
    }


    /**
     * Тип анкеты
     * Может принимать следующие значения:
     *    - Анкета для граждан в возрасте до 75 лет;
     *    - Анкета для граждан в возрасте после 75 лет.
     * @var string-255 $questionnaireType
     */
    public $questionnaireType = '';

    /**
     * Ответы на вопросы
     * @var QuestionnaireAnswerType[] $questionnaireAnswer
     */
    public $questionnaireAnswer = null;
}
