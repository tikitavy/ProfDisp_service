<?php
// MOD! 2019-07-18 15'29'35, Четверг


##############################################################################
##
## Сервис самозаписи
##
##############################################################################
##
## Copyright (C) 2006-2012 Chuk&Gek and Vista Software. All rights reserved.
##
##############################################################################

require_once 'library/soap-common.php';
require_once 'library/database.php';
require_once 'library/trace-log.php';
require_once 'library/ageselector.php';
require_once 'library/applock.php';

#$ini = ini_set("soap.wsdl_cache_enabled","0");

define('msgOk',                             '100 ok');
define('msgNoSuchPatient',                  '200 Пациента искали - и не нашли.');
define('msgTooManySuchPatients',            '201 Пациента искали - и нашли более 1.');
define('msgNoSuchIdentifierType',           '202 Указанного типа идентификатора пациента не существует.');
define('msgWrongPatientId',                 '302 Передали id пациента - а такой записи нет (или она удалена).');
define('msgPatientMarkedAsDead',            '303 Пациент отмечен как умерший.');
define('msgEnqueueNotApplicable',           '304 Пациент не подходит по полу или возрасту.');
define('msgQueueNotFound',                  '305 Очереди (записи) у указанного врача на указанную дату нет.');
define('msgTicketNotFound',                 '306 В очереди нет приёма на это время.');
define('msgPatientAlreadyQueued',           '307 Пациент уже записан.');
define('msgTicketIsBusy',                   '308 Талон уже занят.');
define('msgPatientQueueNotFound',           '309 Указанная запись на приём к врачу не найдена.');
define('msgPatientNotAttached',             '310 Пациент не имеет прикрепления.');
define('msgPatientAttachInsufficient',      '311 Прикрепление пациента не предусматривает обслуживание.');
define('msgInsuranceCompanyServiceStopped', '312 Работа с клиентами СМО приоснановлена.');
define('msgCannotLockQueue',                '313 Невозможно получить блокировку очереди.');
define('msgQueueingProhibited',             '400 Постановка в очередь запрещена.');


define('clientSelectLimit', 100);
define('queueSelectLimit',  100);



function _sexToCode($aSexStr)
{
    switch ($aSexStr)
    { /* это можно заменить простым поиском по строке,                   */
      /* но в этом случае требуется mb_stiring, а я не хочу завязываться */
        case 'М':
        case 'м': return 1; break;
        case 'Ж':
        case 'ж': return 2; break;
        default:  return 0; break;
    }
}


function _formatSex($aSexCode)
{
    switch( $aSexCode )
    {
        case  1: return 'М';
        case  2: return 'Ж';
        default: return '';
    }
}


function _convertDotsToMask($aStr)
{
    return strrev(preg_replace('/\.{3}/', '%', strrev($aStr)));
}


function _getCurrentPersonId()
{
    static $currentPersonId;

    if ( $currentPersonId === NULL )
    {
        $db = GetDB();
        $currentPersonId = $db->Translate('Person', 'login', gCurrentUserCode, 'id');
    }
    return $currentPersonId;
}


function _getOrgStructureIdList($aParendId, $aRecursive)
{
    $db = GetDB();
    $vRecords = $db->Select('OrgStructure LEFT JOIN Organisation ON OrgStructure.organisation_id = Organisation.id',
                            'OrgStructure.id',
                            $db->CondAnd(
                               $db->CondEqual('Organisation`.`infisCode', gCurrentOrganisationInfisCode),
                               '`OrgStructure`.`availableForExternal`',
                               $aParendId == NULL
                                   ? $db->CondIsNull('OrgStructure`.`parent_id')
                                   : $db->CondEqual('OrgStructure`.`parent_id', $aParendId))
                           );

    $vResult = array();
    while( $vRecord = $vRecords->Fetch() )
    {
        $vResult[] = $vRecord['id'];
    }

    if ( $aRecursive )
    {
        $vLeaves = $vResult;
        while( $vLeaves )
        {
            $vRecords = $db->Select('OrgStructure LEFT JOIN Organisation ON OrgStructure.organisation_id = Organisation.id',
                                    'OrgStructure.id',
                                    $db->CondAnd(
                                       $db->CondEqual('Organisation`.`infisCode', gCurrentOrganisationInfisCode),
                                       '`OrgStructure`.`availableForExternal`',
                                       $db->CondIn('OrgStructure`.`parent_id', $vLeaves))
                                   );
            $vLeaves = array();
            while( $vRecord = $vRecords->Fetch() )
            {
                $vId = $vRecord['id'];
                $vLeaves[] = $vId;
                $vResult[] = $vId;
            }
        }
    }
    return $vResult;
}


function _getOrgStructureNetId($db, $aId, &$aMapIdToNetId, &$aMapIdToParentId)
{
    $vIdList = array();
    for( $vId=$aId; ; $vId = @$aMapIdToParentId[$vId] )
    {
        if ( array_key_exists($vId, $aMapIdToNetId) )
        {
            $vNetId = $aMapIdToNetId[$vId];
            foreach($vIdList as $vId)
                $aMapIdToNetId[$vId] = $vNetId;
            return $vNetId;
        }
        $vIdList[] = $vId;
        if ( !array_key_exists($vId, $aMapIdToParentId) )
        {
            if ( $vId )
                $vRecord = $db->Get('OrgStructure', 'parent_id, net_id', $db->CondEqual('id', $vId));
            else
                $vRecord = $db->Get('Organisation', 'net_id', $db->CondEqual('infisCode', gCurrentOrganisationInfisCode));
            if ( $vRecord )
            {
                $vNetId = $vRecord['net_id'];
                if ( $vNetId )
                    $aMapIdToNetId[$vId] = $vNetId;
                $aMapIdToParentId[$vId] = @$vRecord['parent_id'];
            }
            else
            {
                $aMapIdToNetId[$vId] = NULL;
                $aMapIdToParentId[$vId] = NULL;
            }
        }
    }
}


function _getSpecialityId($aNotation, $aSpeciality)
{
    $db = GetDB();
    switch( strtoupper($aNotation) )
    {
        case 'OKSOCODE':
            $vCond = $db->CondEqual('OKSOCode', $aSpeciality);
            break;
        case 'REGIONALCODE':
            $vCond = $db->CondEqual('regionalCode', $aSpeciality);
            break;
        case 'CODE':
            $vCond = $db->CondEqual('code', $aSpeciality);
            break;
        case 'ID':
            $vCond = $db->CondEqual('id', $aSpeciality);
            break;
        default:
            $vCond = $db->CondEqual('name', $aSpeciality);
            break;
    }
    $vRecord = $db->Get('rbSpeciality', 'id', $vCond);
    return $vRecord ? $vRecord['id'] : 0;
}


function _getPersonIdList($aOrgStructureId, $aRecursive, $aSpecialityNotation, $aSpeciality, $aPersonId)
{
    if( $aRecursive or !$aOrgStructureId )
    {
        $vOrgStructureIdList = _getOrgStructureIdList($aOrgStructureId, True);
        if( $aOrgStructureId )
            $vOrgStructureIdList[] = $aOrgStructureId;
    }
    else
    {
        $vOrgStructureIdList = array($aOrgStructureId);
    }

    if ( $aSpeciality )
        $vSpecialityId = _getSpecialityId($aSpecialityNotation, $aSpeciality);
    else
        $vSpecialityId = NULL;

    $db = GetDB();

    $vCond = '`Person`.`deleted`=0 AND `Person`.`retired`=0 AND `Person`.`speciality_id` IS NOT NULL';
    if ( $vOrgStructureIdList )
        $vCond = $db->condAnd($vCond, $db->CondIn('Person`.`orgStructure_id', $vOrgStructureIdList));
    if ( $vSpecialityId )
        $vCond = $db->condAnd($vCond, $db->CondEqual('Person`.`speciality_id', $vSpecialityId));
    if ( $aPersonId )
        $vCond = $db->condAnd($vCond, $db->CondEqual('Person`.`id', $aPersonId));

    return $db->SelectIDsId('Person', $vCond);
}


function _findOrgStructureByAddress($db, $aKLADRCode, $aKLADRStreetCode, $aNumber, $aCorpus, $aFlat)
{
    $vRecords = $db->Select('OrgStructure_Address'
                 .' LEFT JOIN OrgStructure ON OrgStructure.id = OrgStructure_Address.master_id'
                 .' LEFT JOIN AddressHouse ON AddressHouse.id = OrgStructure_Address.house_id',
                 'DISTINCT OrgStructure_Address.master_id',
                 $db->CondAnd(
                    '`AddressHouse`.`deleted`=0',
                    '`OrgStructure`.`deleted`=0',
                    $db->CondEqual('AddressHouse`.`KLADRCode',       $aKLADRCode),
                    $db->CondEqual('AddressHouse`.`KLADRStreetCode', $aKLADRStreetCode),
                    $db->CondEqual('AddressHouse`.`number',          $aNumber),
                    $db->CondEqual('AddressHouse`.`corpus',          $aCorpus),
                    $db->CondOr( '`OrgStructure_Address`.`lastFlat`=0',
                                 $db->CondAnd(
                                    $db->CondLE('OrgStructure_Address`.`firstFlat', $aFlat),
                                    $db->CondGE('OrgStructure_Address`.`lastFlat', $aFlat)))
                 )
                );
    $vResult = array();
    while( $vRecord = $vRecords->Fetch() )
    {
        $vResult[] = $vRecord['master_id'];
    }
    return $vResult;
}


