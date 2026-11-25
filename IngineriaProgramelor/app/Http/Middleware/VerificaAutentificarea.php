<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// MIDDLEWARE: lasa sa treaca doar cererile unui utilizator autentificat.
// Folosit in routes/web.php cu ->middleware('autentificat').
//
// La fiecare cerere contul este citit din nou din baza de date (RNF-002):
// un cont dezactivat, un rol schimbat sau o parola schimbata au efect imediat.
class VerificaAutentificarea
{
    public function handle(Request $request, Closure $next): Response
    {
        [$utilizator, $motiv] = self::utilizatorValid($request);

        if ($utilizator === null) {
            return self::cereAutentificare($request, $motiv);
        }

        $raspuns = $next($request);

        // Paginile protejate nu se pastreaza in memoria browserului,
        // ca sa nu poata fi revazute dupa deconectare.
        $raspuns->headers->set('Cache-Control', 'no-store, private');

        return $raspuns;
    }

    // Intoarce [utilizatorul, ''] sau [null, motivul pentru care sesiunea s-a inchis].
    public static function utilizatorValid(Request $request): array
    {
        if (!Auth::check()) {
            return [null, ''];
        }

        $secundeInactivitate = time() - (int) $request->session()->get('ultima_activitate', 0);

        if ($secundeInactivitate > (int) config('securitate.minute_inactivitate') * 60) {
            self::inchideSesiunea($request);

            return [null, 'sesiune-expirata'];
        }

        /** @var User $cont */
        $cont = Auth::user();
        $cont->loadMissing('rol');

        $contValid = $cont->esteActiv()
            && hash_equals(
                (string) $request->session()->get('amprenta_parola', ''),
                self::amprentaParolei($cont->parola_hash)
            );

        if (!$contValid) {
            self::inchideSesiunea($request);

            return [null, 'cont-modificat'];
        }

        $request->session()->put('ultima_activitate', time());

        return [$cont, ''];
    }

    // Leaga sesiunea de un utilizator, dupa ce parola a fost verificata.
    public static function deschideSesiunea(Request $request, User $cont): void
    {
        Auth::login($cont);

        // Identificator nou de sesiune dupa autentificare.
        $request->session()->regenerate();
        $request->session()->put('amprenta_parola', self::amprentaParolei($cont->parola_hash));
        $request->session()->put('ultima_activitate', time());
    }

    // Sterge sesiunea de pe server (RNF-003 din SRS).
    public static function inchideSesiunea(Request $request): void
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    // Sesiunea retine o amprenta a parolei. Daca parola se schimba,
    // amprenta nu mai corespunde si sesiunile vechi nu mai sunt acceptate.
    public static function amprentaParolei(string $parolaHash): string
    {
        return hash('sha256', $parolaHash);
    }

    // JavaScript-ul primeste 401 in JSON; o pagina deschisa direct merge la autentificare.
    private static function cereAutentificare(Request $request, string $motiv): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'succes' => false,
                'autentificat' => false,
                'motiv' => $motiv,
                'mesaj' => 'Trebuie să te autentifici.',
            ], 401);
        }

        return redirect()->route('login', $motiv !== '' ? ['motiv' => $motiv] : []);
    }
}
