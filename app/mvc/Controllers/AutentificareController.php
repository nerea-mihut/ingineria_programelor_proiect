<?php

declare(strict_types=1);

// CONTROLLER: autentificarea, sesiunea curenta si deconectarea.
final class AutentificareController extends Controller
{
    // Folosit cand e-mailul nu exista, ca verificarea sa dureze la fel
    // ca pentru un cont real. Nu corespunde niciunei parole cunoscute.
    private const HASH_FICTIV = '$2y$10$Xv8zPgdJd9QIOBMVrEI47OBy5YkP7I9aZ01jgSX2leXhEIBQnkD.C';

    private const LUNGIME_MAXIMA_PAROLA = 1024;

    public function afiseazaLogin(): void
    {
        $utilizator = $this->utilizatorCurent();

        if ($utilizator !== null) {
            $this->redirectioneaza(Drepturi::paginaStart($utilizator['rol']));
            return;
        }

        $this->afiseazaView('acces_cont/login.html');
    }

    public function autentifica(): void
    {
        $email = $this->camp('email');
        $parola = $this->campParola('password');

        if (!$this->emailValid($email) || $parola === '' || strlen($parola) > self::LUNGIME_MAXIMA_PAROLA) {
            $this->raspundeEroare(422, 'Completează o adresă de e-mail validă și parola.');
            return;
        }

        $setari = $this->setari();
        $incercari = new IncercareAcces($this->bazaDeDate());
        $tip = IncercareAcces::AUTENTIFICARE;

        // Prea multe greseli recente pentru acest e-mail sau de la aceasta adresa?
        $secundeBlocare = max(
            $incercari->secundeBlocareEmail($tip, $email, $setari['incercari_pe_email'], $setari['minute_blocare']),
            $incercari->secundeBlocareIp($tip, $this->adresaIp(), $setari['incercari_pe_ip'], $setari['minute_blocare'])
        );

        if ($secundeBlocare > 0) {
            $this->raspundeBlocat($secundeBlocare);
            return;
        }

        $utilizator = (new Utilizator($this->bazaDeDate()))->gasesteDupaEmail($email);

        // Fara cont: poate exista o cerere de cont inca neaprobata.
        $cerere = $utilizator === null
            ? (new CerereCont($this->bazaDeDate()))->ultimaNeaprobata($email)
            : null;

        // Parola introdusa se compara cu hash-ul salvat, nu cu o parola in clar.
        $hash = $utilizator['parola_hash'] ?? $cerere['parola_hash'] ?? self::HASH_FICTIV;
        $parolaCorecta = password_verify($parola, $hash);

        if (!$parolaCorecta || ($utilizator === null && $cerere === null)) {
            $incercari->inregistreaza($tip, $email, $this->adresaIp(), false);
            $this->raspundeDateGresite($incercari, $email);
            return;
        }

        // De aici parola este corecta, deci putem spune exact care este situatia.
        if ($utilizator === null) {
            $this->raspundeEroare(403, $cerere['stare'] === 'in_asteptare'
                ? 'Contul tău așteaptă aprobarea administratorului.'
                : 'Cererea ta de cont a fost respinsă. Contactează administratorul.');
            return;
        }

        if ((int) $utilizator['activ'] !== 1) {
            $this->raspundeEroare(403, 'Contul nu este activ. Contactează administratorul.');
            return;
        }

        $incercari->stergeEsecuriEmail($tip, $email);
        $incercari->stergeVechi();

        // Daca PHP recomanda intre timp un hash mai puternic, il inlocuim acum.
        if (password_needs_rehash($utilizator['parola_hash'], PASSWORD_DEFAULT)) {
            $utilizator['parola_hash'] = password_hash($parola, PASSWORD_DEFAULT);
            (new Utilizator($this->bazaDeDate()))->actualizeazaParola(
                (int) $utilizator['id_utilizator'],
                $utilizator['parola_hash']
            );
        }

        deschideSesiuneaPentru($utilizator);
        $this->uitaUtilizatorulCurent();

        // Fiecare rol are pagina lui de start (config/roluri.php).
        $this->raspundeJson(200, [
            'succes' => true,
            'destinatie' => 'index.php?ruta=' . Drepturi::paginaStart($utilizator['rol']),
        ]);
    }

    // Spune paginii cine este autentificat si ce module are (fara date sensibile).
    public function sesiuneCurenta(): void
    {
        $utilizator = $this->cereAutentificare();

        if ($utilizator === null) {
            return;
        }

        $this->raspundeJson(200, [
            'autentificat' => true,
            'utilizator' => [
                'nume' => $utilizator['nume'],
                'email' => $utilizator['email'],
                'rol' => $utilizator['rol'],
                'rol_eticheta' => Drepturi::eticheta($utilizator['rol']),
                'departament' => $utilizator['departament'],
            ],
            'module' => Drepturi::module($utilizator['rol'], $utilizator['departament']),
        ]);
    }

    public function deconecteaza(): void
    {
        // Sesiunea este invalidata imediat (RNF-003 din SRS).
        inchideSesiunea();
        $this->uitaUtilizatorulCurent();

        $this->raspundeJson(200, ['succes' => true]);
    }

    // Acelasi mesaj pentru e-mail inexistent si parola gresita,
    // ca sa nu se poata afla ce adrese au cont.
    private function raspundeDateGresite(IncercareAcces $incercari, string $email): void
    {
        $setari = $this->setari();
        $tip = IncercareAcces::AUTENTIFICARE;

        $secundeBlocare = $incercari->secundeBlocareEmail(
            $tip,
            $email,
            $setari['incercari_pe_email'],
            $setari['minute_blocare']
        );

        if ($secundeBlocare > 0) {
            $this->raspundeBlocat($secundeBlocare);
            return;
        }

        $ramase = $setari['incercari_pe_email']
            - $incercari->numaraEsecuriEmail($tip, $email, $setari['minute_blocare']);

        $mesaj = 'E-mail sau parolă incorectă.';

        if ($ramase === 1) {
            $mesaj .= ' Mai ai o încercare.';
        } elseif ($ramase === 2) {
            $mesaj .= ' Mai ai 2 încercări.';
        }

        $this->raspundeEroare(401, $mesaj);
    }

    private function raspundeBlocat(int $secunde): void
    {
        $minute = (int) ceil($secunde / 60);

        header('Retry-After: ' . $secunde);
        $this->raspundeEroare(429, 'Prea multe încercări nereușite. Încearcă din nou peste '
            . ($minute === 1 ? 'un minut' : $minute . ' minute') . '.');
    }
}