function _getOrgStructuresByClientAttach($db, $aClientId)
{
    $vRecords = $db->Select('`Client`'
                            .' INNER JOIN `ClientAttach` ON `ClientAttach`.`client_id` = `Client`.`id`'
                            .' INNER JOIN `rbAttachType` ON `rbAttachType`.`id` = `ClientAttach`.`attachType_id`'
                            .' INNER JOIN `Organisation` ON `Organisation`.`id` = `ClientAttach`.`LPU_id`',
                            'DISTINCT `ClientAttach`.`orgStructure_id`',
                            $db->CondAnd(
                                $db->CondEqual('Client`.`id', $aClientId),
                                '`Client`.`deleted`=0',
                                '`ClientAttach`.`deleted`=0',
                                '`rbAttachType`.`outcome`=0',
                                $db->CondEqual('Organisation`.`infisCode', gCurrentOrganisationInfisCode),
                                '`ClientAttach`.`orgStructure_id` IS NOT NULL',
                                $db->CondOr(
                                    $db->CondIsNull('ClientAttach`.`endDate'),
                                    $db->CondGE('ClientAttach`.`endDate', date('Y-m-d')))),
                            '`ClientAttach`.`orgStructure_id`');
    $vResult = array();
    while( $vRecord = $vRecords->Fetch() )
    {
        $vResult[] = $vRecord['orgStructure_id'];
    }
    return $vResult;
}


function _getClientAddress($db, $aClientId, $aIsRegAddress)
{
    $vStmt = sprintf('SELECT getClient%sAddressId(%d) AS id;', $aIsRegAddress ? 'Reg' : 'Loc', intval($aClientId));
    $vRows = new TMyRows($db->Query($vStmt));
    $vTmpRecord = $vRows->Fetch();
    if ( $vTmpRecord )
    {
        $vClientAddressId = $vTmpRecord['id'];
        $vRecord = $db->Get('`ClientAddress` '
                            .' INNER JOIN `Address` ON `Address`.`id` = `ClientAddress`.`address_id`'
                            .' INNER JOIN `AddressHouse` ON `AddressHouse`.`id` = `Address`.`house_id`',
                            '`KLADRCode`, `KLADRStreetCode`, `number`, `corpus`, `flat`, `freeInput`',
                            $db->CondEqual( 'ClientAddress`.`id', $vClientAddressId ));
        if ( $vRecord )
        {
            return array( $vRecord['KLADRCode'],
                          $vRecord['KLADRStreetCode'],
                          $vRecord['number'],
                          $vRecord['corpus'],
                          $vRecord['flat'],
                          $vRecord['freeInput'] );
        }
    }
    return NULL;
}


function _getOrgStructuresByClientAddress($db, $aClientId, $aIsRegAddress)
{
    $vAddressArray = _getClientAddress($db, $aClientId, $aIsRegAddress);
    if ( $vAddressArray )
        return _findOrgStructureByAddress($db, $vAddressArray[0], $vAddressArray[1], $vAddressArray[2], $vAddressArray[3], $vAddressArray[4]);
    return array();
}


function _getClientDeathDate($db, $aClientId)
{
    $vRecord = $db->Get('`ClientAttach` LEFT JOIN `rbAttachType` ON `rbAttachType`.`id`=`ClientAttach`.`attachType_id`',
                        '`begDate`',
                        $db->CondAnd(
                            $db->CondEqual('ClientAttach`.`client_id', $aClientId),
                            '`ClientAttach`.`deleted`=0',
                            '`rbAttachType`.`code`=\'8\''));
    if ( $vRecord )
        return $vRecord['begDate'];
    return NULL;
}


function _getClientTemporaryAttach($db, $aClientId)
{
    static $vClientId = NULL;
    static $vAttachInfo = NULL;

    if ( $vClientId != $aClientId )
    {
        $vRecord = $db->Get('`ClientAttach`'
                            .' INNER JOIN `rbAttachType` ON `rbAttachType`.`id` = `ClientAttach`.`attachType_id`'
                            .' INNER JOIN `Organisation` ON `Organisation`.`id` = `ClientAttach`.`LPU_id`',
                            '`ClientAttach`.`id`, `ClientAttach`.`attachType_id`, `ClientAttach`.`begDate`, `ClientAttach`.`endDate`',
                            $db->CondAnd(
                               $db->CondEqual('ClientAttach`.`client_id', $aClientId),
                               '`ClientAttach`.`deleted`=0',
                               '`rbAttachType`.`outcome`=0',
                               '`rbAttachType`.`temporary`=1',
                               $db->CondEqual('Organisation`.`infisCode', gCurrentOrganisationInfisCode)),
                               '`ClientAttach`.`id` DESC');
        $vAttachInfo = $vRecord;
        $vClientId = $aClientId;
    }
    return $vAttachInfo;
}


function _checkClientAttachExists($db, $aClientId, $aDate)
{
    if ( defined('gCheckPatientAttachMethod') && (gCheckPatientAttachMethod == 1 || gCheckPatientAttachMethod ==2) )
    {
        $vAttachInfo = _getClientTemporaryAttach($db, $aClientId);
        if ( !$vAttachInfo )
            return False;
        $vBegDate = $vAttachInfo['begDate'];
        $vEndDate = $vAttachInfo['endDate'];

        if ( gCheckPatientAttachMethod == 1 )
            $vTestDate = $aDate;
        else
            $vTestDate = date('Y-m-d');

        return (!$vBegDate || $vBegDate <= $vTestDate) &&
               (!$vEndDate || $vTestDate <= $vEndDate);
    }
    return True;
}


function _checkClientAttachSufficient($db, $aClientId, $aPersonId)
{
    if ( defined('gCheckPatientAttachSufficient') && (gCheckPatientAttachSufficient > 0) )
    {
        $vAttachInfo = _getClientTemporaryAttach($db, $aClientId);
        if ( !$vAttachInfo )
            return False;
        $vAttachTypeId = $vAttachInfo['attachType_id'];
        $vCnt = $db->CountRows('`OrgStructure_DisabledAttendance`'
                               .' LEFT JOIN `Person` ON `Person`.`orgStructure_id` = `OrgStructure_DisabledAttendance`.`master_id`',
                               '`OrgStructure_DisabledAttendance`.`id`',
                               $db->CondAnd(
                                  $db->CondEqual('OrgStructure_DisabledAttendance`.`attachType_id', $vAttachTypeId),
                                  $db->CondEqual('Person`.`id', $aPersonId)));
        return $vCnt == 0;
    }
    return True;
}


function _checkInsuranceCompanyService($db, $aClientId)
{
    if ( defined('gCheckInsuranceCompanyService') && (gCheckInsuranceCompanyService > 0) )
    {
        $vRecord = $db->Get('`ClientPolicy`'
                            .' INNER JOIN `rbPolicyType` ON `rbPolicyType`.`id` = `ClientPolicy`.`policyType_id`'
                            .' INNER JOIN `Organisation` ON `Organisation`.`id` = `ClientPolicy`.`insurer_id`',
                            '`Organisation`.`voluntaryServiceStop`',
                            $db->CondAnd(
                               $db->CondEqual('ClientPolicy`.`client_id', $aClientId),
                               '`ClientPolicy`.`deleted`=0',
                               $db->CondLikeBase('rbPolicyType`.`name', '%ДМС%')),
                               '`ClientPolicy`.`id` DESC');
        if ( !$vRecord )
            return False;
        return !$vRecord['voluntaryServiceStop'];
    }
    return True;
}


function _parsePolicy($aPolicy)
{
    $vParts = array_filter(explode(' ', $aPolicy), create_function('$s', 'return $s !== \'\';'));
    switch ( count($vParts) )
    {
        case 0:
                $vSerial = '';
                $vNumber = '';
                break;
        case 1:
                $vSerial = '';
                $vNumber = $vParts[0];
                break;
        default:
                $vSerial = array_shift($vParts);
                $vNumber = implode(' ', $vParts);
                break;
    }
    return array($vSerial, $vNumber);
}


function _findPatients($aParams, $aLimit)
{
    $vLastName   = trim(@$aParams->lastName);   // обнаружено, что некоторые пользователи добавляют лишние пробелы после фамилии
    $vFirstName  = trim(@$aParams->firstName);  // возможно, что они это сделают и в других полях.
    $vPatrName   = trim(@$aParams->patrName);
    $vBirthDate  = @cleanDate($aParams->birthDate);
    $vSex        = _sexToCode(trim(@$aParams->sex));
    $vIdentifierType = trim(@$aParams->identifierType);
    $vIdentifier = @$aParams->identifier;
    $vOmiPolicy  = trim(@$aParams->omiPolicy);
    $vDocumentSerial = trim(@$aParams->documentSerial);
    $vDocumentNumber = trim(@$aParams->documentNumber);

    $db = GetDB();

    if ( $vIdentifier )
    {
        if ( !$vIdentifierType || $vIdentifierType === '0' )
            $vIdentifierCond = $db->CondEqual('id',  $vIdentifier);
        else
        {
            $vAccountingSystemId = $db->Translate('rbAccountingSystem', 'code', $vIdentifierType, 'id');
            if ( !$vAccountingSystemId )
                return array('success'=>false, 'message'=>msgNoSuchIdentifierType);
            $vIdentifierCond = 'EXISTS(SELECT ClientIdentification.id FROM ClientIdentification WHERE'
                                    . $db->CondAnd(
                                          '`ClientIdentification`.`client_id`=`Client`.`id`',
                                          $db->CondEqual('accountingSystem_id', $vAccountingSystemId),
                                          $db->CondEqual('identifier', $vIdentifier),
                                          '`ClientIdentification`.`deleted`=0')
                                    .')';
        }
    }
    else
        $vIdentifierCond = '';

    if ( $vOmiPolicy )
    {
        list($vOmiPolicySerial, $vOmiPolicyNumber) = _parsePolicy($vOmiPolicy);
    }
    else
    {
        $vOmiPolicySerial = NULL;
        $vOmiPolicyNumber = NULL;
    }

    $vCond = $db->CondAnd($vLastName  ? $db->CondLikeBase('lastName',  _convertDotsToMask($vLastName))  : '',
                          $vFirstName ? $db->CondLikeBase('firstName', _convertDotsToMask($vFirstName)) : '',
                          $vPatrName  ? $db->CondLikeBase('patrName',  _convertDotsToMask($vPatrName))  : '',
                          $vBirthDate ? $db->CondEqual('birthDate', $vBirthDate)                        : '',
                          $vIdentifierCond,
                          $vSex       ? $db->CondEqual('sex', $vSex)                                    : '',
                          $vOmiPolicy ? $db->CondEqual('ClientPolicy`.`serial', $vOmiPolicySerial)      : '',
                          $vOmiPolicy ? $db->CondEqual('ClientPolicy`.`number', $vOmiPolicyNumber)      : '',
                          $vOmiPolicy ? $db->CondLikeBase('rbPolicyType`.`name', '%ОМС%')               : '',
                          $vOmiPolicy ? '`ClientPolicy`.`deleted`=0'                                    : '',
                          $vDocumentSerial ? $db->CondEqual('ClientDocument`.`serial', $vDocumentSerial): '',
                          $vDocumentNumber ? $db->CondEqual('ClientDocument`.`number', $vDocumentNumber): '',
                          '`Client`.`deleted`=0'
                         );
    $vTable = 'Client';
    if ( $vOmiPolicy )
       $vTable .= ' LEFT JOIN ClientPolicy ON ClientPolicy.client_id = Client.id AND ClientPolicy.deleted = 0'
               .  ' LEFT JOIN rbPolicyType ON rbPolicyType.id = ClientPolicy.policyType_id';
    if ( $vDocumentSerial || $vDocumentNumber )
       $vTable .= ' LEFT JOIN ClientDocument ON ClientDocument.client_id = Client.id AND ClientDocument.deleted = 0';

    return $db->SelectIDsEx($vTable, 'DISTINCT Client.id', $vCond, 'lastName, firstName, patrName, birthDate, Client.id', $aLimit);
}


