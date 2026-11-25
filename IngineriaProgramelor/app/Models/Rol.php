<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// MODEL: rolurile din tabelul "rol".
class Rol extends Model
{
    protected $table = 'rol';

    protected $primaryKey = 'id_rol';

    public $timestamps = false;

    protected $fillable = ['denumire'];

    // Intoarce id-ul rolului sau null daca denumirea nu exista in baza.
    public static function idDupaDenumire(string $denumire): ?int
    {
        $idRol = static::where('denumire', $denumire)->value('id_rol');

        return $idRol === null ? null : (int) $idRol;
    }
}
