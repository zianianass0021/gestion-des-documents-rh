<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// Increase execution time for heavy dashboard pages
set_time_limit(120);

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
