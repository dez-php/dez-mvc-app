<?php

return [
    'application' => [
        'staticPath' => '/static/',
        'basePath' => '/',
        'autoload' => [
            'MySite\\Controllers' => '/var/www/site/app/controllers',
        ],
        'controllerNamespace' => 'MySite\\Controllers\\',
        'modelDirectory' => '/var/www/site/app/models',
        'controllerDirectory' => '/var/www/site/app/controllers',
        'viewDirectory' => '/var/www/site/app/templates',
    ],
    'db' => [
        'connection' => [
            'development' => [
                'dsn' => 'mysql:host=localhost;dbname=my-site',
                'user' => 'root',
                'password' => '0000',
            ],
            'production' => [
                'dsn' => 'mysql:host=localhost;dbname=my-site',
                'user' => 'root',
                'password' => '0000',
            ],
        ],
    ],
    'server' => [
        'timezone' => 'Europe/Kiev',
        'displayErrors' => 'On',
        'errorLevel' => '1',
    ],
];