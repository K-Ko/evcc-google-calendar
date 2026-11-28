<?php

return  [
    'debug' => true,

    // Frontend language (en|de); default "en"
    // 'language' => 'de',


    'cache-dir' => realpath(__DIR__ . '/../cache'),  // string, fallback is /tmp

    // Uncomment and maintain for frontend basic auth
    // 'frontend' => [
    //     'user' => '...',  // string
    //     // php -r 'echo md5("password");'
    //     'hash' => '...'   // string
    // ],

    'calendar' => [
        'url' => '...',  // REQUIRED, string
        // An event relevant for evcc MUST start its summary whith this
        'summary-start' => '::'        // string
        // 'summary-start' => 'Auto:'  // string
    ],

    // Default
    'evcc' => [
        'host' => 'localhost',  // string
        'port' => 7070,         // int
    ],

    'loadpoint' => [
        // Which charging point should be controlled
        'number' => '...',  // REQUIRED, int
        // Max. phases, automatic switching: 0, set to FALSE to disable controlling
        'phases' => 0  // FALSE|int
    ],

    'vehicle' => [
        // Somethig like "db:2", find it in your evcc UI: Configuration > Vehicles > Edit > (?)
        'db'          => '...', // REQUIRED, string
        'capacity'    => 28.9,  // kWh, float
        'consumption' => 16.6,  // kWh / 100 km, float
        // If an event is configured with a range in km, this SOC should be left after the km driven
        'min-soc' => 20  // percent, int
    ],

    'precondition' => [
        // FALSE is relevant for dynamic traiffs
        'continuous' => false,  // bool

        // Switch from late charging "all" to charging "late" if next plan is less "switch" hours from now
        // (run all day with "late all" e.g. for PV charging and switch below 12 h to "late 15 min.")
        // Charge late all > 24 h for max. PV usage
        'switch' => 12,  // Hours, float

        // Late charging: 15 mins
        'late' => 0.25,  // Hours, float
    ],

];
