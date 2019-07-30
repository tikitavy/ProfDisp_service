<?php
##############################################################################
##
## Библиотека для работы с MySQL
##
##############################################################################
##
## Copyright (C) 2006-2011 Chuk&Gek and Vista Software. All rights reserved.
##
##############################################################################

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/trace-log.php';
//require_once 'library/trace-dbwinole.php';


class TMyRows
{
    private $_rowsHandle;

    function __construct($rowsHandle)
    {
        $this->_rowsHandle = $rowsHandle;
    }


    function __destruct()
    {
        mysql_free_result($this->_rowsHandle);
        $this->_rowsHandle = NULL;
    }


    function Count()
    {
        return mysql_num_rows($this->_rowsHandle);
    }

    function& Fetch()
    {
        //$result =& mysql_fetch_array($this->_rowsHandle);
        //return $result;
        return mysql_fetch_array($this->_rowsHandle);
    }
}


class TMyDB
{
    /* private */
    private $_handle;
    private $_trace;
    private $_traceErr;
    private $_errOp;
    private $_errNo;
    private $_errMgs;
    private $_raiseOnError;

    function __construct($raiseOnError=TRUE)
    {
        $this->_handle = mysql_connect(gDBHost, gDBUser, gDBPassword)
                  or die('Could not connect: ' . mysql_error());

        #mysql_query('SET NAMES UTF8');
        #mysql_query('SET CHARACTER SET UTF8');
        
        mysql_query('SET NAMES \'utf8\' COLLATE \'utf8_general_ci\';');
        # это эквивалентно
        # SET character_set_client=utf8;
        # SET character_set_connection=utf8;
        # SET character_set_results=utf8;
        mysql_query('SET SQL_AUTO_IS_NULL=0;');
        mysql_query('SET SQL_MODE=\'\';');

        mysql_select_db(gDBName) or die('Could not select database');

        $this->_trace    = gDBDefaultTrace;
        $this->_traceErr = gDBDefaultTraceErr;
        $this->_raiseOnError = $raiseOnError;
    }


    function __destruct()
    {
      mysql_close($this->_handle);
      $this->_handle = NULL;
    }


    function SetTrace($val)
    {
        $this->_trace = $val;
    }


    function SetTraceErr($val)
    {
        $this->_traceErr = $val;
    }


    function Trace($query)
    {
        if ( $this->_trace)
        {
            Trace($query);
        }
    }


    function TraceErr($query)
    {
        if ( $this->_traceErr)
        {
            Trace($query);
        }
    }



    function ClearError()
    {
        $this->_errOp  = '';
        $this->_errNo  = '';
        $this->_errMgs = '';
    }


    function SetError($op)
    {
        $this->_errOp  = $op;
        $this->_errNo  = mysql_errno();
        $this->_errMgs = mysql_error();
        $this->TraceErr('Error on '. $this->ErrorMsg());
        if ( $this->_raiseOnError )
            throw new Exception($this->ErrorMsg());
    }


    function IsError()
    {
        return !empty($this->_errNo);
    }


    function ErrorMsg()
    {
        return
            empty($this->_errNo)
            ? ''
            : ( $this->_errOp . ': ' .$this->_errNo.' '.$this->_errMgs);
    }

/* ===== prepare names of fields && values ============================== */

    function Decorate($val)
    {
        if ( is_int($val) || is_float($val) )
            return $val;
        else if ( is_null($val) )
            return 'NULL';
        else
            return '\'' . mysql_escape_string($val) . '\'';
    }


    function ConvertToDateTime($unixTimeStamp)
    {
        return date('Y-m-d H:i:s', $unixTimeStamp);
    }

    function ConvertToDate($unixTimeStamp)
    {
        return date('Y-m-d', $unixTimeStamp);
    }

    function CondEqual($field, $val)
    {
        return '`' . $field . '`=' . $this->Decorate($val);
    }

    function CondLE($field, $val)
    {
        return '`' . $field . '`<=' . $this->Decorate($val);
    }

    function CondLT($field, $val)
    {
        return '`' . $field . '`<' . $this->Decorate($val);
    }

    function CondGE($field, $val)
    {
        return '`' . $field . '`>=' . $this->Decorate($val);
    }

    function CondGT($field, $val)
    {
        return '`' . $field . '`>' . $this->Decorate($val);
    }

    function CondNotEqual($field, $val)
    {
        return '`' . $field . '`!=' . $this->Decorate($val);
    }


    function CondIsNull($field)
    {
        return '`' . $field . '` IS NULL';
    }


