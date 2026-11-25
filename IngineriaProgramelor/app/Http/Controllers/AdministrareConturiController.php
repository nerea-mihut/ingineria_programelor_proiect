<?php

namespace App\Http\Controllers;

use App\Models\CerereCont;
use App\Models\Drepturi;
use App\Models\ResetareParola;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// CONTROLLER: administrarea conturilor.
// Doar administratorul general gestioneaza conturile si drepturile (RF-002 din SRS).
// Accesul este verificat in routes/web.php cu middleware-ul 'rol:administrator_general'.
class AdministrareConturiController extends Controller
{
    public function afiseaza(): View
    {
        return view('administrare.conturi');
    }

    // Tot ce are nevoie pagina: cererile in asteptare, conturile si rolurile posibile.
    public function date(Request $request): JsonResponse
    {
        $idAdministrator = (int) $request->user()->id_utilizator;
        $conturi = [];

        foreach (User::with('rol')->orderBy('nume_complet')->orderBy('id_utilizator')->get() as $cont) {
            $conturi[] = [
                'id' => (int) $cont->id_utilizator,
                'nume' => $cont->nume_complet,
                'email' => $cont->email,
                'rol' => $cont->denumireRol(),
                'rol_eticheta' => Drepturi::eticheta($cont->denumireRol()),
                'departament' => $cont->departament,
                'activ' => $cont->esteActiv(),
                'propriu' => (int) $cont->id_utilizator === $idAdministrator,
            ];
        }

        return response()->json([
            'succes' => true,
            'cereri_cont' => CerereCont::inAsteptare(),
            'cereri_resetare' => ResetareParola::solicitate(),
            'conturi' => $conturi,
            'roluri' => Drepturi::roluriPentruInterfata(),
        ]);
    }

    // ----- Cereri de cont -----

    // Aprobarea creeaza contul, cu rolul si departamentul alese de administrator.
    public function aprobaCerereCont(Request $request): JsonResponse
    {
        $rolAles = $this->rolSiDepartamentAlese($request);

        if ($rolAles instanceof JsonResponse) {
            return $rolAles;
        }

        $idAdministrator = (int) $request->user()->id_utilizator;
        $idCerere = $this->campNumar($request, 'id_cerere');

        // Totul sau nimic: contul se creeaza si cererea se inchide impreuna.
        return DB::transaction(function () use ($idCerere, $idAdministrator, $rolAles) {
            $cerere = CerereCont::gasesteInAsteptarePentruProcesare($idCerere);

            if ($cerere === null) {
                return $this->raspundeEroare(404, 'Cererea nu mai este în așteptare.');
            }

            if (User::existaEmail($cerere->email)) {
                return $this->raspundeEroare(409, 'Există deja un cont cu această adresă de e-mail. Respinge cererea.');
            }

            User::creeazaCont(
                $cerere->nume_complet,
                $cerere->email,
                $cerere->parola_hash,
                $rolAles['id_rol'],
                $rolAles['departament']
            );
            CerereCont::marcheazaAprobata((int) $cerere->id_cerere, $idAdministrator);

            return response()->json([
                'succes' => true,
                'mesaj' => 'Contul pentru ' . $cerere->nume_complet . ' a fost creat.',
            ]);
        });
    }

    public function respingeCerereCont(Request $request): JsonResponse
    {
        $respinsa = CerereCont::respinge(
            $this->campNumar($request, 'id_cerere'),
            (int) $request->user()->id_utilizator
        );

        if (!$respinsa) {
            return $this->raspundeEroare(404, 'Cererea nu mai este în așteptare.');
        }

        return response()->json(['succes' => true, 'mesaj' => 'Cererea a fost respinsă.']);
    }

    // ----- Conturi existente -----

    public function actualizeazaCont(Request $request): JsonResponse
    {
        $cont = User::find($this->campNumar($request, 'id_utilizator'));

        if ($cont === null) {
            return $this->raspundeEroare(404, 'Contul nu există.');
        }

        // Altfel aplicatia ar putea ramane fara niciun administrator general.
        if ((int) $cont->id_utilizator === (int) $request->user()->id_utilizator) {
            return $this->raspundeEroare(422, 'Nu îți poți schimba propriul rol.');
        }

        $rolAles = $this->rolSiDepartamentAlese($request);

        if ($rolAles instanceof JsonResponse) {
            return $rolAles;
        }

        $cont->id_rol = $rolAles['id_rol'];
        $cont->departament = $rolAles['departament'];
        $cont->save();

        return response()->json([
            'succes' => true,
            'mesaj' => 'Contul pentru ' . $cont->nume_complet . ' a fost actualizat.',
        ]);
    }

