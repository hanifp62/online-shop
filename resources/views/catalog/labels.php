<?php

// Compatibility for historical copy audits; runtime translations live in lang/.
return array_combine(
    $locales = ['id', 'en', 'zh', 'fr', 'it'],
    array_map(fn ($locale) => require dirname(__DIR__, 3).'/lang/'.$locale.'/zafira.php', $locales),
);
