#!/usr/bin/env php
<?php

error_reporting(-1);
ini_set('display_errors', 1);

define('ROOT', dirname(__DIR__));

include_once ROOT . '/vendor/autoload.php';

$config = require ROOT . '/config/config.php';

echo "\n### Configuration\n### -------------\n\n";

print_r($config);

echo "\n";

$evcc = \Core\EVCC::factory($config);

if ($evcc->fetch()) {
    echo "### Fetched\n### -------\n\nFound the following evcc relevant events:\n\n";

    $events = $evcc->analyse();

    print_r(array_values($events));
}
