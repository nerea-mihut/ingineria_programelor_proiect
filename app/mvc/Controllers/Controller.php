<?php

declare(strict_types=1);

// Functii comune pentru toate controllerele.
abstract class Controller
{
    private static ?PDO $conexiune = null;
    private static ?array $setari = null;

    private static bool $utilizatorVerificat = false;
    private static ?array $utilizator = null;
    private static string $motivSesiuneInchisa = '';

    // O singura conexiune la baza de date pentru toata cererea.
    protected function bazaDeDate(): PDO
    {
        return self::$conexiune ??= conectareBazaDeDate();
    }

    // Regulile din config/securitate.php.
    protected function setari(): array
    {
        return self::$setari ??= require __DIR__ . '/../../config/securitate.php';
    }

    // ----- Raspunsuri -----

    // Trimite browserului un fisier HTML din folderul Views.
    protected function afiseazaView(string $caleInViews): void
    {
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/../Views/' . $caleInViews);
    }

    // Trimite un raspuns JSON, citit apoi de JavaScript-ul din pagina.
    protected function raspundeJson(int $codHttp, array $date): void
    {
        http_response_code($codHttp);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($date, JSON_UNESCAPED_UNICODE);
    }

    // Raspunsul obisnuit pentru o operatie care nu a reusit.
    protected function raspundeEroare(int $codHttp, string $mesaj): void
    {
        $this->raspundeJson($codHttp, ['succes' => false, 'mesaj' => $mesaj]);
    }

    protected function redirectioneaza(string $ruta): void
    {
        header('Location: index.php?ruta=' . $ruta, true, 302);
    }

    // ----- Datele cererii -----

    // Cererile care modifica ceva trebuie sa vina din JavaScript-ul aplicatiei.
    // Un formular de pe alt site nu poate adauga acest antet.
    public static function cerereDinInterfata(): bool
    {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
            return false;
        }

        // Daca browserul spune de unde vine cererea, trebuie sa fie aceeasi adresa.
        $origine = $_SERVER['HTTP_ORIGIN'] ?? '';
        $gazda = $_SERVER['HTTP_HOST'] ?? '';

        return $origine === '' || parse_url($origine, PHP_URL_HOST) === parse_url('http://' . $gazda, PHP_URL_HOST);
    }

    // JavaScript-ul asteapta JSON; o adresa deschisa direct in browser asteapta o pagina.
    protected function asteaptaJson(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    // Un camp text din formular, fara spatiile de la capete.
    protected function camp(string $nume): string
    {
        $valoare = $_POST[$nume] ?? null;

        return is_string($valoare) ? trim($valoare) : '';
    }

    // O parola din formular, exact asa cum a fost scrisa.
    protected function campParola(string $nume): string
    {
        $valoare = $_POST[$nume] ?? null;

        return is_string($valoare) ? $valoare : '';
    }

    // Un numar intreg pozitiv din formular (de exemplu un id) sau 0 daca lipseste.
    protected function campNumar(string $nume): int
    {
        $valoare = $this->camp($nume);

        return ctype_digit($valoare) && strlen($valoare) <= 9 ? (int) $valoare : 0;
    }

    protected function adresaIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    protected function emailValid(string $email): bool
    {
        return $email !== ''
            && strlen($email) <= 254
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    // ----- Cine este autentificat -----

    // Intoarce utilizatorul autentificat sau null.
    //
    // La fiecare cerere contul este citit din nou din baza de date (RNF-002):
    // un cont dezactivat, un rol schimbat sau o parola schimbata au efect imediat.
    protected function utilizatorCurent(): ?array
    {
        if (self::$utilizatorVerificat) {
            return self::$utilizator;
        }

        self::$utilizatorVerificat = true;

        $idUtilizator = $_SESSION['id_utilizator'] ?? null;

        if (!is_int($idUtilizator)) {
            return null;
        }

        $secundeInactivitate = time() - (int) ($_SESSION['ultima_activitate'] ?? 0);

        if ($secundeInactivitate > $this->setari()['minute_inactivitate'] * 60) {
            inchideSesiunea();
            self::$motivSesiuneInchisa = 'sesiune-expirata';

            return null;
        }

        $cont = (new Utilizator($this->bazaDeDate()))->gasesteDupaId($idUtilizator);

        $contValid = $cont !== null
            && (int) $cont['activ'] === 1
            && hash_equals(
                (string) ($_SESSION['amprenta_parola'] ?? ''),
                amprentaParolei($cont['parola_hash'])
            );

        if (!$contValid) {
            inchideSesiunea();
            self::$motivSesiuneInchisa = 'cont-modificat';

            return null;
        }

        $_SESSION['ultima_activitate'] = time();

        return self::$utilizator = [
            'id' => (int) $cont['id_utilizator'],
            'nume' => $cont['nume_complet'],
            'email' => $cont['email'],
            'rol' => $cont['rol'],
            'departament' => $cont['departament'],
        ];
    }

    // Dupa autentificare sau deconectare, utilizatorul trebuie citit din nou.
    protected function uitaUtilizatorulCurent(): void
    {
        self::$utilizatorVerificat = false;
        self::$utilizator = null;
    }

    // Pentru rutele protejate: intoarce utilizatorul sau trimite spre autentificare.
    //
    //     $utilizator = $this->cereAutentificare();
    //     if ($utilizator === null) {
    //         return;
    //     }
    protected function cereAutentificare(): ?array
    {
        $utilizator = $this->utilizatorCurent();

        // Paginile protejate nu se pastreaza in memoria browserului,
        // ca sa nu poata fi revazute dupa deconectare.
        header('Cache-Control: no-store');

        if ($utilizator !== null) {
            return $utilizator;
        }

        if ($this->asteaptaJson()) {
            $this->raspundeJson(401, [
                'succes' => false,
                'autentificat' => false,
                'motiv' => self::$motivSesiuneInchisa,
                'mesaj' => 'Trebuie să te autentifici.',
            ]);

            return null;
        }

        $motiv = self::$motivSesiuneInchisa;
        $this->redirectioneaza('login' . ($motiv !== '' ? '&motiv=' . $motiv : ''));

        return null;
    }

    // Pentru rutele rezervate unor roluri: intoarce utilizatorul sau refuza accesul.
    // Administratorul general are acces peste tot.
    //
    //     $utilizator = $this->cereRol(['operator_cnc']);
    //     $utilizator = $this->cereRol(['administrator_departament'], 'Mentenanță');
    protected function cereRol(array $roluri, ?string $departament = null): ?array
    {
        $utilizator = $this->cereAutentificare();

        if ($utilizator === null) {
            return null;
        }

        if (Drepturi::areAcces($utilizator, $roluri, $departament)) {
            return $utilizator;
        }

        if ($this->asteaptaJson()) {
            $this->raspundeEroare(403, 'Nu ai dreptul să faci această operație.');

            return null;
        }

        http_response_code(403);
        $this->afiseazaView('erori/acces-interzis.html');

        return null;
    }
}
