<?php

require_once( $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/autoload.php');

AddEventHandler("iblock", "OnAfterIBlockElementAdd", Array("IBlockElementHandler", "OnAfterIBlockElementAddHandler"));
AddEventHandler("iblock", "OnAfterIBlockElementUpdate", Array("IBlockElementHandler", "OnAfterIBlockElementUpdateHandler"));


function myAgent() {
    $iblockCode = 'LOG';

    if (\Bitrix\Main\Loader::includeModule('iblock')) {
        // Определяем id инфоблока 'LOG'
        $arIblock = \Bitrix\Iblock\IblockTable::getList(
            array('filter' => array('CODE' => $iblockCode))
        )->fetch();

        if (!empty($arIblock)) {
            $iblockElements = CIBlockElement::GetList(
                ['ACTIVE_FROM' => 'DESC'], 
                ['IBLOCK_ID'=>$arIblock['ID']], 
                false,
                false, 
                ['ID']
            );

            $count = 0;
            while ($iblockElement = $iblockElements->GetNext())
            {
                $count++;

                if ($count <= 10) {
                    continue;
                }

                // Удаляем элемент инфоблока
                CIBlockElement::Delete($iblockElement['ID']);
            }
        }
    }

    return 'myAgent();';
}
