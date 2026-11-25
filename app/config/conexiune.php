<?php

declare(strict_types=1);

function conectareBazaDeDate(): PDO
{
    $config = require __DIR__ . '/configurare.locala.php';

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['baza']
    );

    return new PDO(
        $dsn,
        $config['utilizator'],
        $config['parola'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}
