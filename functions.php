<?php

function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        die("Le fichier .env est introuvable.");
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($key, $value) = explode('=', $line, 2);

        $key = trim($key);
        $value = trim($value, '"');

        putenv("$key=$value");
    }
}
