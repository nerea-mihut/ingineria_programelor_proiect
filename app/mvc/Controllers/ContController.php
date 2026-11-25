<?php

declare(strict_types=1);

// CONTROLLER: cererea de cont, parola uitata si schimbarea parolei.
final class ContController extends Controller
{
    private const MESAJ_LINK_NEVALID = 'Linkul nu este valid sau a expirat. Cere administratorului unul nou.';

    // ----- Creare cont -----

    public function afiseazaCreareCont(): void
    {
        $this->afiseazaView('acces_cont/creare-cont.html');
    }

    // Formularul "Creare cont" nu creeaza direct un cont, ci o cerere.
    // Rolul si departamentul le alege administratorul general la aprobare (RF-001).
    public function solicitaCont(): void
    {
        $nume = (string) preg_replace('/\s+/u', ' ', $this->camp('name'));
        $email = strtolower($this->camp('email'));
        $parola = $this->campParola('password');

        if (preg_match('/^\p{L}[\p{L}\p{M} .\'’-]{1,119}$/u', $nume) !== 1) {
            $this->raspundeEroare(422, 'Scrie numele complet, folosind doar litere, spații și cratime.');
            return;
        }

        if (!$this->emailValid($email)) {
            $this->raspundeEroare(422, 'Scrie o adresă de e-mail validă.');
            return;
        }

        $eroareParola = $this->eroareParolaNoua($parola, $this->campParola('confirm'), $email);

        if ($eroareParola !== null) {
            $this->raspundeEroare(422, $eroareParola);
            return;
        }

        if (!$this->cererePermisa(IncercareAcces::CERERE_CONT, $email)) {
            return;
        }

        // Hash-ul se calculeaza de fiecare data, ca raspunsul sa dureze la fel
        // indiferent daca adresa are sau nu deja un cont.
        $parolaHash = password_hash($parola, PASSWORD_DEFAULT);

        $utilizatori = new Utilizator($this->bazaDeDate());
        $cereri = new CerereCont($this->bazaDeDate());

        if (!$utilizatori->existaEmail($email) && !$cereri->existaInAsteptare($email)) {
            $cereri->creeaza($nume, $email, $parolaHash);
        }

        // Acelasi raspuns si cand adresa are deja cont: nu dezvaluim ce adrese exista.
        $this->raspundeJson(200, [
            'succes' => true,
            'mesaj' => 'Cererea a fost trimisă. Vei putea intra în aplicație după ce administratorul '
                . 'îți aprobă contul. Dacă ai deja un cont cu această adresă, folosește autentificarea.',
        ]);
    }

    // ----- Parola uitata -----

    public function afiseazaRecuperareParola(): void
    {
        $this->afiseazaView('acces_cont/recuperare-parola.html');
    }

    // Cererea ajunge la administratorul general, care genereaza un link de resetare.
    public function solicitaResetare(): void
    {
        $email = strtolower($this->camp('email'));

        if (!$this->emailValid($email)) {
            $this->raspundeEroare(422, 'Scrie o adresă de e-mail validă.');
            return;
        }

        if (!$this->cererePermisa(IncercareAcces::CERERE_RESETARE, $email)) {
            return;
        }

        $utilizator = (new Utilizator($this->bazaDeDate()))->gasesteDupaEmail($email);
        $resetari = new ResetareParola($this->bazaDeDate());

        if (
            $utilizator !== null
            && (int) $utilizator['activ'] === 1
            && !$resetari->areCerereDeschisa((int) $utilizator['id_utilizator'])
        ) {
            $resetari->creeazaCerere((int) $utilizator['id_utilizator']);
        }

        // Acelasi raspuns si cand adresa nu are cont.
        $this->raspundeJson(200, [
            'succes' => true,
            'mesaj' => 'Dacă adresa are un cont activ, cererea a ajuns la administrator. '
                . 'Vei primi de la el un link pentru o parolă nouă.',
        ]);
    }

    // ----- Parola noua, cu linkul primit de la administrator -----

    public function afiseazaParolaNoua(): void
    {
        // Adresa paginii contine codul din link: nu o pastram si nu o trimitem mai departe.
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');

        $this->afiseazaView('acces_cont/parola-noua.html');
    }

