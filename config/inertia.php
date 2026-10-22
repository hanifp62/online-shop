<?php

return [
    // During migration Blade supplies the first HTML; no unprovisioned Node service is contacted.
    'ssr' => ['enabled' => false],
    'history' => ['encrypt' => true],
    'store_previous_url' => true,
    // Avoid persisting request/props traces containing account data, including on local fixtures.
    'devtools' => ['enabled' => false],
];