    function CondIsNotNull($field)
    {
        return '`' . $field . '` IS NOT NULL';
    }


    function CondLike($field, $val)
    {
        return '`' . $field . '` LIKE ' . $this->Decorate($val . '%');
    }

    function CondLikeBase($field, $val)
    {
        return '`' . $field . '` LIKE ' . $this->Decorate($val);
    }

    function CondInSet($field, $val)
    {
        return 'FIND_IN_SET('. $this->Decorate($val) . ', `'. $field .'`)';
    }

    function CondIn($field, $vals)
    {
        $decoratedVals = array();
        if ( is_array($vals) )
            foreach( $vals as $val )
            {
               $decoratedVals[] = $this->Decorate($val);
            }
        else
            $decoratedVals[] = $this->Decorate($vals);

        return '`' . $field . '` IN (' . implode(', ', $decoratedVals) .')';
    }


    function CondAnd()
    {
        $numArgs = func_num_args();
        $result  = '';
        for( $i=0; $i<$numArgs; $i++)
        {
            $arg = func_get_arg($i);
            if ( $arg != '' )
            {
               $result .= ($result===''?'':' AND ')
                        .  '('. $arg . ')';
            }
        }
        return $result;
    }


    function CondOr()
    {
        $numArgs = func_num_args();
        $result  = '';
        for( $i=0; $i<$numArgs; $i++)
        {
            $arg = func_get_arg($i);
            if ( $arg != '' )
            {
               $result .= ($result===''?'':' OR ')
                        .  '('. $arg . ')';
            }
        }
        return $result;
    }



    function ConvAssocToAssigns(&$dict)
    {
        $result = '';
        foreach( $dict as $field => $val )
        {
            if ( substr($field, strlen($field)-2) == '()' )
            {
                if ( $val === NULL )
                    $val = 'NULL';
                $field = substr($field, 0, strlen($field)-2);
            }
            else
            {
                $val = $this->Decorate($val);
            }

            $result .= ($result=='' ? '' : ', ') . '`' . $field .'`='. $val;
        }
        return $result;
    }

/* ===== base queries ================================================== */

    function Query($query)
    {
        $this->Trace($query);
        $result = mysql_query($query, $this->_handle);
        if ( $result === FALSE )
        {
            $operator = trim($query);
            $spacePos = strrpos($operator, ' ');
            if ( $spacePos !== FALSE )
                $operator = substr($operator, 0, $spacePos);
            $this->SetError($operator);
        }
        else
        {
            $this->ClearError();
        }
        return $result;
    }


    function Transaction()
    {
        $this->Query('BEGIN');
    }


    function Rollback()
    {
        $this->Query('ROLLBACK');
    }


    function Commit()
    {
        $this->Query('COMMIT');
    }


    function Insert($table, &$record)
    {
//            if ( empty($record) || is_array($record) && count($record) == 0 )
//                $query = 'INSERT INTO ' . $table . ';';
//            else
        $query = 'INSERT INTO ' . $table . ' SET ' . $this->ConvAssocToAssigns($record) . ';';
        $result = $this->Query($query);
        if ( $result !== FALSE  )
            return mysql_insert_id($this->_handle);
        else
            return $result;
    }

    function InsertBySQL($query)
    {
        $result = $this->Query($query);
        if ( $result !== FALSE  )
            return mysql_insert_id($this->_handle);
        else
            return $result;
    }


    function Update($table, $where='', &$record)
    {
        if ( is_array($record) )
        {
            $query = 'UPDATE ' . $table . ' SET ' .  $this->ConvAssocToAssigns($record) .
                      ($where==''?'': (' WHERE '. $where )) .
                        ';';
            return $this->Query($query);
        }
        else
            return FALSE;
    }


    function Delete($table, $where)
    {
        $query = 'DELETE FROM ' . $table . ($where==''?'':' WHERE ' . $where) . ';';
        return $this->Query($query);
    }


    function& Select($table, $fields='*', $where='', $order='', $limit='')
    {

        $query = 'SELECT ' .
            ($fields  == ''? '*' : $fields) .
            ' FROM ' . $table .
            ($where == '' ? ''  : (' WHERE ' . $where)) .
            ($order == '' ? ''  : (' ORDER BY ' . $order)) .
            ($limit == '' ? ''  : (' LIMIT ' . $limit)) .
            ';';
        $tmp = $this->Query($query);
        $result = new TMyRows($tmp);
        return $result;
    }


