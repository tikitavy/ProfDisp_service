<?php
##############################################################################
##
## Запись в журнал
##
##############################################################################
##
## Copyright (C) 2006-2011 Chuk&Gek and Vista Software. All rights reserved.
##
##############################################################################

function Trace($obj)
{

#    define_syslog_variables();
    //openlog('s11.log', LOG_PID | LOG_PERROR, LOG_LOCAL0);
    if ( is_string($obj) )
        $str = $obj;
    else
    {
        ob_start();
        print_r($obj);
        $str = ob_get_contents();
        ob_end_clean();
    }
    //syslog(LOG_WARNING, date('Y-m-d H:i:s').' '.$str);
    error_log(LOG_WARNING .' '. date('Y-m-d H:i:s').' '.$str, 0);
    //closelog();
}

?>