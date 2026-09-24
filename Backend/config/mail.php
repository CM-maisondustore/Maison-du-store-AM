<?php
return [
    'default' => env('MAIL_MAILER', 'log'),
    'mailers' => [
        'smtp' => ['transport' => 'smtp', 'scheme' => 'smtps', 'host' => env('MAIL_HOST', 'smtp.hostinger.com'), 'port' => (int) env('MAIL_PORT', 465), 'username' => env('MAIL_USERNAME', 'contact@maisondustoream.com'), 'password' => env('MAIL_PASSWORD'), 'timeout' => 20],
        'log' => ['transport' => 'log'],
        'array' => ['transport' => 'array'],
    ],
    'from' => ['address' => 'contact@maisondustoream.com', 'name' => 'Maison du Store AM'],
];