    function CountRows($table, $fields='*', $where='')
    {
        $query  = 'SELECT COUNT('.$fields.') FROM '.$table.($where==''? '':' WHERE '.$where);
        $result = $this->Query($query);
        return mysql_result($result, 0);
    }


    function& SelectList($table, $fields='*', $where='', $order='', $limit='')
    {
        $result = array();
        $rows   =& $this->Select($table, $fields, $where, $order, $limit);
        while( $record =& $rows->Fetch() )
        {
            $result[] = $record;
        }
        return $result;
    }


    function& Get($table, $fields='*', $where='', $order='')
    {
        $rows   =& $this->Select($table, $fields, $where, $order, 1);
        $result =& $rows->Fetch();
        return $result;
    }


    function& Translate($table, $srcCol, $key, $destCol)
    {
        $record = $this->Get($table, $destCol, $this->CondEqual($srcCol, $key));
        return $record[$destCol];
    }



    /* ===== less base queries: assume that record have id field =========== */

    function UpdateEx($table, $idField, $idValue, &$record)
    {
        return $this->Update($table, $this->CondEqual($idField, $idValue), $record);
    }


    function InsertOrUpdateEx($table, $idField, &$record)
    {
    $id = @$record[$idField];
        if ( empty($id) )
            return $this->Insert($table, $record);
        else
    {
    if ( $this->Update($table, $this->CondEqual($idField, $id), $record) )
       return $id;
    else
       return FALSE;
    }
    }


    function DeleteEx($table, $idField, $idValue)
    {
        $where = $this->CondEqual($idField, $idValue);
        return $this->Delete($table, $where);
    }

/*
function& SelectListEx($table, $fields='*', $idField, $idValue)
{
  if ( is_array($idValue) )
    $where = $this->CondOneOf($idField, $idValue);
  else
    $where = $this->CondEqual($idField, $idValue);

  $result = array();
  $rows   =& $this->Select($table, $field, $where);
  while( $record =& $rows->Fetch() )
  {
    $result[] = $record;
  }
  $rows->Free();
  return $result;
}
*/

    function& GetEx($table, $idField, $idValue, $fields='*')
    {
        return $this->Get($table, $fields, $this->CondEqual($idField, $idValue));
    }


    function& SelectIDsEx($table, $idField, $where='', $order='', $limit='')
    {
        $result = array();
        $rows = $this->Select($table, $idField, $where, $order, $limit);
        while ( $row =& $rows->Fetch() )
        {
            $result[] = $row[0];
        }
        return $result;
    }


    function& GetRBList($table, $keyField='id', $valField='name', $addNull=FALSE, $where='', $order='')
    {
        if ( empty($order) )
        {
           $order = '`'.$valField.'`, `'.$keyField.'`';
        }

        $rows = $this->Select($table,
                        '`'.$keyField . '`, `'. $valField.'`',
                        $where,
                        $order);
        $result = array();
        $count  = 0;

        if ( $addNull )
        {
          $result[ '' ] = '';
        }

        while ( $row =& $rows->Fetch() )
        {
           $result[ $row[$keyField] ] = $row[$valField];
        }
        return $result;
    }


/* ===== less base queries: assume that record id field is id ========== */

    function UpdateById($table, $idValue, &$record)
    {
        return $this->UpdateEx($table, 'id', $idValue, $record);
    }


    function InsertOrUpdateById($table, &$record)
    {
        return $this->InsertOrUpdateEx($table, 'id', $record);
    }


    function DeleteById($table, $idValue)
    {
        return $this->DeleteEx($table, 'id', $idValue);
    }


    function& GetById($table, $idValue, $fields='*')
    {
        return $this->GetEx($table, 'id', $idValue, $fields);
    }


    function& SelectIDsId($table, $where='', $order='')
    {
        return $this->SelectIDsEx($table, 'id', $where, $order);
    }
}


function& GetDB()
{
    static $gTMyDB;

    if ( !isset($gTMyDB) )
        $gTMyDB = new TMyDB();

    return $gTMyDB;
}

# =====================================================================



function NullIfEmpty($value)
{
    return ( $value === 0 || $value === '' ) ? NULL : $value;
}

function ForceStr($value)
{
    return ( $value === NULL ) ? '' : $value;
}

function ForceNull($value)
{
    return ( $value === '' ) ? NULL : $value;
}

function ForceBoolean($value)
{
    return ( $value == NULL || $value == '' || $value == '0' || $value === '0000-00-00' ) ? FALSE : TRUE;
}

?>
