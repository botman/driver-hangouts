<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Token
    |--------------------------------------------------------------------------
    |
    | Your Hangouts Chat token used to verify the incoming requests.
    |
    */
    'token' => env('HANGOUTS_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Strip Annotations
    |--------------------------------------------------------------------------
    |
    | If you mention your chatbot using @botname, the driver can automatically
    | strip the text that was sent before your mention.
    | So if you send:
    |
    | > @your-chatbot Hi there
    |
    | The driver will only receive "Hi there".
    |
    */
    'strip_annotations' => false,
];
