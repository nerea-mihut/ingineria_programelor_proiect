<?php

namespace App\Http\Controllers;

use App\Http\Middleware\VerificaAutentificarea;
use App\Models\CerereCont;
use App\Models\Drepturi;
use App\Models\IncercareAcces;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// CONTROLLER: autentificarea, sesiunea curenta si deconectarea.
class AutentificareController extends Controller
{
    // Folosit cand e-mailul nu exista, ca verificarea sa dureze la fel
    // ca pentru un cont real. Nu corespunde niciunei parole cunoscute.
    private const HASH_FICTIV = '$2y$10$Xv8zPgdJd9QIOBMVrEI47OBy5YkP7I9aZ01jgSX2leXhEIBQnkD.C';

    private const LUNGIME_MAXIMA_PAROLA = 1024;

    public function afiseazaLogin(Request $request): Response
    {
        [$utilizator] = VerificaAutentificarea::utilizatorValid($request);

        if ($utilizator !== null) {
            return redirect()->route(Drepturi::paginaStart($utilizator->denumireRol()));
        }

        return response()->view('acces_cont.login');
    }

    public function autentifica(Request $request): JsonResponse
    {
        $email = strtolower($this->camp($request, 'email'));
        $parola = $this->campParola($request, 'password');

        if (!$this->emailValid($email) || $parola === '' || strlen($parola) > self::LUNGIME_MAXIMA_PAROLA) {
            return $this->raspundeEroare(422, 'Completează o adresă de e-mail validă și parola.');
        }

        $setari = $this->setari();
        $tip = IncercareAcces::AUTENTIFICARE;
        $ip = (string) $request->ip();

        // Prea multe greseli recente pentru acest e-mail sau de la aceasta adresa?
        $secundeBlocare = max(
            IncercareAcces::secundeBlocareEmail($tip, $email, $setari['incercari_pe_email'], $setari['minute_blocare']),
            IncercareAcces::secundeBlocareIp($tip, $ip, $setari['incercari_pe_ip'], $setari['minute_blocare'])
        );

        if ($secundeBlocare > 0) {
            return $this->raspundeBlocat($secundeBlocare);
        }

        $utilizator = User::gasesteDupaEmail($email);

        // Fara cont: poate exista o cerere de cont inca neaprobata.
        $cerere = $utilizator === null ? CerereCont::ultimaNeaprobata($email) : null;

        // Parola introdusa se compara cu hash-ul salvat, nu cu o parola in clar.
        $hash = $utilizator?->parola_hash ?? $cerere?->parola_hash ?? self::HASH_FICTIV;
        $parolaCorecta = password_verify($parola, (string) $hash);

        if (!$parolaCorecta || ($utilizator === null && $cerere === null)) {
            IncercareAcces::inregistreaza($tip, $email, $ip, false);

            return $this->raspundeDateGresite($email);
        }

        // De aici parola este corecta, deci putem spune exact care este situatia.
        if ($utilizator === null) {
            return $this->raspundeEroare(403, $cerere->stare === 'in_asteptare'
                ? 'Contul tău așteaptă aprobarea administratorului.'
                : 'Cererea ta de cont a fost respinsă. Contactează administratorul.');
        }

        if (!$utilizator->esteActiv()) {
            return $this->raspundeEroare(403, 'Contul nu este activ. Contactează administratorul.');
        }

        IncercareAcces::stergeEsecuriEmail($tip, $email);
        IncercareAcces::stergeVechi();

        // Daca PHP recomanda intre timp un hash mai puternic, il inlocuim acum.
        if (password_needs_rehash($utilizator->parola_hash, PASSWORD_DEFAULT)) {
            $utilizator->actualizeazaParola(password_hash($parola, PASSWORD_DEFAULT));
        }

        VerificaAutentificarea::deschideSesiunea($request, $utilizator);

        // Fiecare rol are pagina lui de start (config/roluri.php).
        return response()->json([
            'succes' => true,
            'destinatie' => route(Drepturi::paginaStart($utilizator->denumireRol())),
        ]);
    }

    // Spune paginii cine este autentificat si ce module are (fara date sensibile).
    public function sesiuneCurenta(Request $request): JsonResponse
    {
        $utilizator = $request->user()->date();

        return response()->json([
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

    public function deconecteaza(Request $request): JsonResponse
    {
        // Sesiunea este invalidata imediat (RNF-003 din SRS).
        VerificaAutentificarea::inchideSesiunea($request);

        return response()->json(['succes' => true]);
    }

    // Acelasi mesaj pentru e-mail inexistent si parola gresita,
    // ca sa nu se poata afla ce adrese au cont.
    private function raspundeDateGresite(string $email): JsonResponse
    {
        $setari = $this->setari();
        $tip = IncercareAcces::AUTENTIFICARE;

        $secundeBlocare = IncercareAcces::secundeBlocareEmail(
            $tip,
            $email,
            $setari['incercari_pe_email'],
            $setari['minute_blocare']
        );

        if ($secundeBlocare > 0) {
            return $this->raspundeBlocat($secundeBlocare);
        }

        $ramase = $setari['incercari_pe_email']
            - IncercareAcces::numaraEsecuriEmail($tip, $email, $setari['minute_blocare']);

        $mesaj = 'E-mail sau parolă incorectă.';

        if ($ramase === 1) {
            $mesaj .= ' Mai ai o încercare.';
        } elseif ($ramase === 2) {
            $mesaj .= ' Mai ai 2 încercări.';
        }

        return $this->raspundeEroare(401, $mesaj);
    }

    private function raspundeBlocat(int $secunde): JsonResponse
    {
        $minute = (int) ceil($secunde / 60);

        return $this->raspundeEroare(429, 'Prea multe încercări nereușite. Încearcă din nou peste '
            . ($minute === 1 ? 'un minut' : $minute . ' minute') . '.')
            ->header('Retry-After', (string) $secunde);
    }
}
