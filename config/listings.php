<?php

return [

    'min_year' => 1900,

    'max_year' => 1989,

    'images' => [
        'max' => 8,
        'max_upload_kb' => 10240,
        'variants' => [
            'full' => ['width' => 2000, 'height' => 2000],
            'card' => ['width' => 800, 'height' => 600],
            'thumb' => ['width' => 320, 'height' => 240],
        ],
    ],

    'per_page' => [
        'listings' => 12,
        'advanced' => 8,
        'profile' => 8,
        'conversations' => 18,
        'admin' => 12,
        'bookmarks' => 12,
        'seller' => 12,
    ],

    'message' => [
        'min' => 5,
        'max' => 100,
    ],

    'suspension' => [
        'durations' => [
            '7_days' => 7,
            '30_days' => 30,
            'indefinite' => null,
        ],
    ],

];
