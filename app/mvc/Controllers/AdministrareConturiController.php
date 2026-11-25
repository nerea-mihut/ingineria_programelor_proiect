<?php

declare(strict_types=1);

// CONTROLLER: administrarea conturilor.
// Doar administratorul general gestioneaza conturile si drepturile (RF-002 din SRS).
final class AdministrareConturiController extends Controller
{
    private const ROLURI_PERMISE = [Drepturi::ADMINISTRATOR_GENERAL];

    public function afiseaza(): void
    {
        if ($this->cereRol(self::ROLURI_PERMISE) === null) {
            return;
        }

        $this->afiseazaView('administrare/conturi.html');
    }

    // Tot ce are nevoie pagina: cererile in asteptare, conturile si rolurile posibile.
    public function date(): void
    {
        $administrator = $this->cereRol(self::ROLURI_PERMISE);

        if ($administrator === null) {
            return;
        }

        $pdo = $this->bazaDeDate();
        $conturi = [];

        foreach ((new Utilizator($pdo))->toate() as $cont) {
            $conturi[] = [
                'id' => (int) $cont['id_utilizator'],
                'nume' => $cont['nume_complet'],
                'email' => $cont['email'],
                'rol' => $cont['rol'],
                'rol_eticheta' => Drepturi::eticheta($cont['rol']),
                'departament' => $cont['departament'],
                'activ' => (int) $cont['activ'] === 1,
                'propriu' => (int) $cont['id_utilizator'] === $administrator['id'],
            ];
        }

        $this->raspundeJson(200, [
            'succes' => true,
            'cereri_cont' => (new CerereCont($pdo))->inAsteptare(),
            'cereri_resetare' => (new ResetareParola($pdo))->solicitate(),
            'conturi' => $conturi,
            'roluri' => Drepturi::roluriPentruInterfata(),
        ]);
    }

    // ----- Cereri de cont -----

    // Aprobarea creeaza contul, cu rolul si departamentul alese de administrator.
    public function aprobaCerereCont(): void
    {
        $administrator = $this->cereRol(self::ROLURI_PERMISE);

        if ($administrator === null) {
            return;
        }

        $rolAles = $this->rolSiDepartamentAlese();

        if ($rolAles === null) {
            return;
        }

        $pdo = $this->bazaDeDate();
        $cereri = new CerereCont($pdo);
        $utilizatori = new Utilizator($pdo);

        // Totul sau nimic: contul se creeaza si cererea se inchide impreuna.
        $pdo->beginTransaction();

        try {
            $cerere = $cereri->gasesteInAsteptarePentruProcesare($this->campNumar('id_cerere'));

            if ($cerere === null) {
                $pdo->rollBack();
                $this->raspundeEroare(404, 'Cererea nu mai este în așteptare.');
                return;
            }

            if ($utilizatori->existaEmail($cerere['email'])) {
                $pdo->rollBack();
                $this->raspundeEroare(409, 'Există deja un cont cu această adresă de e-mail. Respinge cererea.');
                return;
            }

            $utilizatori->creeaza(
                $cerere['nume_complet'],
                $cerere['email'],
                $cerere['parola_hash'],
                $rolAles['id_rol'],
                $rolAles['departament']
            );
            $cereri->marcheazaAprobata((int) $cerere['id_cerere'], $administrator['id']);

            $pdo->commit();
        } catch (Throwable $eroare) {
            $pdo->rollBack();
            throw $eroare;
        }

        $this->raspundeJson(200, [
            'succes' => true,
            'mesaj' => 'Contul pentru ' . $cerere['nume_complet'] . ' a fost creat.',
        ]);
    }

    public function respingeCerereCont(): void
    {
        $administrator = $this->cereRol(self::ROLURI_PERMISE);

        if ($administrator === null) {
            return;
        }

        $respinsa = (new CerereCont($this->bazaDeDate()))->respinge(
            $this->campNumar('id_cerere'),
            $administrator['id']
        );

        if (!$respinsa) {
            $this->raspundeEroare(404, 'Cererea nu mai este în așteptare.');
            return;
        }

        $this->raspundeJson(200, ['succes' => true, 'mesaj' => 'Cererea a fost respinsă.']);
    }

    // ----- Conturi existente -----

    public function actualizeazaCont(): void
    {
        $administrator = $this->cereRol(self::ROLURI_PERMISE);

        if ($administrator === null) {
            return;
        }

        $cont = $this->contDinCerere();

        if ($cont === null) {
            return;
        }

        // Altfel aplicatia ar putea ramane fara niciun administrator general.
        if ((int) $cont['id_utilizator'] === $administrator['id']) {
            $this->raspundeEroare(422, 'Nu îți poți schimba propriul rol.');
            return;
        }

        $rolAles = $this->rolSiDepartamentAlese();

        if ($rolAles === null) {
            return;
        }

        (new Utilizator($this->bazaDeDate()))->actualizeazaRolSiDepartament(
            (int) $cont['id_utilizator'],
            $rolAles['id_rol'],
            $rolAles['departament']
        );

        $this->raspundeJson(200, [
            'succes' => true,
            'mesaj' => 'Contul pentru ' . $cont['nume_complet'] . ' a fost actualizat.',
        ]);
    }

