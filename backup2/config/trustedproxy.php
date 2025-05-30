<?php

use Illuminate\Http\Request;

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Set this to an array of trusted proxy IPs or use '*' to trust all proxies.
    |
    */

    'proxies' => '*',

    /*
    |--------------------------------------------------------------------------
    | Headers that should be used to detect proxy protocol & host.
    |--------------------------------------------------------------------------
    |
    | These headers are typically set by reverse proxies like Nginx or Cloudflare.
    |
    */

    'headers' => 31,
];
