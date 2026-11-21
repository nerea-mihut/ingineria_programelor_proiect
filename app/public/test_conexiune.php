<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$adresaClientului = $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($adresaClientului, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Test disponibil doar local.');
}

try {
    require_once __DIR__ . '/../config/conexiune.php';

    $pdo = conectareBazaDeDate();

    $baza = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $numarUtilizatori = $pdo->query('SELECT COUNT(*) FROM utilizator')->fetchColumn();

    echo "Conexiune reușită!\n";
    echo "Baza selectată: " . $baza . "\n";
    echo "Număr de utilizatori: " . $numarUtilizatori . "\n";
} catch (Throwable $eroare) {
    error_log((string) $eroare);
    http_response_code(500);
    echo "Conexiunea sau verificarea bazei a eșuat.\n";
    echo "Verifică serviciul MySQL și configurarea locală.\n";
    echo "Detaliile tehnice sunt în jurnalul de erori PHP.";
}