function _getActionId($actionTypeCode, $aPersonId, $aDate)
{
    $db = GetDB();
    $vRecord = $db->Get('Action'
                 .' LEFT JOIN ActionType ON ActionType.id = Action.actionType_id'
                 .' LEFT JOIN Event ON Event.id = Action.event_id'
                 .' LEFT JOIN EventType ON EventType.id = Event.eventType_id'
                 .' LEFT JOIN Person ON Person.id = Event.setPerson_id',
                 'Action.id',
                 $db->CondAnd(
                    '`Event`.`deleted`=0 AND `Action`.`deleted`=0 AND `EventType`.`code` = \'0\'',
                    $db->CondEqual('ActionType`.`code', $actionTypeCode),
                    $db->CondEqual('Event`.`setPerson_id', $aPersonId),
                    $db->CondEqual('Event`.`setDate', $aDate),
                    '(Person.lastAccessibleTimelineDate IS NULL OR Person.lastAccessibleTimelineDate = \'0000-00-00\' OR DATE(Event.setDate)<=Person.lastAccessibleTimelineDate)',
                    '(Person.timelineAccessibleDays IS NULL OR Person.timelineAccessibleDays <= 0 OR DATE(Event.setDate)<=ADDDATE(CURRENT_DATE(), Person.timelineAccessibleDays))'
                ));
    return $vRecord ? $vRecord['id'] : NULL;
}

function _getAmbActionId($aPersonId, $aDate)
{
    return _getActionId('amb', $aPersonId, $aDate);
}

function _getTimeLineActionId($aPersonId, $aDate)
{
    return _getActionId('timeLine', $aPersonId, $aDate);
}


function _getAmbActionRecords($aPersonIdList, $aBegDate, $aEndDate)
{
    $db = GetDB();
    return $db->Select('Action'
                 .' LEFT JOIN ActionType ON ActionType.id = Action.actionType_id'
                 .' LEFT JOIN Event ON Event.id = Action.event_id'
                 .' LEFT JOIN EventType ON EventType.id = Event.eventType_id'
                 .' LEFT JOIN Person ON Person.id = Event.setPerson_id',
                 'Action.id AS id, Event.setPerson_id AS person_id, DATE(Event.setDate) as date',
                 $db->CondAnd(
                    '`Event`.`deleted`=0 AND `Action`.`deleted`=0 AND `EventType`.`code` = \'0\' AND `ActionType`.`code` =\'amb\'',
                    $db->CondIn('Event`.`setPerson_id', $aPersonIdList),
                    $db->CondGE('Event`.`setDate', $aBegDate),
                    $db->CondLE('Event`.`setDate', $aEndDate),
                    '(Person.lastAccessibleTimelineDate IS NULL OR Person.lastAccessibleTimelineDate = \'0000-00-00\' OR DATE(Event.setDate)<=Person.lastAccessibleTimelineDate)',
                    '(Person.timelineAccessibleDays IS NULL OR Person.timelineAccessibleDays <= 0 OR DATE(Event.setDate)<=ADDDATE(CURRENT_DATE(), Person.timelineAccessibleDays))'
                    ),
                 'Event.setPerson_id'
                );
}


function _getActionProperty($aActionId, $aActionPropertyName, $aTable)
{
    $db = GetDB();
    $vRecord = $db->Get('ActionProperty'
                         ." LEFT JOIN $aTable ON $aTable.id = ActionProperty.id"
                         .' LEFT JOIN ActionPropertyType ON ActionPropertyType.id = ActionProperty.type_id',
                       "$aTable.value",
                       $db->CondAnd(
                                    $db->CondEqual('ActionProperty`.`action_id', $aActionId),
                                    $db->CondEqual('ActionPropertyType`.`name', $aActionPropertyName))
                       );
    return $vRecord['value'];
}


function _getActionPropertyV($aActionId, $aActionPropertyName, $aTable)
{
    $db = GetDB();
    $vRecords = $db->Select('Action'
                             .' LEFT JOIN ActionType ON ActionType.id = Action.actionType_id'
                             .' LEFT JOIN ActionPropertyType ON ActionPropertyType.actionType_id = ActionType.id'
                             .' LEFT JOIN ActionProperty ON ActionProperty.action_id = Action.id AND ActionProperty.type_id = ActionPropertyType.id'
                             ." LEFT JOIN $aTable ON $aTable.id = ActionProperty.id",
                            "$aTable.index, $aTable.value",
                             $db->CondAnd(
                                 $db->CondEqual('Action`.`id', $aActionId),
                                 'ActionPropertyType.deleted = 0',
                                 $db->CondEqual('ActionPropertyType`.`name', $aActionPropertyName),
                                 'ActionProperty.id IS NOT NULL'
                                         ),
                            "$aTable.id, $aTable.index"
                           );
    $vResult = array();
    while( $vRecord = $vRecords->Fetch() )
    {
#        $vResult[$vRecord['index']] = $vRecord['value'];
         $vResult[] = $vRecord['value'];
    }
    return $vResult;
}


function _setActionPropertyVX($aActionId, $aActionPropertyName, $aTable, $aIndex, $aValue)
{
    $db = GetDB();
    $vActionTypeId = $db->Translate('Action', 'id', $aActionId, 'actionType_id');    
    $vPropertyRecord = $db->Get('ActionProperty'
                                .' LEFT JOIN ActionPropertyType ON ActionPropertyType.id = ActionProperty.type_id',
                               'ActionProperty.id',
                                $db->CondAnd(
                                    $db->CondEqual('ActionProperty`.`action_id', $aActionId),
                                    $db->CondEqual('ActionPropertyType`.`actionType_id', $vActionTypeId),
                                    $db->CondEqual('ActionPropertyType`.`name', $aActionPropertyName))
                              );
    if ( $vPropertyRecord === false )
    {
        $vActionPropertyTypeRecord = $db->Get('ActionPropertyType'
                                              .' LEFT JOIN ActionType ON ActionType.id = ActionPropertyType.actionType_id',
                                              'ActionPropertyType.id',
                                              $db->CondAnd(
                                                  $db->CondEqual('ActionType`.`id', $vActionTypeId),
                                                  $db->CondEqual('ActionPropertyType`.`name', $aActionPropertyName),
                                                  'ActionPropertyType.deleted = 0')
                                             );
        if ( $vActionPropertyTypeRecord )
            $vActionPropertyTypeId = $vActionPropertyTypeRecord['id'];
        else
            throw new Exception("Action with id=$aActionId does not have property with name $aActionPropertyName");
        $vPropertyRecord = array('createDatetime' => date('Y-m-d H:i:s'),
                                 'createPerson_id'=> _getCurrentPersonId(),
                                 'action_id'      => $aActionId,
                                 'type_id'        => $vActionPropertyTypeId,
                                );
        $vActionPropertyId = $db->Insert('ActionProperty', $vPropertyRecord);
    }
    else
    {
        $vActionPropertyId = $vPropertyRecord['id'];
    }

    $vPropertyValueRecord = $db->Get($aTable, '*', "`id`=$vActionPropertyId AND `index`=$aIndex");
    if ( $vPropertyValueRecord )
    {
        $vPropertyValueRecord = array('value' => $aValue);
        $db->Update($aTable,
                    $db->CondAnd( $db->CondEqual('id', $vActionPropertyId), $db->CondEqual('index', $aIndex)),
                    $vPropertyValueRecord
                   );
    }
    else
    {
        $vPropertyValueRecord = array('id'    => $vActionPropertyId,
                                      'index' => $aIndex,
                                      'value' => $aValue
                                    );
        $db->Insert($aTable, $vPropertyValueRecord);
    }
}


function _getReasonOfAbsence($aPersonId, $aDate)
{
    $vTimeLineActionId = _getTimeLineActionId($aPersonId, $aDate);
    if ( $vTimeLineActionId )
        return _getActionProperty($vTimeLineActionId, 'reasonOfAbsence', 'ActionProperty_rbReasonOfAbsence');
    else
        return NULL;
}


