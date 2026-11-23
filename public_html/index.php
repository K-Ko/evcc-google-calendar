<?php

define('ROOT', dirname(__DIR__));

$config = require ROOT . '/config/config.php';

if ($config['debug'] ?? false) {
    error_reporting(-1);
    ini_set('display_errors', 1);
}

include_once ROOT . '/vendor/autoload.php';

// --------------------------------------------------------------------------

if (!empty($config['frontend']['user']) && !empty($config['frontend']['hash'])) {
    $user = $config['frontend']['user'];
    $hash = $config['frontend']['hash'];

    if (strtolower($_SERVER['PHP_AUTH_USER'] ?? '') != $user || md5($_SERVER['PHP_AUTH_PW'] ?? '') != $hash) {
        header('HTTP/1.1 401 Unauthorized');
        header('WWW-Authenticate: Basic realm="Restricted area"');
        echo 'Sorry, restricted area';
        exit;
    }
}

// --------------------------------------------------------------------------

session_start();

$lang = ROOT . '/language/' . ($config['language'] ?? 'en') . '.php';

if (!is_file($lang)) {
    // Fallback
    $lang = ROOT . '/language/en.php';
}

$i18n = new \Core\I18N(require $lang);

$evcc = new \Core\EVCC($config);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $_SESSION['messages'][] = $evcc->fetch() ? $i18n->PlansRefreshed : $i18n->EmptyResponse;

    // Reload page to break POST
    die(header('Location: /'));
}
// --------------------------------------------------------------------------

$now = time();

$events = $evcc->analyse();

$messages = $_SESSION['messages'] ?? [];
unset($_SESSION['messages']);

// --------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="evcc - Sonne tanken" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="default" />
    <meta name="apple-mobile-web-app-title" content="evcc" />
    <meta name="application-name" content="evcc" />
    <meta name="viewport" content="initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover" />

    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png" />
    <link rel="manifest" href="/site.webmanifest" crossorigin="use-credentials" />
    <link rel="mask-icon" href="/safari-pinned-tab.svg" color="#1C2445" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <meta name="msapplication-TileColor" content="#1C2445" />
    <meta name="msapplication-config" content="/browserconfig.xml" />
    <meta name="theme-color" content="#020318" />
    <meta name="evcc-app-compatible" content="true" />

    <title>evcc - Kalendersteuerung</title>

    <meta property="og:title" content="MARSTEK Venus E Gen 3.0 Dynamic configurations">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <style>
        .w-1 {
            width: 1%
        }
    </style>
</head>

<body class="py-3">
    <div class="container">

        <?php
        if (!empty($messages)) {
            echo '<div id="messages" class="alert alert-info messages">', implode('<br>', $messages), '</div>';
        }
        ?>

        <h3 class="form-group">
            <?= $i18n->Plans ?>
            <form method="post" class="float-right">
                <button type"submit" class="btn btn-sm btn-primary"><?= $i18n->Refresh ?></button>
            </form>
        </h3>

        <table class="table border-bottom">
            <thead class="thead-light">
                <tr>
                    <th class="text-center w-1"><?= $i18n->DateTime ?></th>
                    <th class="text-nowrap text-right w-1"><?= $i18n->Remaining ?></th>
                    <th class="text-center w-1"><?= $i18n->ChargeLevel ?></th>
                    <th><?= $i18n->Strategy ?></th>
                    <th><?= $i18n->Comment ?></th>
                </tr>
            </thead>

            <tbody>
                <?php

                foreach ($events as $event) {

                    // Exclude expired events
                    if ($event['ts'] < $now) continue;

                    echo '<tr>';

                    printf(
                        '<td class="text-nowrap text-center text-monospace">%s</td>',
                        date($i18n->DateTimeFormat, $event['ts'])
                    );

                    printf(
                        '<td class="text-nowrap text-right text-monospace">%s h</td>',
                        number_format(($event['ts'] - $now) / 3600, 1, ',', '.')
                    );

                    // Colored from lg, below just digits
                    printf(
                        '<td class="text-monospace text-right">
                            <div class="bg-success text-white text-center float-left rounded d-none d-lg-inline" style="width:%2$dpx">
                                %1$d %%
                            </div>
                            <span class="d-lg-none">%1$d %%</span>
                        </td>',
                        $event['target'],
                        round($event['target'] * 1.5)
                    );

                    if (isset($event['strategy'])) {
                        // {"continuous":true,"precondition":15}
                        $event['strategy'] = json_decode($event['strategy'], true);
                        $event['strategy'] = sprintf(
                            'kontinuierlich: %s &bull; spät: %d Min.',
                            ['nein', 'ja'][$event['strategy']['continuous']],
                            $event['strategy']['precondition'] / 60
                        );
                    }

                    printf('<td>%s</td>', $event['strategy'] ?? '');

                    printf('<td>%s</td>', $event['comment']);

                    echo '</tr>';
                }

                ?>
            </tbody>
        </table>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        ($ => {
            setTimeout(
                () => $('#messages').fadeOut(
                    'slow',
                    function() {
                        $(this).remove();
                    }),
                5000
            );
        })(jQuery)
    </script>
</body>

</html>
