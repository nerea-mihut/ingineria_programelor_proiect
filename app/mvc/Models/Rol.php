<?php

declare(strict_types=1);

// MODEL: citirea rolurilor din tabelul "rol".
final class Rol
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Intoarce id-ul rolului sau null daca denumirea nu exista in baza.
    public function idDupaDenumire(string $denumire): ?int
    {
        $interogare = $this->pdo->prepare('SELECT id_rol FROM rol WHERE denumire = :denumire LIMIT 1');
        $interogare->execute(['denumire' => $denumire]);

        $idRol = $interogare->fetchColumn();

        return $idRol === false ? null : (int) $idRol;
    }
}
