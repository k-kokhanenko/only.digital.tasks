<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");
if (!$USER->IsAdmin()) {
    LocalRedirect('/');
}
\Bitrix\Main\Loader::includeModule('iblock');
$row = 1;
$IBLOCK_ID = 42;

$el = new CIBlockElement;
$arProps = [];

/* !!!
Код ниже вообще не используется, я не совсем понимаю зачем он нужен в этом парсере.
$IBLOCK_ID = 42; - это id инфоблока вакансии у меня он свой номер.
Ниже в коде идет получение списка элементов инфоблока с ID = 37, зачем?
*/
$rsElement = CIBlockElement::getList([], ['IBLOCK_ID' => 37],
    false, false, ['ID', 'NAME']);
while ($ob = $rsElement->GetNextElement()) {
    $arFields = $ob->GetFields();
    $key = str_replace(['»', '«', '(', ')'], '', $arFields['NAME']);
    $key = strtolower($key);
    $arKey = explode(' ', $key);
    $key = '';
    foreach ($arKey as $part) {
        if (strlen($part) > 2) {
            $key .= trim($part) . ' ';
        }
    }
    $key = trim($key);
    $arProps['OFFICE'][$key] = $arFields['ID'];
}

/*
CIBlockPropertyEnum - Класс для работы с вариантами значений для свойств типа "список".
 GetList - Возвращает (объект CDBResult) список вариантов значений свойств типа "список" 
 по фильтру arFilter отсортированные в порядке arOrder. Метод статический.

 CDBResult::Fetch() - Делает выборку значений полей в массив.
 Возвращает массив вида Array("поле"=>"значение" [, ...]) и передвигает курсор на следующую запись

 Ниже мы пробегаем по по списку свойств (типа список) данного инфоблока вакансий $IBLOCK_ID
 и формируем массив свойст $arProps вида:
   [SCHEDULE] => Array
        (
            [Полный день] => 75
            [Сменный график] => 74
        )
*/
$rsProp = CIBlockPropertyEnum::GetList(
    ["SORT" => "ASC", "VALUE" => "ASC"],
    ['IBLOCK_ID' => $IBLOCK_ID]
);
while ($arProp = $rsProp->Fetch()) {
    $key = trim($arProp['VALUE']);
    $arProps[$arProp['PROPERTY_CODE']][$key] = $arProp['ID'];
}

/*
 Пробегаем по всем элементам инфоблока и удаляем все элементы из списка
 прочитал в сети, что в битриксе массового удаления нет, чтобы удалить разом все элементы 
 одним вызовом, только поэлементное
 */
$rsElements = CIBlockElement::GetList([], ['IBLOCK_ID' => $IBLOCK_ID], false, false, ['ID']);
while ($element = $rsElements->GetNext()) {
    CIBlockElement::Delete($element['ID']);
}

/*
Файл vacancy.csv я загрузил в папку upload и читаю в коде файл уже из этой папки.
Ниже идет как раз парсер самого файла .csv и формирование элемента с параметрами
для записи в инфоблок Вакансии
*/
if (($handle = fopen("vacancy.csv", "r")) !== false) {
    while (($data = fgetcsv($handle, 1000, ",")) !== false) {
        if ($row == 1) {
            $row++;
            continue;
        }
        $row++; 
        /* !!!
         Это лишняя строка кода.
         Изначально $row = 1, условие 'if ($row == 1)' выполняется один раз, потому что мы внутри инкриментируем 
         эту переменную, но делать это каждый раз в цикле while после этого условия не нужно.
         Суть была пропустить 'continue;' один раз выполнения цикла, т.к. первый раз в $data считывается
         массив с заголовками таблицы (название полей - первая строка в файле) а не с самими данными по вакансии
        */

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
            } elseif ($arProps[$key]) {
                $arSimilar = [];
                foreach ($arProps[$key] as $propKey => $propVal) {
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
            $PROP['SALARY_TYPE'] = $arProps['SALARY_TYPE']['договорная'];
        } else {
            $arSalary = explode(' ', $PROP['SALARY_VALUE']);
            if ($arSalary[0] == 'от' || $arSalary[0] == 'до') {
                $PROP['SALARY_TYPE'] = $arProps['SALARY_TYPE'][$arSalary[0]];
                array_splice($arSalary, 0, 1);
                $PROP['SALARY_VALUE'] = implode(' ', $arSalary);
            } else {
                $PROP['SALARY_TYPE'] = $arProps['SALARY_TYPE']['='];
            }
        }
        $arLoadProductArray = [
            "MODIFIED_BY" => $USER->GetID(),
            "IBLOCK_SECTION_ID" => false,
            "IBLOCK_ID" => $IBLOCK_ID,
            "PROPERTY_VALUES" => $PROP,
            "NAME" => $data[3],
            "ACTIVE" => end($data) ? 'Y' : 'N',
        ];
        /* !!!
         Не понял как определяется флаг активности элемента, что это : end($data) ? 'Y' : 'N' ?
         Вернее сама конструкция мне понятна, мне не понятна логика, т.к. в самом файле нет
         информации о том активна вакансия или нет. 
        */
        if ($PRODUCT_ID = $el->Add($arLoadProductArray)) {
            echo "Добавлен элемент с ID : " . $PRODUCT_ID . "<br>";
        } else {
            echo "Error: " . $el->LAST_ERROR . '<br>';
        }
    }
    fclose($handle);
}


