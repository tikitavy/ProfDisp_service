<?php
##############################################################################
##
## Общие функции для SOAP
##
##############################################################################
##
## Copyright (C) 2006-2011 Chuk&Gek and Vista Software. All rights reserved.
##
##############################################################################

function cleanDate($d)
# Некоторые клиенты шлют дату с указанием часового пояса.
# Для нашего случая это излишне (да и какой часовой пояс у даты?)
# Эта функция удаляет часовой пояс из даты
{
    $v = preg_replace('/Z.*$/', '', $d);
    return preg_replace('/[^-0-9]/', '', $v);
}


function cleanTime($t)
# Некоторые клиенты шлют время с указанием часового пояса.
# Для нашего случая это излишне (да и какой часовой пояс у времени?)
# Эта функция удаляет часовой пояс из времени
{
    $v = preg_replace('/[Z+-].*/', '', $t);
    return preg_replace('/[^.:0-9]/', '', $v);
}


function throwAsSoapFault($exception)
{
#    throw new SoapFault('Server', strval($exception));
    throw new SoapFault('Server', $exception->__toString());
}


function soapPreWork($server, $title, $pageColor="#F0F0F0", $emphasisColor="#505050")
{
    if ( $_SERVER['REQUEST_METHOD'] == 'GET' )
    {
        $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'])  ? 'https:' : 'http:';
        $url = $proto.'//'.$_SERVER['HTTP_HOST'];
        if ( !(    ($proto = 'http:' && $_SERVER['SERVER_PORT'] == 80)
        	|| ($proto = 'http:' && $_SERVER['SERVER_PORT'] == 8080)
                || ($proto = 'https:' && $_SERVER['SERVER_PORT'] == 443)
              )
           )
        $url .= ':'.$_SERVER['SERVER_PORT'];
        $url .= $_SERVER['SCRIPT_NAME'];

        if ( strcasecmp($_SERVER['QUERY_STRING'], 'wsdl') == 0 )
        {
            ob_start();
            $server->handle();
            $wsdl = ob_get_contents();
            ob_end_clean();
            print str_replace('%URL%', $url, $wsdl);
        }
        else
        {
?>
<html>
    <head>
        <title> <?php echo strip_tags($title); ?></title>
    </head>
    <body bgcolor="<?php echo $pageColor; ?>" font="#000000">
        <h1 style="color:<?php echo $pageColor; ?>; background-color:<?php echo $emphasisColor; ?>;">&nbsp;Hello, this is <?php echo $title; ?></h1>
        <hr size="1" noshade color="<?php echo $emphasisColor; ?>">
        <p>You can obtain WSDL from <a href="<?php echo $url ?>?WSDL"><?php echo $url ?>?WSDL</a></p>
        <p>If you have questions, please contact at <a href="http://www.vistamed.ru">http://www.vistamed.ru</a></p>
        <hr size="1" noshade color="<?php echo $emphasisColor; ?>">
    </body>
</html>
<?php
        }
        return false;
    }
    return true;
}
?>
