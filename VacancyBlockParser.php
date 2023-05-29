<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");
if (!$USER->IsAdmin()) {
    LocalRedirect('/');
}

\Bitrix\Main\Loader::includeModule('iblock');


/**
 * VacancyIBlockParser
 */
class VacancyIBlockParser
{
    const IBLOCK_CODE = 'VACANCIES';

    private string $iblockId = '';
    private string $userId = '';
    private $arProps = [];
    private $arElements = [];
    
    /**
     * __construct
     *
     * @param  mixed $userId
     * @return void
     */
    function __construct(string $userId)
    {
        $this->userId = $userId;

        // determine id iblock by its character code
        $res = CIBlock::GetList(
            [], 
            ["CODE"=>self::IBLOCK_CODE], 
            true
        );
        $arElements = $res->Fetch(); 
        $this->iblockId = $arElements['ID'];
    }
    
    /**
     * DetermineProperties
     *
     * @return bool
     */
    private function DetermineProperties() : bool {
        if (!empty($this->iblockId) && empty($this->arProps)) {
            $rsProp = CIBlockPropertyEnum::GetList(
                ["SORT" => "ASC", "VALUE" => "ASC"],
                ['IBLOCK_ID' => $this->iblockId]
            );
            while ($arProp = $rsProp->Fetch()) {
                $key = trim($arProp['VALUE']);
                $this->arProps[$arProp['PROPERTY_CODE']][$key] = $arProp['ID'];
            }
        }

        return !empty($this->arProps);
    }
    
    /**
     * parse
     *
     * @param  string $filePath
     * @param  bool $addElementsAfterParsing
     * @return bool
     */
    public function parse(string $filePath, bool $addElementsAfterParsing = true) : bool 
    {
        if (($handle = fopen($filePath, "r")) !== false) {
            if ($this->DetermineProperties()) {
                $flagFirstRow = true;
                while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                    if ($flagFirstRow) {
                        $flagFirstRow = false;
                        continue;
                    }
            
                    $PROP['ACTIVITY'] = $data[9];
                    $PROP['FIELD'] = $data[11];
                    $PROP['OFFICE'] = $data[1];
                    $PROP['LOCATION'] = $data[2];
                    $PROP['REQUIRE'] = $data[4];
                    $PROP['DUTY'] = $data[5];
                    $PROP['CONDITIONS'] = $data[6];
                    $PROP['EMAIL'] = $data[12];
                    $PROP['DATE'] = date('d.m.Y');
                    $PROP['TYPE'] = $data[8];
                    $PROP['SALARY_TYPE'] = '';
                    $PROP['SALARY_VALUE'] = $data[7];
                    $PROP['SCHEDULE'] = $data[10];
            
                    foreach ($PROP as $key => &$value) {
                        $value = trim($value);
                        $value = str_replace('\n', '', $value);
                        if (stripos($value, '•') !== false) {
                            $value = explode('•', $value);
                            array_splice($value, 0, 1);
                            foreach ($value as &$str) {
                                $str = trim($str);
                            }
                        } elseif ($this->arProps[$key]) {
                            $arSimilar = [];
                            foreach ($this->arProps[$key] as $propKey => $propVal) {
                                if ($key == 'OFFICE') {
                                    $value = strtolower($value);
                                    if ($value == 'центральный офис') {
                                        $value .= 'свеза ' . $data[2];
                                    } elseif ($value == 'лесозаготовка') {
                                        $value = 'свеза ресурс ' . $value;
                                    } elseif ($value == 'свеза тюмень') {
                                        $value = 'свеза тюмени';
                                    }
                                    $arSimilar[similar_text($value, $propKey)] = $propVal;
                                }
                                if (stripos($propKey, $value) !== false) {
                                    $value = $propVal;
                                    break;
                                }
            
                                if (similar_text($propKey, $value) > 50) {
                                    $value = $propVal;
                                }
                            }
                            if ($key == 'OFFICE' && !is_numeric($value)) {
                                ksort($arSimilar);
                                $value = array_pop($arSimilar);
                            }
                        }
                    }
                    if ($PROP['SALARY_VALUE'] == '-') {
                        $PROP['SALARY_VALUE'] = '';
                    } elseif ($PROP['SALARY_VALUE'] == 'по договоренности') {
                        $PROP['SALARY_VALUE'] = '';
                        $PROP['SALARY_TYPE'] = $this->arProps['SALARY_TYPE']['договорная'];
                    } else {
                        $arSalary = explode(' ', $PROP['SALARY_VALUE']);
                        if ($arSalary[0] == 'от' || $arSalary[0] == 'до') {
                            $PROP['SALARY_TYPE'] = $this->arProps['SALARY_TYPE'][$arSalary[0]];
                            array_splice($arSalary, 0, 1);
                            $PROP['SALARY_VALUE'] = implode(' ', $arSalary);
                        } else {
                            $PROP['SALARY_TYPE'] = $this->arProps['SALARY_TYPE']['='];
                        }
                    }
    
                    $this->arElements[] = [
                        "MODIFIED_BY" => $this->userId,
                        "IBLOCK_SECTION_ID" => false,
                        "IBLOCK_ID" => $this->iblockId,
                        "PROPERTY_VALUES" => $PROP,
                        "NAME" => $data[3],
                        "ACTIVE" => end($data) ? 'Y' : 'N',
                    ];             
                }
            }

            fclose($handle); 
            
            if ($addElementsAfterParsing) {
                return $this->addParsedElements();
            } 
            
            return true;
        } 

        return false;
    }
    
    /**
     * addParsedElements
     *
     * @return bool
     */
    public function addParsedElements() : bool
    {
        if (count($this->arElements) > 0) {
            if ($this->clear()) {
                $iblock = new CIBlockElement;
                foreach ($this->arElements as $element) {
                    $iblock->Add($element);
                }

                return true;
            } 
        }
        
        return false;
    }
        
    /**
     * clear
     *
     * @return bool
     */
    private function clear() : bool {
        if (empty($this->iblockId))
            return false;

        $result = true;
        $rsElements = CIBlockElement::GetList(
            [], 
            ['iD' => $this->iblockId], 
            false,
            false, 
            ['ID']
        );

        while ($element = $rsElements->GetNext()) {
            if (!CIBlockElement::Delete($element['ID']) && $result)
                $result = false;
        }
        
        return $result;
    }
}

$vacancy = new VacancyIBlockParser($USER->GetID());
if ($vacancy->parse($_SERVER['DOCUMENT_ROOT'] . '/upload/vacancy.csv')) {
    echo 'Данные успешно загружены и добавлены в инфоблок.';
}
