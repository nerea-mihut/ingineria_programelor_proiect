<?php

namespace App\Http\Controllers;

use App\Http\Middleware\VerificaAutentificarea;
use App\Models\CerereCont;
use App\Models\IncercareAcces;
use App\Models\ResetareParola;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

// CONTROLLER: cererea de cont, parola uitata si schimbarea parolei.
class ContController extends Controller
{
    private const MESAJ_LINK_NEVALID = 'Linkul nu este valid sau a expirat. Cere administratorului unul nou.';

    // ----- Creare cont -----

    public function afiseazaCreareCont(): View
    {
        return view('acces_cont.creare-cont');
    }

    // Formularul "Creare cont" nu creeaza direct un cont, ci o cerere.
    // Rolul si departamentul le alege administratorul general la aprobare (RF-001).
    public function solicitaCont(Request $request): JsonResponse
    {
        $nume = (string) preg_replace('/\s+/u', ' ', $this->camp($request, 'name'));
        $email = strtolower($this->camp($request, 'email'));
        $parola = $this->campParola($request, 'password');

        if (preg_match('/^\p{L}[\p{L}\p{M} .\'’-]{1,119}$/u', $nume) !== 1) {
            return $this->raspundeEroare(422, 'Scrie numele complet, folosind doar litere, spații și cratime.');
        }

        if (!$this->emailValid($email)) {
            return $this->raspundeEroare(422, 'Scrie o adresă de e-mail validă.');
        }

        $eroareParola = $this->eroareParolaNoua($parola, $this->campParola($request, 'confirm'), $email);

        if ($eroareParola !== null) {
            return $this->raspundeEroare(422, $eroareParola);
        }

        if (!$this->cererePermisa($request, IncercareAcces::CERERE_CONT, $email)) {
            return $this->raspundeEroare(429, 'Ai trimis prea multe cereri. Încearcă din nou mai târziu.');
        }

        // Hash-ul se calculeaza de fiecare data, ca raspunsul sa dureze la fel
        // indiferent daca adresa are sau nu deja un cont.
        $parolaHash = password_hash($parola, PASSWORD_DEFAULT);

        if (!User::existaEmail($email) && !CerereCont::existaInAsteptare($email)) {
            CerereCont::creeaza($nume, $email, $parolaHash);
        }

        // Acelasi raspuns si cand adresa are deja cont: nu dezvaluim ce adrese exista.
        return response()->json([
            'succes' => true,
            'mesaj' => 'Cererea a fost trimisă. Vei putea intra în aplicație după ce administratorul '
                . 'îți aprobă contul. Dacă ai deja un cont cu această adresă, folosește autentificarea.',
        ]);
    }

    // ----- Parola uitata -----

    public function afiseazaRecuperareParola(): View
    {
        return view('acces_cont.recuperare-parola');
    }

    // Cererea ajunge la administratorul general, care genereaza un link de resetare.
    public function solicitaResetare(Request $request): JsonResponse
    {
        $email = strtolower($this->camp($request, 'email'));

        if (!$this->emailValid($email)) {
            return $this->raspundeEroare(422, 'Scrie o adresă de e-mail validă.');
        }

        if (!$this->cererePermisa($request, IncercareAcces::CERERE_RESETARE, $email)) {
            return $this->raspundeEroare(429, 'Ai trimis prea multe cereri. Încearcă din nou mai târziu.');
        }

        $utilizator = User::gasesteDupaEmail($email);

        if (
            $utilizator !== null
            && $utilizator->esteActiv()
            && !ResetareParola::areCerereDeschisa((int) $utilizator->id_utilizator)
        ) {
            ResetareParola::creeazaCerere((int) $utilizator->id_utilizator);
        }

        // Acelasi raspuns si cand adresa nu are cont.
        return response()->json([
            'succes' => true,
            'mesaj' => 'Dacă adresa are un cont activ, cererea a ajuns la administrator. '
                . 'Vei primi de la el un link pentru o parolă nouă.',
        ]);
    }

    // ----- Parola noua, cu linkul primit de la administrator -----

