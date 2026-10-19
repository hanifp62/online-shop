<?php

require __DIR__.'/guard.php';
amadisGuard();
chdir(dirname(__DIR__, 2).'/public');

return require dirname(__DIR__, 2).'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
