<?php

return [
    // Process-only opt-in; keep .env and request input outside this switch.
    'csp_report_only' => getenv('ZAFIRA_CSP_REPORT_ONLY') === '1',
];
