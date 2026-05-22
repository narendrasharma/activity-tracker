<?php

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'activity_tracker');
define('DB_USER', getenv('DB_USER') ?: 'laravel');
define('DB_PASS', getenv('DB_PASS') ?: 'demo1234');
define('DB_CHARSET', 'utf8mb4');
