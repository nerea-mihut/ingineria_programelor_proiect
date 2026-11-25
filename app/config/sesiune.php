<?php

declare(strict_types=1);

// Porneste sesiunea PHP cu setari sigure pentru cookie.
// Sesiunea tine minte, intre cereri, cine este autentificat.
function pornesteSesiunea(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $conexiuneHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('mesh_sesiune');
    session_set_cookie_params([
        'lifetime' => 0,          // cookie-ul dispare la inchiderea browserului
        'path' => '/',
        'httponly' => true,       // JavaScript nu poate citi cookie-ul
        'samesite' => 'Lax',      // nu se trimite la cereri POST de pe alte site-uri
        'secure' => $conexiuneHttps,
    ]);

    session_start();
}

// Leaga sesiunea de un utilizator, dupa ce parola a fost verificata.
function deschideSesiuneaPentru(array $utilizator): void
{
    // Identificator nou de sesiune dupa autentificare.
    session_regenerate_id(true);

    $_SESSION['id_utilizator'] = (int) $utilizator['id_utilizator'];
    $_SESSION['amprenta_parola'] = amprentaParolei($utilizator['parola_hash']);
    $_SESSION['ultima_activitate'] = time();
}

// Sterge sesiunea de pe server si cookie-ul din browser (RNF-003 din SRS).
function inchideSesiunea(): void
{
    $_SESSION = [];

    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $cookie['path'],
        'domain' => $cookie['domain'],
        'secure' => $cookie['secure'],
        'httponly' => $cookie['httponly'],
        'samesite' => $cookie['samesite'],
    ]);

    session_destroy();
}

// Sesiunea retine o amprenta a parolei. Daca parola se schimba,
// amprenta nu mai corespunde si sesiunile vechi nu mai sunt acceptate.
function amprentaParolei(string $parolaHash): string
{
    return hash('sha256', $parolaHash);
}
