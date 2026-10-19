<?php

return [

    /*
    | Comma-separated IPs/CIDRs of reverse proxies allowed to set X-Forwarded-*
    | headers (env TRUSTED_PROXIES). Always an array, never null: Laravel treats
    | null as "unconfigured" and auto-trusts every caller on Laravel Cloud,
    | *.on-forge.com and *.on-vapor.com hosts. An empty array trusts nobody.
    | Configure explicit proxy IPs/CIDRs; do not use wildcard entries.
    */
    'proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),

];
