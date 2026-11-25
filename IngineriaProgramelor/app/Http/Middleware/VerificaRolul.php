<?php

namespace App\Http\Middleware;

use App\Models\Drepturi;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// MIDDLEWARE: lasa sa treaca doar anumite roluri. Se pune DUPA 'autentificat'.
// Administratorul general are acces peste tot.
//
// Exemple in routes/web.php:
//     ->middleware(['autentificat', 'rol:operator_cnc'])
//     ->middleware(['autentificat', 'rol:mecanic,administrator_departament'])
//     ->middleware(['autentificat', 'rol:administrator_departament,departament=Mentenanță'])
class VerificaRolul
{
    public function handle(Request $request, Closure $next, string ...$parametri): Response
    {
        $roluri = [];
        $departament = null;

        foreach ($parametri as $parametru) {
            if (str_starts_with($parametru, 'departament=')) {
                $departament = substr($parametru, strlen('departament='));
            } else {
                $roluri[] = $parametru;
            }
        }

        $utilizator = Auth::user()?->date();

        if ($utilizator !== null && Drepturi::areAcces($utilizator, $roluri, $departament)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'succes' => false,
                'mesaj' => 'Nu ai dreptul să faci această operație.',
            ], 403);
        }

        return response()->view('erori.acces-interzis', [], 403);
    }
}
