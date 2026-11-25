<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// MODEL: cererile si linkurile de resetare a parolei (tabelul "resetare_parola").
//
// Linkul contine un cod aleator. In baza se pastreaza doar amprenta lui (SHA-256),
// asa ca cine citeste tabelul nu poate reconstitui linkul.
class ResetareParola extends Model
{
    protected $table = 'resetare_parola';

    protected $primaryKey = 'id_resetare';

    public $timestamps = false;

    protected $fillable = ['id_utilizator'];

    public static function amprentaCodului(string $cod): string
    {
        return hash('sha256', $cod);
    }

    public static function areCerereDeschisa(int $idUtilizator): bool
    {
        return static::where('id_utilizator', $idUtilizator)->where('stare', 'solicitata')->exists();
    }

    // Cererea facuta de utilizator din pagina "Am uitat parola".
    public static function creeazaCerere(int $idUtilizator): void
    {
        static::create(['id_utilizator' => $idUtilizator]);
    }

    // Cererile la care administratorul nu a raspuns inca.
    public static function solicitate(): array
    {
        return DB::table('resetare_parola AS p')
            ->join('utilizator AS u', 'u.id_utilizator', '=', 'p.id_utilizator')
            ->selectRaw("p.id_resetare, u.nume_complet, u.email, DATE_FORMAT(p.solicitata_la, '%d.%m.%Y %H:%i') AS solicitata_la")
            ->where('p.stare', 'solicitata')
            ->where('u.activ', 1)
            ->orderBy('p.id_resetare')
            ->get()
            ->all();
    }

    public static function gasesteSolicitata(int $idResetare): ?object
    {
        return DB::table('resetare_parola')
            ->select('id_resetare', 'id_utilizator')
            ->where('id_resetare', $idResetare)
            ->where('stare', 'solicitata')
            ->first();
    }

    // Administratorul raspunde unei cereri: ii ataseaza un link valabil cateva minute.
    public static function ataseazaLink(int $idResetare, string $codHash, int $idAdministrator, int $minute): void
    {
        DB::table('resetare_parola')
            ->where('id_resetare', $idResetare)
            ->where('stare', 'solicitata')
            ->update([
                'stare' => 'link_generat',
                'cod_hash' => $codHash,
                'expira_la' => DB::raw('DATE_ADD(NOW(), INTERVAL ' . (int) $minute . ' MINUTE)'),
                'procesata_la' => DB::raw('NOW()'),
                'procesata_de' => $idAdministrator,
            ]);
    }

    // Administratorul genereaza un link direct din lista de conturi, fara cerere.
    public static function creeazaCuLink(int $idUtilizator, string $codHash, int $idAdministrator, int $minute): void
    {
        DB::table('resetare_parola')->insert([
            'id_utilizator' => $idUtilizator,
            'stare' => 'link_generat',
            'cod_hash' => $codHash,
            'expira_la' => DB::raw('DATE_ADD(NOW(), INTERVAL ' . (int) $minute . ' MINUTE)'),
            'procesata_la' => DB::raw('NOW()'),
            'procesata_de' => $idAdministrator,
        ]);
    }

    // Un link este bun daca nu a fost folosit, nu a expirat si contul este activ.
    public static function gasesteLinkValid(string $codHash): ?object
    {
        return DB::table('resetare_parola AS p')
            ->join('utilizator AS u', 'u.id_utilizator', '=', 'p.id_utilizator')
            ->select('p.id_resetare', 'p.id_utilizator', 'u.email')
            ->where('p.cod_hash', $codHash)
            ->where('p.stare', 'link_generat')
            ->whereRaw('p.expira_la > NOW()')
            ->where('u.activ', 1)
            ->first();
    }

    // Un link se poate folosi o singura data. Intoarce false daca tocmai a fost folosit.
    public static function marcheazaFolosita(int $idResetare): bool
    {
        return DB::table('resetare_parola')
            ->where('id_resetare', $idResetare)
            ->where('stare', 'link_generat')
            ->update(['stare' => 'folosita', 'folosita_la' => DB::raw('NOW()')]) === 1;
    }

    // Anuleaza cererile si linkurile ramase deschise pentru un utilizator.
    public static function anuleazaDeschise(int $idUtilizator): void
    {
        DB::table('resetare_parola')
            ->where('id_utilizator', $idUtilizator)
            ->whereIn('stare', ['solicitata', 'link_generat'])
            ->update(['stare' => 'anulata']);
    }

    // Un utilizator are cel mult un link valabil: cele vechi se anuleaza.
    public static function anuleazaLinkuri(int $idUtilizator): void
    {
        DB::table('resetare_parola')
            ->where('id_utilizator', $idUtilizator)
            ->where('stare', 'link_generat')
            ->update(['stare' => 'anulata']);
    }

    // Intoarce false daca cererea nu mai astepta raspuns.
    public static function respinge(int $idResetare, int $idAdministrator): bool
    {
        return DB::table('resetare_parola')
            ->where('id_resetare', $idResetare)
            ->where('stare', 'solicitata')
            ->update([
                'stare' => 'anulata',
                'procesata_la' => DB::raw('NOW()'),
                'procesata_de' => $idAdministrator,
            ]) === 1;
    }
}
