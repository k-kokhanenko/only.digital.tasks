<?php

require_once( $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/autoload.php');

AddEventHandler("iblock", "OnAfterIBlockElementAdd", Array("IBlockElementHandler", "OnAfterIBlockElementAddHandler"));
AddEventHandler("iblock", "OnAfterIBlockElementUpdate", Array("IBlockElementHandler", "OnAfterIBlockElementUpdateHandler"));



function myAgent() {
    // интервал сейчас стоит в админ панели 300 - это 5 минут
    // по заданию раз в час надо установить 3600 секунд!!!!
    file_put_contents( $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/myagent.log', 'запуск агента - '.date('Y-m-d H:i:s').PHP_EOL, FILE_APPEND);

    return 'myAgent();';
}
