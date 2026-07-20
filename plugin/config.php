<?php
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'mysqli');
define('DB_HOSTNAME', getenv('DB_HOSTNAME') ?: 'localhost');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_DATABASE', getenv('DB_DATABASE') ?: 'clasa8');
define('DB_PREFIX', getenv('DB_PREFIX') ?: 'home_');

