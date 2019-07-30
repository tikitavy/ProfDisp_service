<?php

##############################################################################
##
## Класс для фильтрации возрастов
##
##############################################################################
##
## Copyright (C) 2006-2011 Chuk&Gek and Vista Software. All rights reserved.
##
##############################################################################


class TAgeSelector
{
    private $_begUnit;
    private $_begCount;
    private $_endUnit;
    private $_endCount;

    function __construct($selectorStr)
    #  selector syntax: "{NNN{д|н|м|г}-{MMM{д|н|м|г}}" -
    #  с NNN дней/недель/месяцев/лет по MMM дней/недель/месяцев/лет;
    #  пустая нижняя или верхняя граница - нет ограничения снизу или сверху
    {
        $this->_begUnit  = 0;
        $this->_begCount = 0;
        $this->_endUnit  = 0;
        $this->_endCount = 0;

        $vParts = explode('-', $selectorStr);
        if ( count($vParts) >= 2 )
        {
            list($this->_begUnit, $this->_begCount) = $this->_ParsePart($vParts[0]);
            list($this->_endUnit, $this->_endCount) = $this->_parsePart($vParts[1]);
        }
        elseif ( count($vParts) == 1 )
        {
            list($this->_begUnit, $this->_begCount) = $this->_ParsePart($vParts[0]);
        }
    }


    function Dump()
    {
        print_r( array( $this->_begUnit, $this->_begCount, $this->_endUnit, $this->_endCount) );
    }

    function CheckAgeArray($ageArray)
    {
        if ( $this->_begUnit )
        {
            if ( $ageArray[$this->_begUnit-1] < $this->_begCount )
                return false;
        }
        if ( $this->_endUnit )
        {
            if ( $ageArray[$this->_endUnit-1] > $this->_endCount )
                return false;
        }
        return true;
    }


    function CheckAgeISODate($birthDay, $today=NULL)
    {
        return $this->CheckAgeArray( GetAgeArray($birthDay, $today) );
    }


    function _ParsePart($part)
    {
        $unit = 0;
        $count = 0;
        $vPart = trim($part);
        if ( $vPart )
        {
            sscanf($vPart, '%d%s', $count, $vUnitCode);
            switch ( $vUnitCode )
            { # это можно заменить простым поиском по строке,
              # но в этом случае требуется mb_stiring, а я не хочу завязываться 
                case 'д':
                case 'Д': $unit = 1; break;
                case 'н':
                case 'Н': $unit = 2; break;
                case 'м':
                case 'М': $unit = 3; break;
                case 'г':
                case 'Г': $unit = 4; break;
                default : break;
            }
        }
        return array($unit, $count);
    }

    function _OutPartForSoap($unit, $count)
    {
        if ( $unit )
            return array( 'unit'=>substr('dwmy', $unit-1,1), 'count'=>$count );
        else
            return NULL;
    }

    function OutForSoap()
    {
        if ( $this->_begUnit || $this->_endUnit )
        return array(
            'from' => $this->_OutPartForSoap($this->_begUnit, $this->_begCount),
            'to'   => $this->_OutPartForSoap($this->_endUnit, $this->_endCount)
            );
        else
            return NULL;
    }
}


function GetAgeArray($birthDay, $today=NULL)
{
    list($vBYear, $vBMonth, $vBDay) = array_filter( explode('-', $birthDay), 'intval');
    $vBJD = GregorianToJD($vBMonth, $vBDay, $vBYear);

    list($vCYear, $vCMonth, $vCDay) = array_filter( explode('-', $today ? $today : date('Y-m-d')), 'intval');
    $vCJD = GregorianToJD($vCMonth, $vCDay, $vCYear);

    $vDays   = $vCJD-$vBJD;
    $vWeeks  = intval($vDays/7);
    $vMonths = ($vCYear-$vBYear)*12+($vCMonth-$vBMonth) - ($vBDay > $vCDay ? 1 : 0);
    $vYears  = ($vCYear-$vBYear) - (($vBMonth>$vCMonth || ($vBMonth == $vCMonth && $vBDay > $vCDay)) ? 1 : 0);
    return array($vDays, $vWeeks, $vMonths, $vYears);
}

?>