    // Un cont dezactivat nu se mai poate autentifica, iar sesiunile lui deschise
    // sunt refuzate la urmatoarea cerere. Istoricul lui ramane in baza de date.
    public function schimbaStareCont(): void
    {
        $administrator = $this->cereRol(self::ROLURI_PERMISE);

        if ($administrator === null) {
            return;
        }

        $cont = $this->contDinCerere();

        if ($cont === null) {
            return;
        }

        if ((int) $cont['id_utilizator'] === $administrator['id']) {
            $this->raspundeEroare(422, 'Nu îți poți dezactiva propriul cont.');
            return;
        }

        $activ = $this->camp('activ') === '1';
        $pdo = $this->bazaDeDate();

        (new Utilizator($pdo))->seteazaActiv((int) $cont['id_utilizator'], $activ);

        if (!$activ) {
            (new ResetareParola($pdo))->anuleazaDeschise((int) $cont['id_utilizator']);
        }

        $this->raspundeJson(200, [
            'succes' => true,
            'mesaj' => 'Contul pentru ' . $cont['nume_complet']
                . ($activ ? ' a fost activat.' : ' a fost dezactivat.'),
        ]);
    }

    // ----- Resetarea parolei -----

    // Genereaza un link de resetare, fie ca raspuns la o cerere a utilizatorului,
    // fie direct din lista de conturi. Linkul este afisat o singura data:
    // administratorul il transmite persoanei, iar aceasta isi alege singura parola.
    public function genereazaLinkResetare(): void
    {
        $administrator = $this->cereRol(self::ROLURI_PERMISE);

        if ($administrator === null) {
            return;
        }

        $pdo = $this->bazaDeDate();
        $resetari = new ResetareParola($pdo);
        $minute = (int) $this->setari()['minute_valabilitate_link'];

        $idResetare = $this->campNumar('id_resetare');
        $cerere = $idResetare > 0 ? $resetari->gasesteSolicitata($idResetare) : null;

        if ($idResetare > 0 && $cerere === null) {
            $this->raspundeEroare(404, 'Cererea nu mai așteaptă răspuns.');
            return;
        }

        $idUtilizator = $cerere !== null ? (int) $cerere['id_utilizator'] : $this->campNumar('id_utilizator');
        $cont = (new Utilizator($pdo))->gasesteDupaId($idUtilizator);

        if ($cont === null || (int) $cont['activ'] !== 1) {
            $this->raspundeEroare(404, 'Contul nu există sau nu este activ.');
            return;
        }

        $cod = bin2hex(random_bytes(32));
        $codHash = ResetareParola::amprentaCodului($cod);

        $pdo->beginTransaction();

        try {
            if ($cerere !== null) {
                $resetari->anuleazaLinkuri($idUtilizator);
                $resetari->ataseazaLink($idResetare, $codHash, $administrator['id'], $minute);
            } else {
                $resetari->anuleazaDeschise($idUtilizator);
                $resetari->creeazaCuLink($idUtilizator, $codHash, $administrator['id'], $minute);
            }

            $pdo->commit();
        } catch (Throwable $eroare) {
            $pdo->rollBack();
            throw $eroare;
        }

        $this->raspundeJson(200, [
            'succes' => true,
            'nume' => $cont['nume_complet'],
            'cale' => 'index.php?ruta=parola-noua&cod=' . $cod,
            'minute' => $minute,
        ]);
    }

    public function respingeCerereResetare(): void
    {
        $administrator = $this->cereRol(self::ROLURI_PERMISE);

        if ($administrator === null) {
            return;
        }

        $respinsa = (new ResetareParola($this->bazaDeDate()))->respinge(
            $this->campNumar('id_resetare'),
            $administrator['id']
        );

        if (!$respinsa) {
            $this->raspundeEroare(404, 'Cererea nu mai așteaptă răspuns.');
            return;
        }

        $this->raspundeJson(200, ['succes' => true, 'mesaj' => 'Cererea a fost respinsă.']);
    }

    // ----- Functii ajutatoare -----

    // Citeste contul indicat in cerere. Intoarce null dupa ce a trimis eroarea.
    private function contDinCerere(): ?array
    {
        $cont = (new Utilizator($this->bazaDeDate()))->gasesteDupaId($this->campNumar('id_utilizator'));

        if ($cont === null) {
            $this->raspundeEroare(404, 'Contul nu există.');
        }

        return $cont;
    }

    // Verifica rolul si departamentul alese de administrator.
    // Intoarce ['id_rol' => ..., 'departament' => ...] sau null dupa ce a trimis eroarea.
    private function rolSiDepartamentAlese(): ?array
    {
        $rol = $this->camp('rol');

        if (!Drepturi::rolCunoscut($rol)) {
            $this->raspundeEroare(422, 'Alege un rol.');
            return null;
        }

        // Rolurile fara departament (administratorul general) primesc null.
        $departament = Drepturi::departamentePermise($rol) === [] ? null : $this->camp('departament');

        if (!Drepturi::departamentValid($rol, $departament)) {
            $this->raspundeEroare(422, 'Alege un departament potrivit pentru acest rol.');
            return null;
        }

        $idRol = (new Rol($this->bazaDeDate()))->idDupaDenumire($rol);

        if ($idRol === null) {
            $this->raspundeEroare(
                409,
                'Rolul lipsește din baza de date. Rulează fișierul database/autentificare.sql.'
            );
            return null;
        }

        return ['id_rol' => $idRol, 'departament' => $departament];
    }
}
