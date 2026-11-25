<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

// MODEL: conturile din tabelul "utilizator".
// Laravel il foloseste pentru autentificare (config/auth.php).
class User extends Authenticatable
{
    protected $table = 'utilizator';

    protected $primaryKey = 'id_utilizator';

    public $timestamps = false;

    // Coloana in care este salvata parola (hash), in locul coloanei "password" din Laravel.
    protected $authPasswordName = 'parola_hash';

    protected $fillable = [
        'id_rol',
        'nume_complet',
        'email',
        'parola_hash',
        'departament',
        'activ',
    ];

    protected $hidden = [
        'parola_hash',
    ];

    public function getAuthPassword(): string
    {
        return (string) $this->parola_hash;
    }

    // Tabelul nu are coloana remember_token ("tine-ma minte" nu este folosit).
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'id_rol', 'id_rol');
    }

    // ----- Cautari -----

    public static function gasesteDupaEmail(string $email): ?self
    {
        return static::with('rol')->where('email', $email)->first();
    }

    public static function gasesteDupaId(int $idUtilizator): ?self
    {
        return static::with('rol')->find($idUtilizator);
    }

    public static function existaEmail(string $email): bool
    {
        return static::where('email', $email)->exists();
    }

    // ----- Modificari -----

    // Creeaza un cont activ. Parola ajunge aici deja transformata in hash.
    public static function creeazaCont(
        string $numeComplet,
        string $email,
        string $parolaHash,
        int $idRol,
        ?string $departament
    ): self {
        return static::create([
            'id_rol' => $idRol,
            'nume_complet' => $numeComplet,
            'email' => $email,
            'parola_hash' => $parolaHash,
            'departament' => $departament,
            'activ' => 1,
        ]);
    }

    public function actualizeazaParola(string $parolaHash): void
    {
        $this->parola_hash = $parolaHash;
        $this->save();
    }

    // ----- Date pentru restul aplicatiei -----

    public function esteActiv(): bool
    {
        return (int) $this->activ === 1;
    }

    public function denumireRol(): string
    {
        return (string) ($this->rol->denumire ?? '');
    }

    // Datele contului, asa cum le folosesc controllerele si regulile din Drepturi.
    public function date(): array
    {
        return [
            'id' => (int) $this->id_utilizator,
            'nume' => $this->nume_complet,
            'email' => $this->email,
            'rol' => $this->denumireRol(),
            'departament' => $this->departament,
        ];
    }
}