    // Pagina intreaba, la deschidere, daca linkul mai este bun.
    public function verificaLink(): void
    {
        $cod = is_string($_GET['cod'] ?? null) ? $_GET['cod'] : '';

        if ($this->linkuriBlocate()) {
            return;
        }

        if ($this->gasesteLinkValid($cod) === null) {
            $this->raspundeJson(200, ['valid' => false, 'mesaj' => self::MESAJ_LINK_NEVALID]);
            return;
        }

        $this->raspundeJson(200, ['valid' => true]);
    }

    public function seteazaParolaNoua(): void
    {
        if ($this->linkuriBlocate()) {
            return;
        }

        $resetare = $this->gasesteLinkValid($this->camp('cod'));

        if ($resetare === null) {
            $this->raspundeEroare(400, self::MESAJ_LINK_NEVALID);
            return;
        }

        $parola = $this->campParola('password');
        $eroareParola = $this->eroareParolaNoua($parola, $this->campParola('confirm'), $resetare['email']);

        if ($eroareParola !== null) {
            $this->raspundeEroare(422, $eroareParola);
            return;
        }

        $pdo = $this->bazaDeDate();
        $resetari = new ResetareParola($pdo);
        $idUtilizator = (int) $resetare['id_utilizator'];

        // Totul sau nimic: linkul se consuma doar daca parola a fost schimbata.
        $pdo->beginTransaction();

        try {
            if (!$resetari->marcheazaFolosita((int) $resetare['id_resetare'])) {
                $pdo->rollBack();
                $this->raspundeEroare(400, self::MESAJ_LINK_NEVALID);
                return;
            }

            (new Utilizator($pdo))->actualizeazaParola($idUtilizator, password_hash($parola, PASSWORD_DEFAULT));
            $resetari->anuleazaDeschise($idUtilizator);

            $pdo->commit();
        } catch (Throwable $eroare) {
            $pdo->rollBack();
            throw $eroare;
        }

        // Cu o parola noua, blocarea dupa greseli repetate nu mai are rost.
        (new IncercareAcces($pdo))->stergeEsecuriEmail(IncercareAcces::AUTENTIFICARE, $resetare['email']);

        $this->raspundeJson(200, [
            'succes' => true,
            'destinatie' => 'index.php?ruta=login&motiv=parola-schimbata',
        ]);
    }

    // ----- Schimbarea parolei de catre utilizatorul autentificat -----

    public function afiseazaSchimbareParola(): void
    {
        if ($this->cereAutentificare() === null) {
            return;
        }

        $this->afiseazaView('cont/schimbare-parola.html');
    }

    public function schimbaParola(): void
    {
        $utilizator = $this->cereAutentificare();

        if ($utilizator === null) {
            return;
        }

        $setari = $this->setari();
        $pdo = $this->bazaDeDate();
        $incercari = new IncercareAcces($pdo);
        $tip = IncercareAcces::SCHIMBARE_PAROLA;

        $secundeBlocare = $incercari->secundeBlocareEmail(
            $tip,
            $utilizator['email'],
            $setari['incercari_pe_email'],
            $setari['minute_blocare']
        );

        if ($secundeBlocare > 0) {
            $this->raspundeEroare(429, 'Prea multe încercări nereușite. Încearcă din nou mai târziu.');
            return;
        }

        $utilizatori = new Utilizator($pdo);
        $cont = $utilizatori->gasesteDupaId($utilizator['id']);
        $parolaActuala = $this->campParola('current');

        // Cine schimba parola trebuie sa o stie pe cea actuala,
        // chiar daca este deja autentificat.
        if ($cont === null || !password_verify($parolaActuala, $cont['parola_hash'])) {
            $incercari->inregistreaza($tip, $utilizator['email'], $this->adresaIp(), false);
            $this->raspundeEroare(422, 'Parola actuală nu este corectă.');
            return;
        }

        $parolaNoua = $this->campParola('password');
        $eroareParola = $this->eroareParolaNoua($parolaNoua, $this->campParola('confirm'), $utilizator['email']);

        if ($eroareParola === null && $parolaNoua === $parolaActuala) {
            $eroareParola = 'Parola nouă trebuie să fie diferită de cea actuală.';
        }

        if ($eroareParola !== null) {
            $this->raspundeEroare(422, $eroareParola);
            return;
        }

        $cont['parola_hash'] = password_hash($parolaNoua, PASSWORD_DEFAULT);
        $utilizatori->actualizeazaParola($utilizator['id'], $cont['parola_hash']);

        (new ResetareParola($pdo))->anuleazaDeschise($utilizator['id']);
        $incercari->stergeEsecuriEmail($tip, $utilizator['email']);

        // Sesiunea aceasta ramane deschisa, cu noua parola.
        // Celelalte sesiuni ale contului nu mai sunt acceptate.
        deschideSesiuneaPentru($cont);

        $this->raspundeJson(200, ['succes' => true, 'mesaj' => 'Parola a fost schimbată.']);
    }

