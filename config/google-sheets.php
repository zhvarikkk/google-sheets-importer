<?php

return [
    'credentials' => env(
        'GOOGLE_APPLICATION_CREDENTIALS'
    ),

    'scopes' => [
        'https://www.googleapis.com/auth/spreadsheets.readonly',
    ],
];