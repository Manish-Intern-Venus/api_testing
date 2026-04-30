<?php

declare(strict_types=1);

$scripts = [
    'AuthTest.php',
];

$baseDir = __DIR__;
$exitCode = 0;

foreach ($scripts as $script) {
    echo 'Running ' . $script . PHP_EOL;
    passthru('php ' . escapeshellarg($baseDir . '/' . $script), $scriptExitCode);

    if ($scriptExitCode !== 0) {
        $exitCode = $scriptExitCode;
    }

    echo PHP_EOL;
}

exit($exitCode);
