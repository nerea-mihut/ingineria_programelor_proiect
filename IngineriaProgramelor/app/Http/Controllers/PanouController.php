<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

// CONTROLLER: pagina principala, disponibila doar dupa autentificare.
// Pagina este aceeasi pentru toate rolurile; modulele afisate difera
// si vin de la ruta "sesiune".
class PanouController extends Controller
{
    public function afiseaza(): View
    {
        return view('panou.panou');
    }
}
