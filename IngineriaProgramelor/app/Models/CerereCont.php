<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// MODEL: cererile de cont trimise din pagina "Creare cont" (tabelul "cerere_cont").
// Contul apare in tabelul "utilizator" abia dupa ce administratorul general aproba cererea.
class CerereCont extends Model
{
    protected $table = 'cerere_cont';

    protected $primaryKey = 'id_cerere';

    public $timestamps = false;

    protected $fillable = ['nume_complet', 'email', 'parola_hash'];

    protected $hidden = ['parola_hash'];

    public static function creeaza(string $numeComplet, string $email, string $parolaHash): void
    {
        static::create([
            'nume_complet' => $numeComplet,
            'email' => $email,
            'parola_hash' => $parolaHash,
        ]);
    }

    public static function existaInAsteptare(string $email): bool
    {
        return static::where('email', $email)->where('stare', 'in_asteptare')->exists();
    }

    // Cea mai noua cerere inca neaprobata pentru un e-mail. Folosita la autentificare,
    // ca omul sa afle ca asteapta aprobarea sau ca cererea a fost respinsa.
    public static function ultimaNeaprobata(string $email): ?object
    {
        return DB::table('cerere_cont')
            ->select('stare', 'parola_hash')
            ->where('email', $email)
            ->whereIn('stare', ['in_asteptare', 'respinsa'])
            ->orderByDesc('id_cerere')
            ->first();
    }

    // Cererile care asteapta decizia administratorului. Fara parole.
    public static function inAsteptare(): array
    {
        return DB::table('cerere_cont')
            ->selectRaw("id_cerere, nume_complet, email, DATE_FORMAT(creata_la, '%d.%m.%Y %H:%i') AS creata_la")
            ->where('stare', 'in_asteptare')
            ->orderBy('id_cerere')
            ->get()
            ->all();
    }

    // Citeste cererea si o blocheaza pana la sfarsitul tranzactiei,
    // ca doi administratori sa nu o poata procesa in acelasi timp.
    public static function gasesteInAsteptarePentruProcesare(int $idCerere): ?object
    {
        return DB::table('cerere_cont')
            ->select('id_cerere', 'nume_complet', 'email', 'parola_hash')
            ->where('id_cerere', $idCerere)
            ->where('stare', 'in_asteptare')
            ->lockForUpdate()
            ->first();
    }

    // Dupa aprobare parola nu mai ramane in cerere: a fost mutata in contul creat.
    public static function marcheazaAprobata(int $idCerere, int $idAdministrator): void
    {
        DB::table('cerere_cont')
            ->where('id_cerere', $idCerere)
            ->update([
                'stare' => 'aprobata',
                'parola_hash' => '',
                'procesata_la' => DB::raw('NOW()'),
                'procesata_de' => $idAdministrator,
            ]);
    }

    // Intoarce false daca cererea nu mai era in asteptare.
    public static function respinge(int $idCerere, int $idAdministrator): bool
    {
        return DB::table('cerere_cont')
            ->where('id_cerere', $idCerere)
            ->where('stare', 'in_asteptare')
            ->update([
                'stare' => 'respinsa',
                'procesata_la' => DB::raw('NOW()'),
                'procesata_de' => $idAdministrator,
            ]) === 1;
    }
}