function _getQuota($aPersonId)
{
    $db = GetDB();
    $externalQuotaId = $db->Translate('rbPrerecordQuotaType', 'code', 'external', 'id');
    $quotaRecord = $db->Get(
        'PersonPrerecordQuota',
        'value',
        $db->CondAnd( $db->CondEqual('person_id', $aPersonId), $db->CondEqual('quotaType_id', $externalQuotaId) )
    );
    return $quotaRecord ? intval($quotaRecord['value']) : 0;
}


function _isQueuedByExternal($aQueueActionId)
{
    $db = GetDB();
    $setPersonId = $db->Translate('Action', 'id', $aQueueActionId, 'setPerson_id');
//     return ($setPersonId === NULL || $setPersonId == _getCurrentPersonId());
    return $setPersonId === NULL;
}


function _getTickets2($aActionId, $aQuota)
{
    $vTimes = _getActionPropertyV($aActionId, 'times', 'ActionProperty_Time');
    $vQueue = _getActionPropertyV($aActionId, 'queue', 'ActionProperty_Action');
    $vTickes = array();
    $vExternalCount = 0;
    $vFreeCount = 0;
    for( $i=0; $i<count($vTimes); $i++ )
    {
        if ( $vTimes[$i] )
        {
            $vQueueActionId = @$vQueue[$i];
            $vFree = $vQueueActionId === NULL;
            if ( $vFree )
                $vFreeCount += 1;
            else
                if ( _isQueuedByExternal($vQueueActionId) )
                    $vExternalCount += 1;
            $vTickes[] = array( 'time'      => $vTimes[$i],
                                'free'      => $vFree,
                                'available' => $vFree);
        }
    }
    $vAvailable = max(0, (int)($aQuota*count($vTickes)*0.01) - $vExternalCount);
    if ($vAvailable < 1)
    {
        for( $i=0; $i<count($vTickes); $i++ )
        {
            $vTickes[$i]['available'] = false;
        }
    }
    return array($vTickes, min($vAvailable, $vFreeCount));
}


function _getAmbInfo2($aActionId, $aQuota=0)
{
    list($vTickets, $vAvailable) = _getTickets2($aActionId, $aQuota);
    $vResult = array( 'begTime'   => _getActionProperty($aActionId, 'begTime', 'ActionProperty_Time'),
                      'endTime'   => _getActionProperty($aActionId, 'endTime', 'ActionProperty_Time'),
                      'office'    => _getActionProperty($aActionId, 'office',  'ActionProperty_String'),
                      'plan'      => _getActionProperty($aActionId, 'plan',    'ActionProperty_Integer'),
                      'tickets'   => $vTickets,
                      'available' => $vAvailable,
                   );
    return $vResult;
}


function _createQueueAction($db, $aClientId, $aPersonId, $aDate, $aTime, $aNote, $aOffice)
{
    $etcQueue = 'queue';
    $atcQueue = 'queue';

    $vDatetime = $aDate . ' ' . $aTime;

    $vEventTypeId  = $db->Translate('EventType',    'code', $etcQueue, 'id');
    $vActionTypeId = $db->Translate('ActionType',   'code', $atcQueue, 'id');
    $vOrgId        = $db->Translate('Organisation', 'infisCode', gCurrentOrganisationInfisCode, 'id');
    $vNow = date('Y-m-d H:i:s');

    $vEventRecord  = array('createDatetime' => $vNow,
                           'createPerson_id'=> _getCurrentPersonId(),
                           'eventType_id' => $vEventTypeId,
                           'org_id'       => $vOrgId,
                           'client_id'    => $aClientId,
                           'setDate'      => $vDatetime,
                           'isPrimary'    => 1
                         );
    $vEventId = $db->Insert('Event', $vEventRecord);
    $vActionRecord = array('createDatetime' => $vNow,
                           'createPerson_id'=> _getCurrentPersonId(),
                           'actionType_id' => $vActionTypeId,
                           'event_id'      => $vEventId,
                           'directionDate' => $vDatetime,
                           'status'        => 1,
                           'person_id'     => $aPersonId,
                           'setPerson_id'  => NULL,
                           'note'          => ForceStr($aNote),
                           'office'        => $aOffice,
                         );
    $vActionId = $db->Insert('Action', $vActionRecord);
    return $vActionId;
}


function _selectQueueAction($db, $aClientId, $aQueueActionId=NULL)
{
    $vRecords = $db->Select( 'Action AS QueueAction '
                            .'LEFT JOIN ActionType AS QueueActionType ON QueueActionType.id = QueueAction.actionType_id '
                            .'LEFT JOIN Event      AS QueueEvent      ON QueueEvent.id = QueueAction.event_id '
                            .'LEFT JOIN EventType  AS QueueEventType  ON QueueEventType.id = QueueEvent.eventType_id '
                            .'LEFT JOIN ActionProperty_Action         ON ActionProperty_Action.value = QueueAction.id '
                            .'LEFT JOIN ActionProperty                ON ActionProperty.id = ActionProperty_Action.id '
                            .'LEFT JOIN Action                        ON Action.id = ActionProperty.action_id '
                            .'LEFT JOIN ActionType                    ON ActionType.id = Action.actionType_id '
                            .'LEFT JOIN ActionPropertyType AS APTTime ON APTTime.actionType_id = ActionType.id AND APTTime.name=\'times\' '
                            .'LEFT JOIN ActionProperty AS APTime      ON APTime.type_id = APTTime.id AND APTime.action_id = Action.id '
                            .'LEFT JOIN ActionProperty_Time           ON ActionProperty_Time.id = APTime.id AND ActionProperty_Time.`index` = ActionProperty_Action.`index` '
                            .'LEFT JOIN Event                         ON Event.id = Action.event_id '
                            .'LEFT JOIN EventType                     ON EventType.id = Event.eventType_id ',
                            'QueueEvent.client_id AS `clientId`, '
                            .'DATE(QueueEvent.setDate) AS `date`, '
                            .'ActionProperty_Time.value AS `time`, '
                            .'ActionProperty_Action.`index` AS `index`, '
                            .'QueueAction.person_id AS `personId`, '
                            .'QueueAction.note AS `note`, '
                            .'QueueAction.id AS `queueActionId`, '
                            .'QueueAction.createPerson_id AS `enqueuePersonId`, '
                            .'DATE(QueueAction.createDatetime) AS `enqueueDate`, '
                            .'TIME(QueueAction.createDatetime) AS `enqueueTime`, '
                            .'ActionProperty.action_id AS `ambActionId`',
                           $db->CondAnd(
                              'QueueAction.deleted = 0',
                              'QueueActionType.code = \'queue\'',
                              'QueueEvent.deleted = 0',
                              'QueueEventType.code = \'queue\'',
                              'Action.deleted = 0',
                              'ActionType.code = \'amb\'',
                              'Event.deleted = 0',
                              'EventType.code = \'0\'',
                              $db->CondEqual('QueueEvent`.`client_id', $aClientId),
                              $aQueueActionId ? $db->CondEqual('QueueAction`.`id', $aQueueActionId) : '',
#                              $aPersonId ? $db->CondEqual('QueueAction.person_id', $aPersonId) : '',
                              $db->CondGE('QueueEvent`.`setDate', date('Y-m-d'))),
                          'QueueEvent.setDate',
                          queueSelectLimit
                          );
    $vResult = array();
    while( $vRecord = $vRecords->Fetch() )
    {
        $vResult[] = $vRecord;
    }
    return $vResult;
}

function addDays($aDate, $aNumDays)
{
    list($vYear, $vMonth, $vDay) = explode('-', $aDate);
    $vDayNo = GregorianToJD($vMonth, $vDay, $vYear);
    list($vMonth, $vDay, $vYear) = explode('/', JDToGregorian($vDayNo + $aNumDays));
    return sprintf('%04d-%02d-%02d', $vYear, $vMonth, $vDay);
}


function _clientAlreadyInQueue2($db, $aClientId, $aDate, $aPersonId)
{
    if ( !defined('gCheckQueueByDateMethod') )
    {
        define('gCheckQueueByDateMethod', 2);
    }
    switch( gCheckQueueByDateMethod )
    {
            case 0:  return False;
            case 1:  $vDateCond = $db->CondAnd($db->CondGE('QueueEvent`.`setDate', date('Y-m-d H:i:s')),
                                               $db->CondGE('QueueEvent`.`setDate', $aDate),
                                               $db->CondLT('QueueEvent`.`setDate', addDays($aDate, 1))
                                              );
                     break;
            default: $vDateCond = $db->CondGE('QueueEvent`.`setDate', date('Y-m-d H:i:s'));
                     break;
    }

    $vCnt = $db->CountRows('Action AS QueueAction '
                           .'LEFT JOIN ActionType AS QueueActionType ON QueueActionType.id = QueueAction.actionType_id '
                           .'LEFT JOIN Person     AS QueuePerson     ON QueuePerson.id = QueueAction.person_id '
                           .'LEFT JOIN Event      AS QueueEvent      ON QueueEvent.id = QueueAction.event_id '
                           .'LEFT JOIN EventType  AS QueueEventType  ON QueueEventType.id = QueueEvent.eventType_id '
                           .'LEFT JOIN ActionProperty_Action         ON ActionProperty_Action.value = QueueAction.id '
                           .'LEFT JOIN ActionProperty                ON ActionProperty.id = ActionProperty_Action.id '
                           .'LEFT JOIN Action                        ON Action.id = ActionProperty.action_id '
                           .'LEFT JOIN ActionType                    ON ActionType.id = Action.actionType_id '
                           .'LEFT JOIN Event                         ON Event.id = Action.event_id '
                           .'LEFT JOIN EventType                     ON EventType.id = Event.eventType_id '
                           .'LEFT JOIN Person                        ON Person.speciality_id = QueuePerson.speciality_id',
                           'QueueAction.id',
                           $db->CondAnd(
                              'QueueAction.deleted = 0',
                              'QueueActionType.code = \'queue\'',
                              'QueueEvent.deleted = 0',
                              'QueueEventType.code = \'queue\'',
                              'Action.deleted = 0',
                              'ActionType.code = \'amb\'',
                              'Event.deleted = 0',
                              'EventType.code = \'0\'',
                              $db->CondEqual('QueueEvent`.`client_id', $aClientId),
                              $db->CondEqual('Person`.`id', $aPersonId),
                              $vDateCond)
                          );
    return $vCnt > 0;
}


