<?php
##############################################################################
##
## Блокировки прикладного уровня
##
##############################################################################
##
## Copyright (C) 2006-2011 Chuk&Gek and Vista Software. All rights reserved.
##
##############################################################################


function initLock($db)
{
    static $done = FALSE;

    if ( !$done )
    {
        $db->Query('CALL getAppLock_prepare();');
        $done = TRUE;
    }
    return $done;
}


function tryLock($db, $personId, $tableName, $id, $propertyIndex=0)
{
    $isSuccess = FALSE;
    $appLockId = NULL;
    $lockInfo  = NULL;

    if ( !$personId )
    {
        $personId = 'NULL';
    }

    $db->Query("CALL getAppLock_('$tableName', $id, $propertyIndex, $personId, 'SOAP service[]', @res);");
    $queryResultHandle = $db->Query('SELECT @res AS res;');
    $queryResult = new TMyRows($queryResultHandle);
    $record =& $queryResult->Fetch();
    if ( $record )
    {
        $s = explode(' ', $record['res']);
        if ( count($s)>1 )
        {
            $isSuccess = intval($s[0]);
            $appLockId = intval($s[1]);
        }
    }
    return array( $isSuccess, $appLockId);
}


function lock($db, $personId, $tableName, $id, $propertyIndex = 0)
{
    if ( initLock($db) )
    {
        for( $i=0; $i<300; $i++) # 3 sec.
        {
            list($isSuccess, $appLockId) = tryLock($db, $personId, $tableName, $id, $propertyIndex);
            if ( $isSuccess )
                return $appLockId;
            usleep(10000); # 0.01 sec
        }
        return NULL;
    }
    else
        return -1;
}


function releaseLock($db, $appLockId)
{
    if ( $appLockId>0 )
    {
        $db->Query("CALL ReleaseAppLock($appLockId);");
    }
}
