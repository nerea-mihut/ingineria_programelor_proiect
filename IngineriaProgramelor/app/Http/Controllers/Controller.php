<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Functii comune pentru toate controllerele.
abstract class Controller
{
    // Regulile din config/securitate.php.
    protected function setari(): array
    {
        return config('securitate');
    }

    // Raspunsul obisnuit pentru o operatie care nu a reusit.
    protected function raspundeEroare(int $codHttp, string $mesaj): JsonResponse
    {
        return response()->json(['succes' => false, 'mesaj' => $mesaj], $codHttp);
    }

    // Un camp text din formular, fara spatiile de la capete.
    protected function camp(Request $request, string $nume): string
    {
        $valoare = $request->input($nume);

        return is_string($valoare) ? trim($valoare) : '';
    }

    // O parola din formular, exact asa cum a fost scrisa.
    protected function campParola(Request $request, string $nume): string
    {
        $valoare = $request->input($nume);

        return is_string($valoare) ? $valoare : '';
    }

    // Un numar intreg pozitiv din formular (de exemplu un id) sau 0 daca lipseste.
    protected function campNumar(Request $request, string $nume): int
    {
        $valoare = $this->camp($request, $nume);

        return ctype_digit($valoare) && strlen($valoare) <= 9 ? (int) $valoare : 0;
    }

    protected function emailValid(string $email): bool
    {
        return $email !== ''
            && strlen($email) <= 254
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