    // Un cont dezactivat nu se mai poate autentifica, iar sesiunile lui deschise
    // sunt refuzate la urmatoarea cerere. Istoricul lui ramane in baza de date.
    public function schimbaStareCont(Request $request): JsonResponse
    {
        $cont = User::find($this->campNumar($request, 'id_utilizator'));

        if ($cont === null) {
            return $this->raspundeEroare(404, 'Contul nu există.');
        }

        if ((int) $cont->id_utilizator === (int) $request->user()->id_utilizator) {
            return $this->raspundeEroare(422, 'Nu îți poți dezactiva propriul cont.');
        }

        $activ = $this->camp($request, 'activ') === '1';

        $cont->activ = $activ ? 1 : 0;
        $cont->save();

        if (!$activ) {
            ResetareParola::anuleazaDeschise((int) $cont->id_utilizator);
        }

        return response()->json([
            'succes' => true,
            'mesaj' => 'Contul pentru ' . $cont->nume_complet
                . ($activ ? ' a fost activat.' : ' a fost dezactivat.'),
        ]);
    }

    // ----- Resetarea parolei -----

    // Genereaza un link de resetare, fie ca raspuns la o cerere a utilizatorului,
    // fie direct din lista de conturi. Linkul este afisat o singura data:
    // administratorul il transmite persoanei, iar aceasta isi alege singura parola.
    public function genereazaLinkResetare(Request $request): JsonResponse
    {
        $idAdministrator = (int) $request->user()->id_utilizator;
        $minute = (int) $this->setari()['minute_valabilitate_link'];

        $idResetare = $this->campNumar($request, 'id_resetare');
        $cerere = $idResetare > 0 ? ResetareParola::gasesteSolicitata($idResetare) : null;

        if ($idResetare > 0 && $cerere === null) {
            return $this->raspundeEroare(404, 'Cererea nu mai așteaptă răspuns.');
        }

        $idUtilizator = $cerere !== null
            ? (int) $cerere->id_utilizator
            : $this->campNumar($request, 'id_utilizator');
        $cont = User::find($idUtilizator);

        if ($cont === null || !$cont->esteActiv()) {
            return $this->raspundeEroare(404, 'Contul nu există sau nu este activ.');
        }

        $cod = bin2hex(random_bytes(32));
        $codHash = ResetareParola::amprentaCodului($cod);

        DB::transaction(function () use ($cerere, $idResetare, $idUtilizator, $codHash, $idAdministrator, $minute) {
            if ($cerere !== null) {
                ResetareParola::anuleazaLinkuri($idUtilizator);
                ResetareParola::ataseazaLink($idResetare, $codHash, $idAdministrator, $minute);
            } else {
                ResetareParola::anuleazaDeschise($idUtilizator);
                ResetareParola::creeazaCuLink($idUtilizator, $codHash, $idAdministrator, $minute);
            }
        });

        return response()->json([
            'succes' => true,
            'nume' => $cont->nume_complet,
            'cale' => route('parola-noua', ['cod' => $cod]),
            'minute' => $minute,
        ]);
    }

    public function respingeCerereResetare(Request $request): JsonResponse
    {
        $respinsa = ResetareParola::respinge(
            $this->campNumar($request, 'id_resetare'),
            (int) $request->user()->id_utilizator
        );

        if (!$respinsa) {
            return $this->raspundeEroare(404, 'Cererea nu mai așteaptă răspuns.');
        }

        return response()->json(['succes' => true, 'mesaj' => 'Cererea a fost respinsă.']);
    }

    // ----- Functii ajutatoare -----

    // Verifica rolul si departamentul alese de administrator.
    // Intoarce ['id_rol' => ..., 'departament' => ...] sau raspunsul de eroare.
    private function rolSiDepartamentAlese(Request $request): array|JsonResponse
    {
        $rol = $this->camp($request, 'rol');

        if (!Drepturi::rolCunoscut($rol)) {
            return $this->raspundeEroare(422, 'Alege un rol.');
        }

        // Rolurile fara departament (administratorul general) primesc null.
        $departament = Drepturi::departamentePermise($rol) === [] ? null : $this->camp($request, 'departament');

        if (!Drepturi::departamentValid($rol, $departament)) {
            return $this->raspundeEroare(422, 'Alege un departament potrivit pentru acest rol.');
        }

        $idRol = Rol::idDupaDenumire($rol);

        if ($idRol === null) {
            return $this->raspundeEroare(409, 'Rolul lipsește din baza de date.');
        }

        return ['id_rol' => $idRol, 'departament' => $departament];
    }
}
