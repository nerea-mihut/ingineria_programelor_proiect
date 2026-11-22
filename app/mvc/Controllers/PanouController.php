<?php

declare(strict_types=1);

// CONTROLLER: pagina principala, disponibila doar dupa autentificare.
// Pagina este aceeasi pentru toate rolurile; modulele afisate difera
// si vin de la ruta "sesiune".
final class PanouController extends Controller
{
    public function afiseaza(): void
    {
        if ($this->cereAutentificare() === null) {
            return;
        }

        $this->afiseazaView('panou/panou.html');
    }
}
