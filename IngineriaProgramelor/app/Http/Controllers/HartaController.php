<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// CONTROLLER: harta halei, doar vizuala, afisata pe monitor.
class HartaController extends Controller
{
    public function afiseaza(): View
    {
        return view('harta.harta');
    }

    // Starea fiecarui utilaj, pentru actualizarea automata a hartii.
    //   rosu   = oprire neinchisa, comanda in executie inainte de oprire,
    //            necesita reparatie si nicio interventie preluata
    //   galben = interventie in lucru sau in asteptare
    //   verde  = in rest
    public function date(): JsonResponse
    {
        $utilaje = DB::select("
            SELECT e.cod,
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM sesizare s
                        JOIN oprire o ON o.id_sesizare = s.id_sesizare
                        LEFT JOIN interventie i ON i.id_sesizare = s.id_sesizare
                        WHERE s.id_echipament = e.id_echipament
                          AND o.reluata_la IS NULL
                          AND o.executa_comanda_inainte = 1
                          AND o.necesita_reparatie = 1
                          AND i.id_interventie IS NULL
                    ) THEN 'rosu'
                    WHEN EXISTS (
                        SELECT 1
                        FROM sesizare s
                        JOIN interventie i ON i.id_sesizare = s.id_sesizare
                        WHERE s.id_echipament = e.id_echipament
                          AND i.stare IN ('in_lucru', 'in_asteptare')
                    ) THEN 'galben'
                    ELSE 'verde'
                END AS stare
            FROM echipament e
            WHERE e.cod IS NOT NULL
            ORDER BY e.id_echipament
        ");

        return response()
            ->json([
                'succes' => true,
                'actualizat' => now()->format('H:i:s'),
                'utilaje' => $utilaje,
            ])
            ->header('Cache-Control', 'no-store');
    }
}
