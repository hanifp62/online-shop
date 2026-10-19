<?php

return [
    // Laravel loads .env through env(); keep request input outside this switch.
    'csp_report_only' => env('ZAFIRA_CSP_REPORT_ONLY') === '1',
];