function _recordApplicable($aRecord, $aClientSex, $aClientAge)
{
    if ( $aRecord !== false )
    {
        $vSex = $aRecord['sex'];
        if ( $vSex && $vSex != $aClientSex )
            return false;

        $vAgeSelector = new TAgeSelector($aRecord['age']);
        return $vAgeSelector->CheckAgeArray($aClientAge);
    }
    return true;
}


function _orgStructureApplicable($db, $aOrgStructureId, $aClientSex, $aClientAge)
{
    $vOrgId = NULL;
    $vOrgStructureId = $aOrgStructureId;
    while ( $vOrgStructureId )
    {
        $vOrgStructureRecord = $db->Get('`OrgStructure` LEFT JOIN `rbNet` ON `rbNet`.`id` = `OrgStructure`.`net_id`',
                                        '`rbNet`.`sex`, `rbNet`.`age`, `OrgStructure`.`net_id`, `OrgStructure`.`parent_id`, `OrgStructure`.`organisation_id`',
                                        $db->CondEqual('OrgStructure`.`id', $vOrgStructureId));
        if ( $vOrgStructureRecord['net_id'] )
            return _recordApplicable($vOrgStructureRecord, $aClientSex, $aClientAge);
        $vOrgStructureId = $vOrgStructureRecord['parent_id'];
        $vOrgId = $vOrgStructureRecord['organisation_id'];
    }
    $vOrganisationRecord = $db->Get('`Organisation` LEFT JOIN `rbNet` ON `rbNet`.`id` = `Organisation`.`net_id`',
                                    '`rbNet`.`sex`, `rbNet`.`age`',
                                    $db->CondEqual('Organisation`.`id', $vOrgId));
    return _recordApplicable($vOrganisationRecord, $aClientSex, $aClientAge);
}


function _applicable($db, $aPersonId, $aDate, $aClientRecord)
{
    $vClientSex = $aClientRecord['sex'];
    $vClientAge = GetAgeArray($aClientRecord['birthDate'], $aDate);

    $vPersonRecord = $db->Get('Person LEFT JOIN rbSpeciality ON rbSpeciality.id = Person.speciality_id',
                              'rbSpeciality.sex, rbSpeciality.age, Person.orgStructure_id',
                              $db->CondEqual('Person`.`id', $aPersonId));
    if ( !_recordApplicable($vPersonRecord, $vClientSex, $vClientAge) )
        return false;

    $vOrgStructureId = $vPersonRecord['orgStructure_id'];
    return _orgStructureApplicable($db, $vOrgStructureId, $vClientSex, $vClientAge);
}


function _enqueue($db, $aClientId, $aPersonId, $aDate, $aTime, $aNote)
{
    $vClientRecord = $db->Get('Client',
                              'sex, birthDate',
                              $db->CondAnd($db->CondEqual('id', $aClientId), $db->CondEqual('deleted', 0)));
    if ( $vClientRecord === false )
         return array('success'=>false, 'message'=>msgWrongPatientId);

    if ( _getClientDeathDate($db, $aClientId) )
         return array('success'=>false, 'message'=>msgPatientMarkedAsDead);

    if ( ! _checkClientAttachExists($db, $aClientId, $aDate) )
         return array('success'=>false, 'message'=>msgPatientNotAttached);

    if ( ! _checkClientAttachSufficient($db, $aClientId, $aPersonId) )
         return array('success'=>false, 'message'=>msgPatientAttachInsufficient);

    if ( ! _checkInsuranceCompanyService($db, $aClientId) )
         return array('success'=>false, 'message'=>msgInsuranceCompanyServiceStopped);

    if ( !_applicable($db, $aPersonId, $aDate, $vClientRecord) )
         return array('success'=>false, 'message'=>msgEnqueueNotApplicable);

    $vActionId = _getAmbActionId($aPersonId, $aDate);
    if ( !$vActionId )
        return array('success'=>false, 'message'=>msgQueueNotFound);

    $vLockId = lock($db, _getCurrentPersonId(), 'Action', $vActionId);
    if ( !$vLockId )
        return array('success'=>false, 'message'=>msgCannotLockQueue);
    try
    {
        $vTimeRecord = $db->Get('ActionProperty'
                                .' LEFT JOIN ActionProperty_Time ON ActionProperty_Time.id = ActionProperty.id'
                                .' LEFT JOIN ActionPropertyType ON ActionPropertyType.id = ActionProperty.type_id',
                                'ActionProperty_Time.index',
                                 $db->CondAnd(
                                    $db->CondEqual('ActionProperty`.`action_id', $vActionId),
                                    $db->CondEqual('ActionPropertyType`.`name',   'times'),
                                    '`ActionProperty_Time`.`value`=TIME('. $db->Decorate($aTime).')')
                               );
        if ( $vTimeRecord === false )
            return array('success'=>false, 'message'=>msgTicketNotFound);
        $vQueueIndex = $vTimeRecord['index'];
        $vQueue = _getActionPropertyV($vActionId, 'queue', 'ActionProperty_Action');

        if ( _clientAlreadyInQueue2($db, $aClientId, $aDate, $aPersonId) )
            return array('success'=>false, 'message'=>msgPatientAlreadyQueued);

        if ( count($vQueue) > $vQueueIndex )
        {
            if ( $vQueue[$vQueueIndex] != NULL )
                return array('success'=>false, 'message'=>msgTicketIsBusy);
        }
        else
        {
            for( $i=count($vQueue); $i<$vQueueIndex; $i++ )
            {
                _setActionPropertyVX($vActionId, 'queue', 'ActionProperty_Action', $i, NULL);
            }
        }

        $vOffice = ForceStr(_getActionProperty($vActionId, 'office', 'ActionProperty_String'));
        $vQueueActionId = _createQueueAction($db, $aClientId, $aPersonId, $aDate, $aTime, $aNote, $vOffice);
        _setActionPropertyVX($vActionId, 'queue', 'ActionProperty_Action', $vQueueIndex, $vQueueActionId);
        releaseLock($db, $vLockId);

        return array('success'=>true, 'message'=>msgOk, 'index'=>$vQueueIndex, 'queueId'=>$vQueueActionId);
    }
    catch (Exception $e)
    {
        releaseLock($db, $vLockId);
        throw $e;
    }
}


function _dequeue($db, $aActionId, $aQueueIndex)
{
    _setActionPropertyVX($aActionId, 'queue', 'ActionProperty_Action', $aQueueIndex, NULL);
}

function _deferredQueue($db, $aClientId, $aPersonId, $aDate, $aTime, $aNote, $aNoteObj)
{    
    $vSpecId   = $db->Translate('rbSpeciality',          'name', $aNoteObj['spec'], 'id');
    $vStatusId = $db->Translate('rbDeferredQueueStatus', 'code', '0',               'id');
    
    $vQueueRecord = array(
        'createDatetime'  => date('Y-m-d H:i:s'),
        'createPerson_id' => $aNoteObj['user'],
        'modifyDatetime'  => date('Y-m-d H:i:s'),
        'modifyPerson_id' => _getCurrentPersonId(),
        'client_id'       => $aClientId,
        'speciality_id'   => $vSpecId,
        'maxDate'         => date('Y-m-d H:i:s', strtotime($aDate.' '.$aTime)),
        'status_id'       => $vStatusId,
        'comment'         => $aNote . "\nТелефон:" . $aNoteObj['phone'] . "\nID:" . $aNoteObj['id'],                             
    );
    
    if ($aPersonId)
        $vQueueRecord['person_id'] = $aPersonId;
    
    $vQueueRecordId = $db->Insert('DeferredQueue', $vQueueRecord);
        
    return array('success'=>true, 'message'=>msgOk, 'index'=>0, 'queueId'=>$vQueueRecordId);
}

/* ====================================================================================== */


function test($aParams)
{
    $vMessage = 'ok';
    try
    {
        $db = GetDB();
    }
    catch (Exception $e)
    {
        $vMessage = $e->getMessage();
    }
    return array('string'=>$vMessage);
}


function getOrganisationInfo($aParams)
{
    $db = GetDB();
    $vRecord = $db->Get('Organisation', 'fullName, shortName, address, infisCode', $db->CondEqual('infisCode', gCurrentOrganisationInfisCode));
    return array(
                 'fullName'  => $vRecord['fullName'],
                 'shortName' => $vRecord['shortName'],
                 'address'   => $vRecord['address'],
                 'infisCode' => $vRecord['infisCode'],
                );
}


