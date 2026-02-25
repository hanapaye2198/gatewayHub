<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo API Key
    |--------------------------------------------------------------------------
    |
    | API key of a test merchant used for the demo checkout page. This key is
    | exposed to the browser—use only a dedicated test/demo merchant key.
    | Set to null or empty to disable the demo checkout.
    |
    */

    'api_key' => env('DEMO_API_KEY', null),

];