    public function afiseazaParolaNoua(): Response
    {
        // Adresa paginii contine codul din link: nu o pastram si nu o trimitem mai departe.
        return response()
            ->view('acces_cont.parola-noua')
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    // Pagina intreaba, la deschidere, daca linkul mai este bun.
    public function verificaLink(Request $request): JsonResponse
    {
        if ($this->linkuriBlocate($request)) {
            return $this->raspundeEroare(429, 'Prea multe încercări nereușite. Încearcă din nou mai târziu.');
        }

        $cod = is_string($request->query('cod')) ? $request->query('cod') : '';

        if ($this->gasesteLinkValid($request, $cod) === null) {
            return response()->json(['valid' => false, 'mesaj' => self::MESAJ_LINK_NEVALID]);
        }

        return response()->json(['valid' => true]);
    }

    public function seteazaParolaNoua(Request $request): JsonResponse
    {
        if ($this->linkuriBlocate($request)) {
            return $this->raspundeEroare(429, 'Prea multe încercări nereușite. Încearcă din nou mai târziu.');
        }

        $resetare = $this->gasesteLinkValid($request, $this->camp($request, 'cod'));

        if ($resetare === null) {
            return $this->raspundeEroare(400, self::MESAJ_LINK_NEVALID);
        }

        $parola = $this->campParola($request, 'password');
        $eroareParola = $this->eroareParolaNoua($parola, $this->campParola($request, 'confirm'), $resetare->email);

        if ($eroareParola !== null) {
            return $this->raspundeEroare(422, $eroareParola);
        }

        $idUtilizator = (int) $resetare->id_utilizator;

        // Totul sau nimic: linkul se consuma doar daca parola a fost schimbata.
        $folosit = DB::transaction(function () use ($resetare, $idUtilizator, $parola) {
            if (!ResetareParola::marcheazaFolosita((int) $resetare->id_resetare)) {
                return false;
            }

            User::findOrFail($idUtilizator)->actualizeazaParola(password_hash($parola, PASSWORD_DEFAULT));
            ResetareParola::anuleazaDeschise($idUtilizator);

            return true;
        });

        if (!$folosit) {
            return $this->raspundeEroare(400, self::MESAJ_LINK_NEVALID);
        }

        // Cu o parola noua, blocarea dupa greseli repetate nu mai are rost.
        IncercareAcces::stergeEsecuriEmail(IncercareAcces::AUTENTIFICARE, $resetare->email);

        return response()->json([
            'succes' => true,
            'destinatie' => route('login', ['motiv' => 'parola-schimbata']),
        ]);
    }

    // ----- Schimbarea parolei de catre utilizatorul autentificat -----

    public function afiseazaSchimbareParola(): View
    {
        return view('cont.schimbare-parola');
    }

    public function schimbaParola(Request $request): JsonResponse
    {
        /** @var User $cont */
        $cont = $request->user();
        $email = $cont->email;

        $setari = $this->setari();
        $tip = IncercareAcces::SCHIMBARE_PAROLA;

        $secundeBlocare = IncercareAcces::secundeBlocareEmail(
            $tip,
            $email,
            $setari['incercari_pe_email'],
            $setari['minute_blocare']
        );

        if ($secundeBlocare > 0) {
            return $this->raspundeEroare(429, 'Prea multe încercări nereușite. Încearcă din nou mai târziu.');
        }

        $parolaActuala = $this->campParola($request, 'current');

        // Cine schimba parola trebuie sa o stie pe cea actuala,
        // chiar daca este deja autentificat.
        if (!password_verify($parolaActuala, $cont->parola_hash)) {
            IncercareAcces::inregistreaza($tip, $email, (string) $request->ip(), false);

            return $this->raspundeEroare(422, 'Parola actuală nu este corectă.');
        }

        $parolaNoua = $this->campParola($request, 'password');
        $eroareParola = $this->eroareParolaNoua($parolaNoua, $this->campParola($request, 'confirm'), $email);

        if ($eroareParola === null && $parolaNoua === $parolaActuala) {
            $eroareParola = 'Parola nouă trebuie să fie diferită de cea actuală.';
        }

        if ($eroareParola !== null) {
            return $this->raspundeEroare(422, $eroareParola);
        }

        $cont->actualizeazaParola(password_hash($parolaNoua, PASSWORD_DEFAULT));

        ResetareParola::anuleazaDeschise((int) $cont->id_utilizator);
        IncercareAcces::stergeEsecuriEmail($tip, $email);

        // Sesiunea aceasta ramane deschisa, cu noua parola.
        // Celelalte sesiuni ale contului nu mai sunt acceptate.
        VerificaAutentificarea::deschideSesiunea($request, $cont);

        return response()->json([
            'succes' => true,
            'mesaj' => 'Parola a fost schimbată.',
            'token' => csrf_token(),
        ]);
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
    private function cererePermisa(Request $request, string $tip, string $email): bool
    {
        $ip = (string) $request->ip();

        if (IncercareAcces::numaraPentruIp($tip, $ip, 60) >= $this->setari()['cereri_pe_ora']) {
            return false;
        }

        IncercareAcces::inregistreaza($tip, $email, $ip, true);

        return true;
    }

    // Opreste ghicirea codurilor din linkuri.
    private function linkuriBlocate(Request $request): bool
    {
        $setari = $this->setari();

        return IncercareAcces::secundeBlocareIp(
            IncercareAcces::LINK_RESETARE,
            (string) $request->ip(),
            $setari['incercari_pe_ip'],
            $setari['minute_blocare']
        ) > 0;
    }

    // Cauta linkul dupa codul primit. Un cod gresit se trece in jurnal.
    private function gasesteLinkValid(Request $request, string $cod): ?object
    {
        $resetare = preg_match('/^[a-f0-9]{64}$/', $cod) === 1
            ? ResetareParola::gasesteLinkValid(ResetareParola::amprentaCodului($cod))
            : null;

        if ($resetare === null) {
            IncercareAcces::inregistreaza(IncercareAcces::LINK_RESETARE, null, (string) $request->ip(), false);
        }

        return $resetare;
    }
}
