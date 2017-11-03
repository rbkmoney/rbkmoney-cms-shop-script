<?php

return array(

    'shop_id' => array(
        'value'        => 'TEST',
        'title'        => 'Shop ID',
        'description'  => 'Your shop ID from <a target="_blank" href="https://dashboard.rbk.money">RBKmoney</a>',
        'control_type' => waHtmlControl::INPUT,
    ),

    'api_key' => array(
        'value'        => '',
        'title'        => 'API key',
        'description'  => 'Your <a target="_blank" href="https://dashboard.rbk.money/api/key">key</a> to access API',
        'control_type' => waHtmlControl::TEXTAREA,
    ),

    'webhook_key' => array(
        'value'       => '',
        'title'       => 'Webhook key',
        'description' => 'Key signatures received on the Webhook notification',
        'control_type' => waHtmlControl::TEXTAREA,
    ),

    'payform_path_logo' => array(
        'value'       => '',
        'title'       => 'URL logo',
        'description' => 'Your URL logo for payment form',
        'control_type' => waHtmlControl::INPUT,
    ),

    'payform_button_label' => array(
        'value'       => '',
        'title'       => 'Button label',
        'description' => 'Button label for payment form',
        'control_type' => waHtmlControl::INPUT,
    ),

    'payform_description' => array(
        'value'       => '',
        'title'       => 'Description',
        'description' => 'Description for payment form',
        'control_type' => waHtmlControl::INPUT,
    ),

    'payform_company_name' => array(
        'value'       => '',
        'title'       => 'Company name',
        'description' => 'Company name for payment form',
        'control_type' => waHtmlControl::INPUT,
    ),

);
