<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};

//ini_set('memory_limit', $_SERVER['PHP_MEMORY_LIMIT'] ?? '128M');

ini_set('memory_limit','128000M');
