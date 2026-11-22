<?php

declare(strict_types=1);

// PUNCTUL DE INTRARE al aplicatiei.
// Toate cererile ajung aici si sunt trimise catre controllerul potrivit.

require_once __DIR__ . '/../config/conexiune.php';
require_once __DIR__ . '/../config/sesiune.php';

require_once __DIR__ . '/../mvc/Models/Drepturi.php';
require_once __DIR__ . '/../mvc/Models/Utilizator.php';
require_once __DIR__ . '/../mvc/Models/Rol.php';
require_once __DIR__ . '/../mvc/Models/CerereCont.php';
require_once __DIR__ . '/../mvc/Models/ResetareParola.php';
require_once __DIR__ . '/../mvc/Models/IncercareAcces.php';

require_once __DIR__ . '/../mvc/Controllers/Controller.php';
require_once __DIR__ . '/../mvc/Controllers/AutentificareController.php';
require_once __DIR__ . '/../mvc/Controllers/ContController.php';
require_once __DIR__ . '/../mvc/Controllers/PanouController.php';
require_once __DIR__ . '/../mvc/Controllers/AdministrareConturiController.php';

// Rutele aplicatiei: 'METODA ruta' => [controller, metoda controllerului].
// O pagina noua se adauga aici, cu controllerul ei.
$rute = [
    // Pagini publice
    'GET login' => [AutentificareController::class, 'afiseazaLogin'],
    'GET creare-cont' => [ContController::class, 'afiseazaCreareCont'],
    'GET recuperare-parola' => [ContController::class, 'afiseazaRecuperareParola'],
    'GET parola-noua' => [ContController::class, 'afiseazaParolaNoua'],

    // Operatii publice, apelate din JavaScript
    'POST autentificare' => [AutentificareController::class, 'autentifica'],
    'POST creare-cont' => [ContController::class, 'solicitaCont'],
    'POST recuperare-parola' => [ContController::class, 'solicitaResetare'],
    'GET verificare-link' => [ContController::class, 'verificaLink'],
    'POST parola-noua' => [ContController::class, 'seteazaParolaNoua'],

    // Dupa autentificare, pentru orice rol
    'GET panou' => [PanouController::class, 'afiseaza'],
    'GET sesiune' => [AutentificareController::class, 'sesiuneCurenta'],
    'POST deconectare' => [AutentificareController::class, 'deconecteaza'],
    'GET schimbare-parola' => [ContController::class, 'afiseazaSchimbareParola'],
    'POST schimbare-parola' => [ContController::class, 'schimbaParola'],

    // Doar pentru administratorul general
    'GET administrare-conturi' => [AdministrareConturiController::class, 'afiseaza'],
    'GET administrare-conturi-date' => [AdministrareConturiController::class, 'date'],
    'POST aprobare-cerere-cont' => [AdministrareConturiController::class, 'aprobaCerereCont'],
    'POST respingere-cerere-cont' => [AdministrareConturiController::class, 'respingeCerereCont'],
    'POST actualizare-cont' => [AdministrareConturiController::class, 'actualizeazaCont'],
    'POST stare-cont' => [AdministrareConturiController::class, 'schimbaStareCont'],
    'POST generare-link-resetare' => [AdministrareConturiController::class, 'genereazaLinkResetare'],
    'POST respingere-cerere-resetare' => [AdministrareConturiController::class, 'respingeCerereResetare'],
];

// Raspuns simplu, pentru situatiile in care nu ajungem la un controller.
function raspundeFaraController(int $codHttp, string $mesaj): void
{
    $vreaJson = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

    http_response_code($codHttp);
    header('Cache-Control: no-store');

    if ($vreaJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['succes' => false, 'mesaj' => $mesaj], JSON_UNESCAPED_UNICODE);
        return;
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo $mesaj;
}

// Antete de securitate pentru toate raspunsurile.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

$ruta = is_string($_GET['ruta'] ?? null) ? $_GET['ruta'] : 'login';
$metoda = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$destinatie = $rute[$metoda . ' ' . $ruta] ?? null;

if ($destinatie === null) {
    raspundeFaraController(404, 'Pagina nu există.');
    return;
}

// Orice cerere care modifica ceva trebuie sa vina din JavaScript-ul aplicatiei.
if ($metoda === 'POST' && !Controller::cerereDinInterfata()) {
    raspundeFaraController(403, 'Cerere respinsă.');
    return;
}

try {
    pornesteSesiunea();

    [$clasaController, $metodaController] = $destinatie;
    (new $clasaController())->$metodaController();
} catch (Throwable $eroare) {
    // Detaliile tehnice raman in jurnalul de erori PHP, nu ajung in browser.
    error_log((string) $eroare);

    // 42S02 = lipseste un tabel: baza de date nu a fost adusa la zi.
    $lipsesteTabel = $eroare instanceof PDOException && $eroare->getCode() === '42S02';

    raspundeFaraController(500, $lipsesteTabel
        ? 'Baza de date nu este la zi. Rulează fișierul database/autentificare.sql în HeidiSQL.'
        : 'Aplicația nu este disponibilă momentan. Încearcă din nou.');
}