    // ----- Reguli comune -----

    // Intoarce textul erorii sau null daca parola noua respecta regulile.
    private function eroareParolaNoua(string $parola, string $confirmare, string $email): ?string
    {
        $setari = $this->setari();
        $numarCaractere = preg_match_all('/./us', $parola);

        if ($numarCaractere === false || $numarCaractere < $setari['lungime_minima_parola']) {
            return 'Parola trebuie să aibă cel puțin ' . $setari['lungime_minima_parola'] . ' caractere.';
        }

        if (strlen($parola) > $setari['lungime_maxima_parola']) {
            return 'Parola este prea lungă. Alege una de cel mult ' . $setari['lungime_maxima_parola'] . ' de caractere.';
        }

        if (preg_match('/\p{L}/u', $parola) !== 1 || preg_match('/\d/', $parola) !== 1) {
            return 'Parola trebuie să conțină cel puțin o literă și o cifră.';
        }

        if (strcasecmp($parola, $email) === 0) {
            return 'Parola nu poate fi adresa de e-mail.';
        }

        if ($parola !== $confirmare) {
            return 'Parolele nu coincid.';
        }

        return null;
    }

    // Limiteaza cate cereri poate trimite aceeasi adresa IP intr-o ora.
    // Intoarce false dupa ce a trimis raspunsul de refuz.
    private function cererePermisa(string $tip, string $email): bool
    {
        $incercari = new IncercareAcces($this->bazaDeDate());

        if ($incercari->numaraPentruIp($tip, $this->adresaIp(), 60) >= $this->setari()['cereri_pe_ora']) {
            $this->raspundeEroare(429, 'Ai trimis prea multe cereri. Încearcă din nou mai târziu.');
            return false;
        }

        $incercari->inregistreaza($tip, $email, $this->adresaIp(), true);

        return true;
    }

    // Opreste ghicirea codurilor din linkuri. Intoarce true dupa ce a trimis refuzul.
    private function linkuriBlocate(): bool
    {
        $setari = $this->setari();
        $secundeBlocare = (new IncercareAcces($this->bazaDeDate()))->secundeBlocareIp(
            IncercareAcces::LINK_RESETARE,
            $this->adresaIp(),
            $setari['incercari_pe_ip'],
            $setari['minute_blocare']
        );

        if ($secundeBlocare > 0) {
            $this->raspundeEroare(429, 'Prea multe încercări nereușite. Încearcă din nou mai târziu.');
            return true;
        }

        return false;
    }

    // Cauta linkul dupa codul primit. Un cod gresit se trece in jurnal.
    private function gasesteLinkValid(string $cod): ?array
    {
        $resetare = preg_match('/^[a-f0-9]{64}$/', $cod) === 1
            ? (new ResetareParola($this->bazaDeDate()))->gasesteLinkValid(ResetareParola::amprentaCodului($cod))
            : null;

        if ($resetare === null) {
            (new IncercareAcces($this->bazaDeDate()))->inregistreaza(
                IncercareAcces::LINK_RESETARE,
                null,
                $this->adresaIp(),
                false
            );
        }

        return $resetare;
    }
}
