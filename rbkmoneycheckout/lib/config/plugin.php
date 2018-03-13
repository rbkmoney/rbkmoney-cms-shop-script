<?php
/**
 * Payment plugin general description
 */
return array(
    'name'        => 'RBKmoney',
    'description' => 'Payment system <a href="http://rbkmoney.com/">RBKmoney</a>',

    # plugin icon
    'icon'        => 'img/rbkmoney16.png',

    # default payment gateway logo
    'logo'        => 'img/rbkmoney.png',
    # plugin vendor ID (for 3rd parties vendors it's a number)
    'vendor'      => '1094150',

    # plugin version
    'version'     => '1.0.4',
    'locale'      => array('ru_RU', ),
    'type'        => waPayment::TYPE_ONLINE,
);
