#!/usr/bin/env php
<?php

define('ROOT', dirname(__DIR__));

$config = require ROOT . '/config/config.php';

if ($config['debug'] ?? false) {
    error_reporting(-1);
    ini_set('display_errors', 1);
}

// --------------------------------------------------------------------------

include_once ROOT . '/vendor/autoload.php';

$evcc = new \Core\EVCC($config);

echo date('- d.m.Y H:i'), "\n";

if ($evcc->fetch()) {
    echo implode("\n", $evcc->plan());
}
