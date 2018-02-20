<?php

/**
 * An array that describes the plugin settings
 * array(
 * '%setting_key%'=>array(
 * 'value'=>'default value',
 * 'title'=>'Setting title',
 * 'description'=>'Setting description',
 * 'control_type'=>waHtmlControl::INPUT,
 * ),
 * )
 * @see https://developers.webasyst.ru/cookbook/plugins/payment-plugins/
 */

return array(

    'shop_id' => array(
        'value' => 'TEST',
        'title' => 'Идентификатор магазина',
        'description' => 'Ваш идентификатор магазина из <a target="_blank" href="https://dashboard.rbk.money">RBKmoney</a>',
        'control_type' => waHtmlControl::INPUT,
    ),

    'api_key' => array(
        'value' => '',
        'title' => 'Ключ API',
        'description' => 'Ваш <a target="_blank" href="https://dashboard.rbk.money/api/key">ключ</a> для доступа к API',
        'control_type' => waHtmlControl::TEXTAREA,
    ),

    'webhook_key' => array(
        'value' => '',
        'title' => 'Ключ для уведомлений',
        'description' => 'Ключ для проверки подписи при уведомлениях',
        'control_type' => waHtmlControl::TEXTAREA,
    ),

    'payform_button_css' => array(
        'value' => '',
        'title' => 'Стилизация кнопки',
        'description' => 'Стилизация кнопки открытия формы оплаты',
        'control_type' => waHtmlControl::TEXTAREA,
    ),

    'payform_button_label' => array(
        'value' => '',
        'title' => 'Значение кнопки',
        'description' => 'Значение кнопки для платежной формы',
        'control_type' => waHtmlControl::INPUT,
    ),

    'payform_label' => array(
        'value' => '',
        'title' => 'Метка',
        'description' => 'Метка для платежной формы',
        'control_type' => waHtmlControl::INPUT,
    ),

    'payform_description' => array(
        'value' => '',
        'title' => 'Описание',
        'description' => 'Описание для платежной формы',
        'control_type' => waHtmlControl::INPUT,
    ),

    'payform_company_name' => array(
        'value' => '',
        'title' => 'Название компании',
        'description' => 'Название компании для платежной формы',
        'control_type' => waHtmlControl::INPUT,
    ),

);