function getOrgStructures($aParams)
{
    $vParendId  = @$aParams->parentId;
    $vRecursive = @$aParams->recursive;
    $db = GetDB();
    $vResult = array();
    $vMapIdToParentId = array();
    $vMapIdToNetId = array();
    $vOrgStructureIdList = _getOrgStructureIdList($vParendId, $vRecursive);
    if( $vOrgStructureIdList )
    {
        $vRecords = $db->Select('OrgStructure',
                                'id, parent_id, code, name, address, net_id',
                                $db->CondIn('id', $vOrgStructureIdList),
                                'parent_id, name, id'
                               );
        while( $vRecord = $vRecords->Fetch() )
        {
            $vId = $vRecord['id'];
            $vNetId = $vRecord['net_id'];
            $vParentId = $vRecord['parent_id'];
            $vMapIdToParentId[$vId] = $vParentId;
            if( $vNetId )
                $vMapIdToNetId[$vId] = $vNetId;
            $vResult[] = array( 'id'       => $vId,
                                'parentId' => $vParentId,
                                'code'     => $vRecord['code'],
                                'name'     => $vRecord['name'],
                                'address'  => $vRecord['address'],
                                'sexFilter'=> NULL,
                                'ageFilter'=> NULL
                              );
        }
        foreach( $vResult as $vDescr )
        {
            $vId = $vDescr['id'];
            if( !array_key_exists($vId, $vMapIdToNetId) )
                $vMapIdToNetId[$vId] = _getOrgStructureNetId($db, $vId, $vMapIdToNetId, $vMapIdToParentId);
        }
        $vMapNetIdToFilter = array();
        $vRecords = $db->Select('rbNet',
                                'id, sex, age',
                                $db->CondIn('id', array_filter(array_unique($vMapIdToNetId)))
                               );
        while( $vRecord = $vRecords->Fetch() )
        {
            $vSex   = $vRecord['sex'];
            $vAge   = $vRecord['age'];
            if( $vSex || $vAge )
            {
                $vNetId = $vRecord['id'];
                $vAS = new TAgeSelector($vAge);
                $vMapNetIdToFilter[$vNetId] = array(
                    $vSex ? _formatSex($vSex) : NULL,
                    $vAS->OutForSoap()
                    );
            }
        }
        for( $i=0; $i<count($vResult); $i++ )
        {
            $vId = $vResult[$i]['id'];
            $vNetId = @$vMapIdToNetId[$vId];
            if( $vNetId )
            {
                $vFilter = @$vMapNetIdToFilter[$vNetId];
                if( $vFilter )
                {
                    list($vResult[$i]['sexFilter'], $vResult[$i]['ageFilter']) = $vFilter;
                }
            }
        }
    }

    return $vResult;
}


function getAddresses($aParams)
{
    $vOrgStructureId = @$aParams->orgStructureId;
    $vRecursive      = @$aParams->recursive;

    $db = GetDB();
    if( $vRecursive or !$vOrgStructureId )
    {
        $vOrgStructureIdList = _getOrgStructureIdList($vOrgStructureId, True);
        if( $vOrgStructureId )
            $vOrgStructureIdList[] = $vOrgStructureId;
    }
    else
    {
        $vOrgStructureIdList = array($vOrgStructureId);
    }

    $vResult = array();
    if( $vOrgStructureIdList )
    {
        $vRecords = $db->Select('OrgStructure_Address'
                     .' LEFT JOIN OrgStructure ON OrgStructure.id = OrgStructure_Address.master_id'
                     .' LEFT JOIN AddressHouse ON AddressHouse.id = OrgStructure_Address.house_id',
                     'OrgStructure_Address.master_id, AddressHouse.KLADRCode, AddressHouse.KLADRStreetCode, AddressHouse.number, AddressHouse.corpus, OrgStructure_Address.firstFlat, OrgStructure_Address.lastFlat',
                     $db->CondAnd(
                        $db->CondIn('OrgStructure_Address`.`master_id', $vOrgStructureIdList),
                        $db->CondEqual('AddressHouse`.`deleted', 0),
                        $db->CondEqual('OrgStructure`.`deleted', 0)
                     ),
                     'OrgStructure_Address.master_id, AddressHouse.KLADRCode, AddressHouse.KLADRStreetCode, AddressHouse.number, AddressHouse.corpus, OrgStructure_Address.firstFlat, OrgStructure_Address.lastFlat'
                    );
        while( $vRecord = $vRecords->Fetch() )
        {
            $vResult[] = array( 'orgStructureId' => $vRecord['master_id'],
                                'pointKLADR'     => ForceStr($vRecord['KLADRCode']),
                                'streetKLADR'    => ForceStr($vRecord['KLADRStreetCode']),
                                'number'         => ForceStr($vRecord['number']),
                                'corpus'         => ForceStr($vRecord['corpus']),
                                'firstFlat'      => ForceStr($vRecord['firstFlat']),
                                'lastFlat'       => ForceStr($vRecord['lastFlat']),
                              );
        }
    }

    return $vResult;
}


function findOrgStructureByAddress($aParams)
{
    $vKLADRCode       = @$aParams->pointKLADR;
    $vKLADRStreetCode = @$aParams->streetKLADR;
    $vNumber          = @$aParams->number;
    $vCorpus          = @$aParams->corpus;
    $vFlat            = intval(@$aParams->flat,10);

    $db = GetDB();
    return _findOrgStructureByAddress($db, $vKLADRCode, $vKLADRStreetCode, $vNumber, $vCorpus, $vFlat);
}


function getPersonnel($aParams)
{
    $vOrgStructureId = @$aParams->orgStructureId;
    $vRecursive      = @$aParams->recursive;

    $db = GetDB();
    if( $vRecursive or !$vOrgStructureId )
    {
        $vOrgStructureIdList = _getOrgStructureIdList($vOrgStructureId, True);
        if( $vOrgStructureId )
            $vOrgStructureIdList[] = $vOrgStructureId;
    }
    else
    {
        $vOrgStructureIdList = array($vOrgStructureId);
    }

    $vResult = array();
    if( $vOrgStructureIdList )
    {
        $vRecords = $db->Select('Person '
                     .' LEFT JOIN Organisation ON Person.org_id = Organisation.id'
                     .' LEFT JOIN rbSpeciality ON Person.speciality_id = rbSpeciality.id'
                     .' LEFT JOIN rbPost       ON Person.post_id = rbPost.id',
                     'Person.id, Person.orgStructure_id, Person.SNILS, Person.code, Person.lastName, Person.firstName, Person.patrName, Person.office, rbSpeciality.name AS speciality, rbSpeciality.OKSOCode AS specialityOKSOCode,  rbSpeciality.regionalCode AS specialityRegionalCode, rbSpeciality.federalCode AS specialityFederalCode, rbSpeciality.sex AS sex, rbSpeciality.age AS age, rbPost.name AS post, rbPost.regionalCode AS postRegionalCode, rbPost.federalCode as postFederalCode',
                     $db->CondAnd(
    #                    $db->CondEqual('Organisation`.`infisCode', gCurrentOrganisationInfisCode),
                        $db->CondIn('Person`.`orgStructure_id', $vOrgStructureIdList),
                        $db->CondEqual('Person`.`deleted', 0),
                        $db->CondEqual('Person`.`retired', 0),
                        '`Person`.`speciality_id` IS NOT NULL',
                        '`Person`.`availableForExternal`'
                     ),
                     'Person.lastName, Person.firstName, Person.patrName'
                    );
        while( $vRecord = $vRecords->Fetch() )
        {
            $vAS = new TAgeSelector($vRecord['age']);
            $vResult[] = array( 'id'                     => $vRecord['id'],
                                'code'                   => $vRecord['code'],
                                'orgStructureId'         => $vRecord['orgStructure_id'],
                                'lastName'               => $vRecord['lastName'],
                                'firstName'              => $vRecord['firstName'],
                                'patrName'               => $vRecord['patrName'],
                                'office'                 => $vRecord['office'],
                                'snils'                  => $vRecord['SNILS'],
                                'speciality'             => ForceStr($vRecord['speciality']),
                                'specialityOKSOCode'     => ForceStr($vRecord['specialityOKSOCode']),
                                'specialityRegionalCode' => ForceStr($vRecord['specialityRegionalCode']),
				'specialityFederalCode'  => ForceStr($vRecord['specialityFederalCode']),
                                'post'                   => ForceStr($vRecord['post']),
                                'postRegionalCode'       => ForceStr($vRecord['postRegionalCode']),
                                'postFederalCode'        => ForceStr($vRecord['postFederalCode']),
                                'sexFilter'              => $vRecord['sex'] ? _formatSex($vRecord['sex']) : NULL,
                                'ageFilter'              => $vAS->OutForSoap(),
                              );
        }
    }

    return array('list'=>$vResult);
}


function getTicketsAvailability($aParams)
{
    class TTicketCounter
    {
        function __construct()
        {
            $this->total = 0;
            $this->free  = 0;
            $this->available = 0;
        }

        function countTicket(&$aTicket, $aIndex)
        {
            $this->total += 1;
            if ( $aTicket['free'] )
                $this->free += 1;
            if ( $aTicket['available'] )
                $this->available += 1;
        }

        function work($aTickets)
        {
           array_walk($aTickets, array(&$this, 'countTicket'));
        }
    }

    $vOrgStructureId = @$aParams->orgStructureId;
    $vRecursive      = @$aParams->recursive;
    $vSpecialityNotation = @$aParams->specialityNotation;
    $vSpeciality     = @$aParams->speciality;
    $vPersonId       = @$aParams->personId;
    $vBegDate        = cleanDate($aParams->begDate);
    $vEndDate        = cleanDate($aParams->endDate);

#    $vBegTime = strtotime($vBegDate,0);
#    $vEndTime = strtotime($vEndDate,0);
#    if ( ($vEndTime-$vBegTime) > 2764800 ) /* 32*24*60*60 */
#        throw new Exception('too wide date range');
    $vResult = array();
    $vPersonIdList = _getPersonIdList($vOrgStructureId, $vRecursive, $vSpecialityNotation, $vSpeciality, $vPersonId);

    if ( $vPersonIdList )
    {
        $vActionRecors =_getAmbActionRecords($vPersonIdList, $vBegDate, $vEndDate);

        $vPrevPersonId = false;
        $vQuota = 0;
        while ( $vRecord = $vActionRecors->Fetch() )
        {
            $vCounter = new TTicketCounter();
            $vPersonId = $vRecord['person_id'];
            if ( $vPrevPersonId != $vPersonId )
            {
                $vPrevPersonId = $vPersonId;
                $vQuota = _getQuota($vPersonId);
            }

            if ( !_getReasonOfAbsence($vPersonId, $vRecord['date']) )
            {
		//@@@
                //list($vTickets, $vAvailable) = _getTickets2($vRecord['id'], $vQuota);
/*
    $vResult = array( 'begTime'   => _getActionProperty($aActionId, 'begTime', 'ActionProperty_Time'),
                      'endTime'   => _getActionProperty($aActionId, 'endTime', 'ActionProperty_Time'),
                      'office'    => _getActionProperty($aActionId, 'office',  'ActionProperty_String'),
                      'plan'      => _getActionProperty($aActionId, 'plan',    'ActionProperty_Integer'),
                      'tickets'   => $vTickets,
                      'available' => $vAvailable,
*/

		$vResultAmb = _getAmbInfo2($vRecord['id'], $vQuota);
                $vCounter->work($vResultAmb['tickets']);
                if ( $vCounter->total )
                {
                    $vResult[] = array('personId'  => $vPersonId,
                                       'date'      => $vRecord['date'],
                                       'total'     => $vCounter->total,
                                       'free'      => $vCounter->free,
                                       'available' => $vResultAmb['available'],
				       'begTime'   => $vResultAmb['begTime'],
				       'endTime'   => $vResultAmb['endTime'],
				       'office'    => $vResultAmb['office'],
				       'plan'      => $vResultAmb['plan'],
			);
                }
            }
        }
    }

    return array('list' => $vResult);
}


