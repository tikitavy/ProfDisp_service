<?php
require_once __DIR__ . '/library/Rmis-server/autoload.php';
require_once __DIR__ . '/library/SMSO/library/database.php';
require_once __DIR__ . '/library/SMSO/smsoFuncList.php';

$str = "03065261618";
$result = MyTypeValidate::validateSNILSType($str, "qweqweqwe");
var_dump($result);
echo print_r($result, true);



/*

$db = GetDB();

// Ищем список всех дат и врачей с талонами

/*$result = $db->Select('Action'
    .' LEFT JOIN ActionType ON ActionType.id = Action.actionType_id'
    .' LEFT JOIN Event ON Event.id = Action.event_id'
    .' LEFT JOIN EventType ON EventType.id = Event.eventType_id'
    .' LEFT JOIN Person ON Person.id = Event.setPerson_id',
    'Action.id AS id, Event.setPerson_id AS person_id, DATE(Event.setDate) as date',
    $db->CondAnd(
        '`Event`.`deleted`=0 AND `Action`.`deleted`=0 AND `EventType`.`code` = \'0\' AND `ActionType`.`code` =\'amb\'',
        $db->CondIn('Event`.`setPerson_id', '12'),
        $db->CondGE('Event`.`setDate', '2019-07-01'),
        $db->CondLE('Event`.`setDate', '2019-07-31'),
        '(Person.lastAccessibleTimelineDate IS NULL OR Person.lastAccessibleTimelineDate = \'0000-00-00\' OR DATE(Event.setDate)<=Person.lastAccessibleTimelineDate)',
        '(Person.timelineAccessibleDays IS NULL OR Person.timelineAccessibleDays <= 0 OR DATE(Event.setDate)<=ADDDATE(CURRENT_DATE(), Person.timelineAccessibleDays))'
    ),
    'Event.setPerson_id'
);
*/
/*
class params
{
    public $orgStructureId = '';
    public $recursive = '';
    public $specialityNotation = '';
    public $speciality = '';
    public $personId = '';
    public $begDate = '';
    public $endDate = '';
    public $date = '';

}

$aParams = new params();
$aParams->orgStructureId = false;
$aParams->recursive = true;
$aParams->specialityNotation = false;
$aParams->speciality = false;
$aParams->personId = 12;
$aParams->begDate = '2019-07-01';
$aParams->endDate = '2019-07-31';
$aParams->date = '2019-07-16';

$result = getTicketsAvailability($aParams);

$arrDates = array();

foreach ($result['list'] as $value)
{
    checkVariableIsSet($value['free'], 'Неверные данные, полученные от талона (нет ожидаемых параметров: free)');
    checkVariableIsSet($value['date'], 'Неверные данные, полученные от талона (нет ожидаемых параметров: date)');

    if ($value['free'] > 0)
    {
        $arrDates[] = $value['date'];
    }
}

$aParams = new params();
foreach ($arrDates as $date)
{
    echo $date;

    $aParams->date = $date;
    $aParams->personId = 12;
    $result = getWorkTimeAndStatus($aParams);
    $result = $result['amb']['tickets'];

    if ($result != null)
    {
        foreach ($result as $tickets)
        {
            checkVariableIsSet($tickets['free'], 'Неверные данные, полученные от талона (нет ожидаемых параметров: free)');
            checkVariableIsSet($tickets['time'], 'Неверные данные, полученные от талона (нет ожидаемых параметров: time)');

            if ($tickets['free'] === true)
            {
                echo $date .' '. $tickets['time'] ."\n";
            }
        }
    }
    else
    {
        echo 'нет талонов';
    }

}


//






function checkVariableIsSet($var, $message)
{
    if (!isset($var))
    {
        throw new WrongDataException($message);
    }
}



/*
$sql = "

SELECT *
FROM
  Client
WHERE
  lastName = 'Абраамян'
  AND firstName = 'Гайк'
  AND patrName = 'Врежович'
      AND birthDate = '1973-09-15'
  AND sex = '1';

";

$result= $db->Query($sql);

while ($row = mysql_fetch_assoc($result)) {
    echo $row['firstName'] .' ';
    echo $row['lastName'] .' ';
    echo $row['birthDate'] .' ';
    echo $row['id'] .' ';
}

mysql_free_result($result);

//$subject = '2015-12-31';

//MyTypes::checkStringSize($subject, 15);
/*
if (preg_match("/[а-яА-ЯёЁ\-\s,.]/mi", $subject))
{
    echo "да";
} else {
    echo "нет";
}
*/

//if (preg_match("/^[0-9]{1,10}$/", $subject))
//if (preg_match("/^[а-яА-ЯёЁ\-\s,.]{1,10}$/", $subject))

/*
if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/u", $subject))
{
    echo "\r\nДА";
}
else
{
    echo "\r\nНЕТ";
}
*/

