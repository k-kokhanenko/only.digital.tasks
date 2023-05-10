<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
	die();

$arResult["isFormTitle"] == "Y"	
	? $formTitle = $arResult["FORM_TITLE"] 
	: $formTitle = 'Связаться';

$arResult["isFormDescription"] == "Y" 
	? $formDesc = $arResult["FORM_DESCRIPTION"] 
	: $formDesc = 'Наши сотрудники помогут выполнить подбор услуги и&nbsp;расчет цены с&nbsp;учетом ваших требований';
?>

<div class="contact-form">
    <div class="contact-form__head">
        <div class="contact-form__head-title"><?=$formTitle?></div>	
		<div class="contact-form__head-text"><?=$formDesc?></div>
    </div>

	<?
	$inputElements = '';
	$textareaElements = '';
	foreach ($arResult["QUESTIONS"] as $FIELD_SID => $arQuestion)
	{
		$arQuestion['REQUIRED'] == 'Y' ? $reqText = '*' : $reqText = '';
		$arQuestion['REQUIRED'] == 'Y' ? $reqFlag = 'required' : $reqFlag = '';
		$fieldId = $arQuestion['STRUCTURE'][0]['FIELD_ID'];

		if ($arQuestion['STRUCTURE'][0]['FIELD_TYPE'] == 'text') {
			$inputElements .= '
				<div class="input contact-form__input">
					<label class="input__label" for="'.$fieldId.'">
						<div class="input__label-text">'.$arQuestion['CAPTION'].$reqText.'</div>
						<input class="input__input" type="text" id="'.$fieldId.'" name="'.$fieldId.'" value="" '.$reqFlag.'>
						<div class="input__notification"></div>
					</label>
				</div>
			';
		} else 
		if ($arQuestion['STRUCTURE'][0]['FIELD_TYPE'] == 'textarea') {
            $textareaElements .= '
				<div class="input">
					<label class="input__label" for="'.$fieldId.'">
						<div class="input__label-text">'.$arQuestion['CAPTION'].$reqText.'</div>
						<textarea class="input__input" type="text" id="'.$fieldId.'" name="'.$fieldId.'" value="" '.$reqFlag.'></textarea>
						<div class="input__notification"></div>
					</label>
				</div>
			';
		}	
	}
	?>

	<?
	if ($arResult["isFormErrors"] == "Y") {
		echo $arResult["FORM_ERRORS_TEXT"]; 
	}	
	?>

	<form class="contact-form__form" action="/" method="POST">
		<div class="contact-form__form-inputs">
			<?=$inputElements;?>
		</div>
		<div class="contact-form__form-message">
			<?=$textareaElements;?>
		</div>
		<div class="contact-form__bottom">
            <div class="contact-form__bottom-policy">Нажимая &laquo;Отправить&raquo;, Вы&nbsp;подтверждаете, что
                ознакомлены, полностью согласны и&nbsp;принимаете условия &laquo;Согласия на&nbsp;обработку персональных
                данных&raquo;.
            </div>
            <button class="form-button contact-form__bottom-button" data-success="Отправлено" data-error="Ошибка отправки" >
                <div class="form-button__title"><?=$arResult["arForm"]["BUTTON"];?></div>
            </button>
        </div>
	</form>
</div>