function getTotalTicketsAvailability($aParams)
{
    class TTotalCounter
    {
        function __construct()
        {
            $this->total = 0;
            $this->free  = 0;
            $this->available = 0;
        }

        function countItem(&$aItem, $aIndex)
        {
            $this->total += $aItem['total'];
            $this->free  += $aItem['free'];
            $this->available  += $aItem['available'];
        }

        function work($aItems)
        {
            array_walk($aItems, array(&$this, 'countItem'));
        }
    }

    $vTmp = getTicketsAvailability($aParams);
    $vItems = $vTmp['list'];
    $vCounter = new TTotalCounter();
    $vCounter->work($vItems);
    return array( 'total' => $vCounter->total,
                  'free'  => $vCounter->free,
                  'available' => $vCounter->available );
}


function getWorkTimeAndStatus($aParams)
{
    $vPersonId = $aParams->personId;
    $vDate     = cleanDate($aParams->date);

    $vAmbActionId = _getAmbActionId($vPersonId, $vDate);
    if ( $vAmbActionId && !_getReasonOfAbsence($vPersonId, $vDate) )
    {
        $vResultAmb = _getAmbInfo2($vAmbActionId, _getQuota($vPersonId));
        $vResult    = array( 'amb' => $vResultAmb );
    }
    else
    {
        $vResult = array();
    }
    return $vResult;
}


function findPatient($aParams)
{
    $vIdList = _findPatients($aParams, 2);
    switch ( count($vIdList) )
    {
        case 0 : return array('success'=>false, 'message'=>msgNoSuchPatient);
        case 1 : return array('success'=>true,  'message'=>msgOk, 'patientId'=>$vIdList[0]);
        default: return array('success'=>false, 'message'=>msgTooManySuchPatients);
    }
}


function registerPatient($aParams)
{
    $vPatient = findPatient($aParams);
    if ($vPatient['success'])
    {
    	return $vPatient;
    }

    $db = GetDB();

    $pPatientRecord = array(
		'createDatetime'      => date('Y-m-d H:i:s'), // datetime NOT NULL COMMENT 'Дата создания записи',
		'createPerson_id'     => _getCurrentPersonId(), // int(11) DEFAULT NULL COMMENT 'Автор записи {Person}',
		'modifyDatetime'      => date('Y-m-d H:i:s'), // datetime NOT NULL COMMENT 'Дата изменения записи',
		'modifyPerson_id'     => _getCurrentPersonId(), // int(11) DEFAULT NULL COMMENT 'Автор изменения записи {Person}',
		'deleted'             => 0, // tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Отметка удаления записи',
		'lastName'            => @$aParams->lastName, // varchar(30) NOT NULL COMMENT 'Фамилия',
		'firstName'           => @$aParams->firstName, // varchar(30) NOT NULL COMMENT 'Имя',
		'patrName'            => @$aParams->patrName, // varchar(30) NOT NULL COMMENT 'Отчество',
		'birthDate'           => @$aParams->birthDate, // date NOT NULL COMMENT 'Дата рождения',
		'birthTime'           => '00:00', // time NOT NULL COMMENT 'Время рождения',
		'sex'                 => @$aParams->sex, // tinyint(4) NOT NULL COMMENT 'Пол (0-неопределено, 1-М, 2-Ж)',
		'SNILS'               => '', // char(11) NOT NULL COMMENT 'СНИЛС',
		'bloodType_id'        => NULL, // int(11) DEFAULT NULL COMMENT 'Группа крови{rbBloodType}',
		'bloodDate'           => NULL, // date DEFAULT NULL COMMENT 'Дата установления группы крови',
		'bloodNotes'          => '', // tinytext NOT NULL COMMENT 'Примечания к группе крови',
		'growth'              => '', // varchar(16) NOT NULL COMMENT 'Рост при рождении',
		'weight'              => '', // varchar(16) NOT NULL COMMENT 'Вес при рождении',
		'embryonalPeriodWeek' => '', // varchar(16) NOT NULL COMMENT 'Неделя эмбрионального периода(в которую рожден пациент)',
		'birthPlace'          => '', // varchar(120) NOT NULL DEFAULT '' COMMENT 'Место рождения',
		'diagNames'           => '', // varchar(64) NOT NULL COMMENT 'Коды диагнозов',
		'notes'               => 'Пациент создан через РФ ЕГИСЗ.', // tinytext NOT NULL COMMENT 'Примечания',
	);

    $vPatientId = $db->Insert('Client', $pPatientRecord);
    return array('success'=>true,  'message'=>msgOk, 'patientId'=>$vPatientId);
}


function findPatients($aParams)
{
    $vIdList = _findPatients($aParams, clientSelectLimit);
    return array('list'=>$vIdList);
}


function getPatientInfo($aParams)
{
    $vClientIdOrList = @$aParams->patientId;
    if ( is_array($vClientIdOrList) )
        $vClientIdList = array_slice($vClientIdOrList, 0, clientSelectLimit);
    else
        $vClientIdList = array($vClientIdOrList);
    $db = GetDB();
    $vRecords = $db->Select('Client', 
                            'id, lastName, firstName, patrName, birthDate, sex', 
                            $db->CondAnd($db->CondIn('id', $vClientIdList), $db->CondEqual('deleted', 0)),
                            '',
                            clientSelectLimit
                           );
    $vList[] = array();
    while( $vRecord = $vRecords->Fetch() )
        {
            $vClientId = $vRecord['id'];
            $vList[$vClientId] = array(
                        'lastName'  => $vRecord['lastName'],
                        'firstName' => $vRecord['firstName'],
                        'patrName'  => $vRecord['patrName'],
                        'birthDate' => $vRecord['birthDate'],
                        'sex'       => _formatSex($vRecord['sex'])
                       );
        }
    $vInfoList = array();
    foreach( $vClientIdList as $vClientId )
    {
        if ( array_key_exists($vClientId, $vList) )
            $vInfoList[] = $vList[$vClientId];
        else
            $vInfoList[] = NULL;
    }
    return array('patientInfo'=>$vInfoList);
}


function getPatientDocument($aParams)
{
    $vClientId = intVal(@$aParams->patientId);
    $db = GetDB();
    $vCond ='`ClientDocument`.`id` = getClientDocumentId(' . $vClientId . ')';
    $vRecords = $db->Select('ClientDocument'
                            .' LEFT JOIN rbDocumentType ON rbDocumentType.id = ClientDocument.documentType_id',
                            'rbDocumentType.name, ClientDocument.serial, ClientDocument.number',
                            $vCond
                           );

    if( $vRecord = $vRecords->Fetch() )
    {
        $vDoc = array('type'    => ForceStr($vRecord['name']),
                      'serial'  => ForceStr($vRecord['serial']),
                      'number'  => ForceStr($vRecord['number']),
                     );
    }
    else
    {
        $vDoc = NULL;
    }
    $vResult = array('document'=>$vDoc);
    return $vResult;
}


function getPatientPolicy($aParams)
{
    $vClientId = intVal(@$aParams->patientId);
    $db = GetDB();
    $vCond ='`ClientPolicy`.`id` = getClientPolicyId(' . $vClientId . ', 1)';
    $vRecords = $db->Select('ClientPolicy'
                            .' LEFT JOIN Organisation ON Organisation.id = ClientPolicy.insurer_id',
                            'Organisation.infisCode, ClientPolicy.serial, ClientPolicy.number',
                            $vCond
                           );

    if( $vRecord = $vRecords->Fetch() )
    {
        $vDoc = array('insurerCode' => ForceStr($vRecord['infisCode']),
                      'serial'      => ForceStr($vRecord['serial']),
                      'number'      => ForceStr($vRecord['number']),
                     );
    }
    else
    {
        $vDoc = NULL;
    }
    $vResult = array('policy'=>$vDoc);
    return $vResult;
}


