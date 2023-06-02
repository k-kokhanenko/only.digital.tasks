<?php

use \Bitrix\Iblock\IblockTable;
use \Bitrix\Main\Engine\CurrentUser;

class IBlockElementHandler
{
    const IBLOCK_HANDLER_CODE = 'LOG';

    // инфоблок в который мы добавляем данные
    static $iblockId;
    
    /**
     * createNewIBlock 
     *
     * @param  string $iblockCode - символьный код инфоблока
     * @return bool
     */
    public static function createNewIBlock(string $iblockCode = self::IBLOCK_HANDLER_CODE) : bool 
    {
        $arIblock = IblockTable::getList(
            array('filter' => array('CODE' => $iblockCode))
        )->fetch();

        if (!empty($arIblock)) {
            self::$iblockId = $arIblock['ID'];
         } else {
            // Create new infoblock   
            
            // ...
        }
        
        return true;
    }
    
    /**
     * OnAfterIBlockElementAddHandler
     *
     * @param  mixed $arFields - параметры добавленного элемента инфоблока
     * @return void
     */
    public function OnAfterIBlockElementAddHandler(&$arFields)
    {
        if (self::createNewIBlock()) {
            if ($arFields["IBLOCK_ID"] != self::$iblockId) {
                $arElements = CIBlock::GetList(
                    [], 
                    ["ID" => $arFields["IBLOCK_ID"]], 
                    true
                )->Fetch(); 

                $sectionId = -1;
                $sectionName = $arElements['NAME'].'-'.$arElements['CODE'];
                
                // Проверяем наличие в инфоблоке раздела с символьным кодом вида
                // 'имя инфоблока добавленного элемента-символьный код инфоблока добавленного элемента'
                $iblockSections = CIBlockSection::GetList(
                    [],
                    [
                        'IBLOCK_ID' => self::$iblockId, 
                        'CODE' => $sectionName
                    ], 
                    false, 
                    ['ID'] 
                );
                while ($iblockSection = $iblockSections->GetNext()) {
                    $sectionId = $iblockSection['ID'];     
                }
                
                // Создаем раздел в инфоблоке с символьным кодом вида
                // 'имя инфоблока добавленного элемента-символьный код инфоблока добавленного элемента'
                if ($sectionId == -1) {
                    $iblockSection = new CIBlockSection; 
                    $sectionId = $iblockSection->Add([
                        'ACTIVE' => 'Y',
                        'IBLOCK_ID' => self::$iblockId, 
                        'NAME' => $sectionName, 
                        'CODE' => $sectionName,
                        'SORT' => ''
                    ]); 

                    if ($sectionId <= 0) {
                        die($iblockSection->LAST_ERROR); 
                    }
                }
    
                // Формируем строку вида 'имя раздела'->'имя подраздела'->'...'
                $sectionListText = '';
                if (!empty($arFields['IBLOCK_SECTION'])) {
                    $nav = CIBlockSection::GetNavChain(false, $arFields['IBLOCK_SECTION'][0]);
                    while ($item = $nav->Fetch()) {
                        $sectionListText .= ' -> '.$item['NAME'];
                    }
                }

                // формируем параметры элемента инфоблока согласно задания:
                // 1.4. Именем добавленного элемента должен быть ID логируемого злемента.
                // 1.5. В Начало активности должна записываться дата дата создания/изменения элемента.
                // 1.6. В Описание для анонса должна записываться строка в таком формате: Имя инфоблока -> Имя раздела(от родителя к ребенку)... -> Имя элемента.
                $elementFields = [
                    'MODIFIED_BY' => CurrentUser::get()->getId(),
                    'IBLOCK_SECTION_ID' => $sectionId,
                    'IBLOCK_ID' => self::$iblockId,
                    'NAME' => $arFields['ID'],
                    'ACTIVE' =>'Y',
                    'PREVIEW_TEXT' => $arElements['NAME'].$sectionListText.' -> '.$arFields['NAME'],
                    'ACTIVE_FROM' => date('d.m.Y H:i:s'),
                ];

                // Добавляем новый элемент в инфоблок
                $iblock = new CIBlockElement;
                if (!$iblock->Add($elementFields)) {
                    die($iblock->LAST_ERROR);
                } 
            }                     
        }
    }
    
    /**
     * OnAfterIBlockElementUpdateHandler
     *
     * @param  mixed $arFields - параметры измененного элемента инфоблока
     * @return void
     */
    public function OnAfterIBlockElementUpdateHandler(&$arFields)
    {
        if (self::createNewIBlock()) {
            if ($arFields["IBLOCK_ID"] != self::$iblockId) {
                $findElement = false;
                // Определяем есть ли в инфоблоке элемент с NAME = id изменяемого элемента
                $iblockElements = CIBlockElement::GetList(
                    [], 
                    ['IBLOCK_ID'=>self::$iblockId, 'NAME' => $arFields['ID']], 
                    false,
                    false, 
                    ['ID']
                );

                while($iblockElement = $iblockElements->GetNext())
                {
                    $findElement = true;

                    // Удаляем текущий элемент из инфоблока
                    if (CIBlockElement::Delete( $iblockElement['ID'])) {
                        // Запускаем метод для создания элемента в нашем инфоблоке
                        self::OnAfterIBlockElementAddHandler($arFields);                       
                    } 
                }

                if (!$findElement) {
                    // Запускаем метод для создания элемента в нашем инфоблоке
                    self::OnAfterIBlockElementAddHandler($arFields);
                }
            }
        }
    }
}