<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// MODEL: jurnalul incercarilor (tabelul "incercare_acces").
// Pe baza lui aplicatia opreste incercarile repetate de ghicire a parolei
// si trimiterea in masa a cererilor.
class IncercareAcces extends Model
{
    public const AUTENTIFICARE = 'autentificare';
    public const CERERE_CONT = 'cerere_cont';
    public const CERERE_RESETARE = 'cerere_resetare';
    public const LINK_RESETARE = 'link_resetare';
    public const SCHIMBARE_PAROLA = 'schimbare_parola';

    protected $table = 'incercare_acces';

    protected $primaryKey = 'id_incercare';

    public $timestamps = false;

    protected $fillable = ['tip', 'email', 'adresa_ip', 'reusita'];

    public static function inregistreaza(string $tip, ?string $email, string $adresaIp, bool $reusita): void
    {
        static::create([
            'tip' => $tip,
            'email' => $email,
            'adresa_ip' => $adresaIp,
            'reusita' => $reusita ? 1 : 0,
        ]);
    }

    // Cate secunde mai dureaza blocarea pentru un e-mail. 0 = nu este blocat.
    public static function secundeBlocareEmail(string $tip, string $email, int $maxim, int $minute): int
    {
        return self::secundeBlocare('email', $tip, $email, $maxim, $minute);
    }

    // La fel, pentru o adresa IP.
    public static function secundeBlocareIp(string $tip, string $adresaIp, int $maxim, int $minute): int
    {
        return self::secundeBlocare('adresa_ip', $tip, $adresaIp, $maxim, $minute);
    }

    // Cate incercari nereusite are un e-mail in ultimele $minute minute.
    public static function numaraEsecuriEmail(string $tip, string $email, int $minute): int
    {
        return static::where('tip', $tip)
            ->where('email', $email)
            ->where('reusita', 0)
            ->whereRaw('moment > DATE_SUB(NOW(), INTERVAL ? MINUTE)', [$minute])
            ->count();
    }

    // Cate cereri de un anumit tip a trimis o adresa IP in ultimele $minute minute.
    public static function numaraPentruIp(string $tip, string $adresaIp, int $minute): int
    {
        return static::where('tip', $tip)
            ->where('adresa_ip', $adresaIp)
            ->whereRaw('moment > DATE_SUB(NOW(), INTERVAL ? MINUTE)', [$minute])
            ->count();
    }

    // Dupa o autentificare reusita, greselile vechi nu mai conteaza.
    public static function stergeEsecuriEmail(string $tip, string $email): void
    {
        static::where('tip', $tip)->where('email', $email)->where('reusita', 0)->delete();
    }

    // Jurnalul nu creste la nesfarsit: inregistrarile mai vechi de o zi se sterg.
    public static function stergeVechi(): void
    {
        static::whereRaw('moment < DATE_SUB(NOW(), INTERVAL 1 DAY)')->delete();
    }

    // Blocarea incepe la a $maxim-a greseala din fereastra de timp si tine
    // pana cand acea greseala iese din fereastra.
    private static function secundeBlocare(string $coloana, string $tip, string $valoare, int $maxim, int $minute): int
    {
        // $coloana vine doar din cele doua metode de mai sus, niciodata din cerere.
        $secunde = DB::table('incercare_acces')
            ->selectRaw('TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(moment, INTERVAL ? MINUTE)) AS secunde', [$minute])
            ->where('tip', $tip)
            ->where($coloana, $valoare)
            ->where('reusita', 0)
            ->whereRaw('moment > DATE_SUB(NOW(), INTERVAL ? MINUTE)', [$minute])
            ->orderByDesc('moment')
            ->orderByDesc('id_incercare')
            ->offset(max(0, $maxim - 1))
            ->limit(1)
            ->value('secunde');

        return $secunde === null ? 0 : max(0, (int) $secunde);
    }
}
