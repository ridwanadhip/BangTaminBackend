<?php
return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
        ],

        // Monolog settings
        'logger' => [
            'name' => 'slim-app',
            'path' => __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],

        // Line bot settings
        'bot' => [
            'channelToken' => 'ZlQwWR2QG/L1/wrgNHaraveorb0QXO4k3fQBlu7Ru2xneospwifwunYTfR/b5DLOq74cGCtxfkAx1caIu85XR5b0IuS9/hiSDBYxx+CQiBepYM8GhxQaPcY8iPjpJ7Kj8X4HRxNdzXAASgyMy6SY3QdB04t89/1O/w1cDnyilFU=',
            'channelSecret' => '51a069ea919e28cd28cba5967ded15a9',
        ],
    ],
];