function getPatientContacts($aParams)
{
    $vClientId = @$aParams->patientId;
    $db = GetDB();
    $vCond = $db->CondAnd($db->CondEqual('Client`.`id', $vClientId),
                          $db->CondEqual('Client`.`deleted', 0),
                          $db->CondEqual('ClientContact`.`deleted', 0)
                         );
    $vRecords = $db->Select('Client'
                            .' LEFT JOIN ClientContact ON ClientContact.client_id = Client.id'
                            .' LEFT JOIN rbContactType ON rbContactType.id = ClientContact.contactType_id',
                            'rbContactType.code, rbContactType.name, ClientContact.contact, ClientContact.notes',
                            $vCond,
                            'ClientContact.id');

    $vContactList = array();
    while( $vRecord = $vRecords->Fetch() )
    {
        $vContactList[] = array('type'    => ForceStr($vRecord['name']),
                                'code'    => ForceStr($vRecord['code']),
                                'contact' => ForceStr($vRecord['contact']),
                                'note'    => ForceStr($vRecord['notes'])
                               );
    }
    $vResult = array('list'=>$vContactList);
    return $vResult;
}


function getPatientAddress($aParams)
{
    function prepAddress($aAddressArray)
    {
        if ( $aAddressArray )
            return array_combine( array('pointKLADR', 'streetKLADR', 'number', 'corpus', 'flat', 'freeInput'),
                                  $aAddressArray
                                );
        else
            return Null;
    }

    $vClientId = @$aParams->patientId;
    $db = GetDB();
    $vClientRecord = $db->Get('Client', 'id', $db->CondAnd($db->CondEqual('id', $vClientId), 'deleted=0'));
    $vLocAddress = Null;
    $vRegAddress = Null;
    if ( $vClientRecord )
    {
        $vRegAddress = prepAddress( _getClientAddress($db, $vClientId, True) );
        $vLocAddress = prepAddress( _getClientAddress($db, $vClientId, False) );
    }

    $vResult = array('reg'=>$vRegAddress, 'loc'=>$vLocAddress);
    return $vResult;
}

function getPatientOrgStructures($aParams)
{
    $vClientId = @$aParams->patientId;
    $db = GetDB();
    $vClientRecord = $db->Get('Client', 'birthDate, sex', $db->CondAnd($db->CondEqual('id', $vClientId), 'deleted=0'));
    $vOrgStructuresList = array();
    if ( $vClientRecord )
    {
        $vClientSex = $vClientRecord['sex'];
        $vClientAge = GetAgeArray($vClientRecord['birthDate']);

        $vList = array();
        foreach( _getOrgStructuresByClientAttach($db, $vClientId) as $vOrgStructureId )
            $vList[$vOrgStructureId] = 1;

        foreach( _getOrgStructuresByClientAddress($db, $vClientId, true) as $vOrgStructureId )
            @$vList[$vOrgStructureId] |= 2;

        foreach( _getOrgStructuresByClientAddress($db, $vClientId, false) as $vOrgStructureId )
            @$vList[$vOrgStructureId] |= 4;

        foreach( $vList as $vOrgStructureId=>$vFlags )
        {
            if ( _orgStructureApplicable($db, $vOrgStructureId, $vClientSex, $vClientAge) )
            {
                $vOrgStructuresList[] = array(  'orgStructureId'  => $vOrgStructureId,
                                                'attached'        => (bool)($vFlags & 1),
                                                'matchRegAddress' => (bool)($vFlags & 2),
                                                'matchLocAddress' => (bool)($vFlags & 4));
            }
        }
    }

    $vResult = array('list'=>$vOrgStructuresList);
    return $vResult;
}


function enqueuePatient($aParams)
{
    $vClientId = @$aParams->patientId;
    $vPersonId = @$aParams->personId;
    $vDate     = cleanDate(@$aParams->date);
    $vTime     = cleanTime(@$aParams->time);
    $vNote     = @$aParams->note;
    $vTmp      = split("\n", $vNote);
    $vNoteObj  = @unserialize($vTmp[1]);
    if ($vTmp[1] && $vNoteObj !== FALSE)
    {
        $vNote = $vTmp[0];
        for($i = 2; $i < count($vTmp); $i++)
        {
            $vNote .= "\n" . $vTmp[$i];
        }
    }

    $db = GetDB();
    $db->Transaction();
    try
    {
        if ($vNoteObj !== FALSE)
        {
            $vResult = _deferredQueue($db, $vClientId, $vPersonId, $vDate, $vTime, $vNote, $vNoteObj);
        }
        else
            $vResult = _enqueue($db, $vClientId, $vPersonId, $vDate, $vTime, $vNote);
#        if ( $vResult['success'] )
#            $vResult = array( 'success' => false, 'message'=>msgQueueingProhibited);
        if ( $vResult['success'] )
            $db->Commit();
        else
            $db->Rollback();
            
        return $vResult;
    }
    catch(Exception $e)
    {
        $db->Rollback();
        throw $e;
    }
}


function getPatientQueue($aParams)
{
    $vClientId = @$aParams->patientId;
    $db = GetDB();
    $vResultList = array();
    foreach ( _selectQueueAction($db, $vClientId) as $vRecord )
    {
        $vResultList[] = array('date'     => $vRecord['date'],
                               'time'     => $vRecord['time'],
                               'index'    => $vRecord['index'],
                               'personId' => $vRecord['personId'],
                               'note'     => $vRecord['note'],
                               'queueId'  => $vRecord['queueActionId'],
                               'enqueuePersonId' => $vRecord['enqueuePersonId'],
                               'enqueueDate' => $vRecord['enqueueDate'],
                               'enqueueTime' => $vRecord['enqueueTime']);
    }
    return array('list' => $vResultList);
}


function dequeuePatient($aParams)
{
    $vClientId = @$aParams->patientId;
    $vQueueId  = @$aParams->queueId;

    $db = GetDB();
    $db->Transaction();
    try
    {
        $vRecords = _selectQueueAction($db, $vClientId, $vQueueId);
        if ( count($vRecords) == 1 )
        {
            $vRecord   = $vRecords[0];
            $vActionId = $vRecord['ambActionId'];
            $vIndex    = $vRecord['index'];
            _dequeue($db, $vActionId, $vIndex);
            $vResult = array( 'success' => true, 'message'=>msgOk);
        }
        else
        {
            $vResult = array( 'success' => false, 'message'=>msgPatientQueueNotFound);
        }
        if ( $vResult['success'] )
            $db->Commit();
        else
            $db->Rollback();
        return $vResult;
    }
    catch(Exception $e)
    {
        $db->Rollback();
        throw $e;
    }
}


function getStatistic($aParams)
{
    $vBegDate = _cleandate(@$aParams->begDate);
    $vEndDate = _cleandate(@$aParams->endDate);
    $vOrgStructureId  = @$aParams->orgStructureId;
    $vRecursive  = @$aParams->recursive;

    $vList = array();
    return array('list'=>$vList);
}


function getEnqueues($aParams)
{
    $query = "select
		Client.firstName as `clientFirstName`,
		Client.patrName as `clientPatrName`,
		Client.lastName as `clientLastName`,
		Client.birthDate as `clientBirthDate`,
		Client.sex as `clientSex`,
		Client.SNILS as `clientSNILS`,
		ClientDocument.serial as `docSerial`,
		ClientDocument.number as `docNumber`,
		ClientPolicy.serial as `polisSerial`,
		ClientPolicy.number as `polisNumber`,
		DATE(QueueEvent.setDate) AS `date`, 
		ActionProperty_Time.value AS `time`, 
		QueueAction.note AS `note`, 
		Person.SNILS as `docSNILS`
      from Action AS QueueAction
        LEFT JOIN ActionType AS QueueActionType ON QueueActionType.id = QueueAction.actionType_id
        LEFT JOIN Event      AS QueueEvent      ON QueueEvent.id = QueueAction.event_id
        LEFT JOIN EventType  AS QueueEventType  ON QueueEventType.id = QueueEvent.eventType_id
        LEFT JOIN ActionProperty_Action         ON ActionProperty_Action.value = QueueAction.id
        LEFT JOIN ActionProperty                ON ActionProperty.id = ActionProperty_Action.id
        LEFT JOIN Action                        ON Action.id = ActionProperty.action_id
        LEFT JOIN ActionType                    ON ActionType.id = Action.actionType_id
        LEFT JOIN ActionPropertyType AS APTTime ON APTTime.actionType_id = ActionType.id AND APTTime.name='times'
        LEFT JOIN ActionProperty AS APTime      ON APTime.type_id = APTTime.id AND APTime.action_id = Action.id
        LEFT JOIN ActionProperty_Time           ON ActionProperty_Time.id = APTime.id AND ActionProperty_Time.`index` = ActionProperty_Action.`index`
        LEFT JOIN Event                         ON Event.id = Action.event_id
        LEFT JOIN EventType                     ON EventType.id = Event.eventType_id
        LEFT JOIN Person                        ON QueueAction.person_id = Person.id
	LEFT JOIN Client                        ON Client.id = QueueEvent.client_id
	LEFT JOIN ClientDocument                ON ClientDocument.client_id = Client.id and ClientDocument.deleted = 0
	LEFT JOIN ClientPolicy                  ON ClientPolicy.client_id = Client.id and ClientPolicy.deleted = 0
      where
        QueueAction.deleted = 0
	AND QueueActionType.code = 'queue'
        AND QueueEvent.deleted = 0
        AND QueueEventType.code = 'queue'
        AND Action.deleted = 0
        AND ActionType.code = 'amb'
        AND Event.deleted = 0
        AND EventType.code = '0'
        AND QueueEvent.setDate between '%s' and '%s'
        AND QueueAction.createDatetime >= '%s %s'
	AND not isnull(ActionProperty_Time.value)
	AND Person.availableForExternal = 1
      group by QueueAction.id;";

    $db = GetDB();

    $vStmt = sprintf($query, $aParams->queueBegDate, $aParams->queueEndDate, $aParams->fromDate, $aParams->fromTime);

    $vRows = new TMyRows($db->Query($vStmt));

    $vResult = array();

    while($vTmpRecord = $vRows->Fetch())
    {
	$vResult []= $vTmpRecord;
    }

    return array('list'=>$vResult);
}


?>

