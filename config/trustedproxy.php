<?php

return [

    /*
    | Comma-separated IPs/CIDRs of reverse proxies allowed to set X-Forwarded-*
    | headers. Empty means no proxy is trusted. Use '*' only when the app is
    | reachable solely through a controlled proxy (never directly from internet).
    */
    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
