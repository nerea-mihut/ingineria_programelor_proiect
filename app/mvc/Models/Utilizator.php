<?php

declare(strict_types=1);

// MODEL: operatiile asupra datelor din tabelul "utilizator".
// Nu afiseaza pagini si nu lucreaza cu sesiunea.
final class Utilizator
{
    private const COLOANE = 'u.id_utilizator,
                             u.nume_complet,
                             u.email,
                             u.parola_hash,
                             u.departament,
                             u.activ,
                             r.denumire AS rol';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Cauta un utilizator dupa e-mail, impreuna cu denumirea rolului.
    // Intoarce null daca nu exista.
    public function gasesteDupaEmail(string $email): ?array
    {
        $interogare = $this->pdo->prepare(
            'SELECT ' . self::COLOANE . '
             FROM utilizator AS u
             JOIN rol AS r ON r.id_rol = u.id_rol
             WHERE u.email = :email
             LIMIT 1'
        );

        // Valoarea este trimisa separat de textul SQL (interogare parametrizata).
        $interogare->execute(['email' => $email]);

        $utilizator = $interogare->fetch();

        return $utilizator === false ? null : $utilizator;
    }

    public function gasesteDupaId(int $idUtilizator): ?array
    {
        $interogare = $this->pdo->prepare(
            'SELECT ' . self::COLOANE . '
             FROM utilizator AS u
             JOIN rol AS r ON r.id_rol = u.id_rol
             WHERE u.id_utilizator = :id
             LIMIT 1'
        );
        $interogare->execute(['id' => $idUtilizator]);

        $utilizator = $interogare->fetch();

        return $utilizator === false ? null : $utilizator;
    }

    public function existaEmail(string $email): bool
    {
        $interogare = $this->pdo->prepare('SELECT 1 FROM utilizator WHERE email = :email LIMIT 1');
        $interogare->execute(['email' => $email]);

        return $interogare->fetchColumn() !== false;
    }

    // Toate conturile, pentru pagina de administrare. Fara parole.
    public function toate(): array
    {
        return $this->pdo->query(
            'SELECT u.id_utilizator,
                    u.nume_complet,
                    u.email,
                    u.departament,
                    u.activ,
                    r.denumire AS rol
             FROM utilizator AS u
             JOIN rol AS r ON r.id_rol = u.id_rol
             ORDER BY u.nume_complet, u.id_utilizator'
        )->fetchAll();
    }

    // Creeaza un cont activ. Parola ajunge aici deja transformata in hash.
    public function creeaza(
        string $numeComplet,
        string $email,
        string $parolaHash,
        int $idRol,
        ?string $departament
    ): int {
        $interogare = $this->pdo->prepare(
            'INSERT INTO utilizator (id_rol, nume_complet, email, parola_hash, departament, activ)
             VALUES (:id_rol, :nume, :email, :parola_hash, :departament, 1)'
        );
        $interogare->execute([
            'id_rol' => $idRol,
            'nume' => $numeComplet,
            'email' => $email,
            'parola_hash' => $parolaHash,
            'departament' => $departament,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function actualizeazaParola(int $idUtilizator, string $parolaHash): void
    {
        $interogare = $this->pdo->prepare(
            'UPDATE utilizator SET parola_hash = :parola_hash WHERE id_utilizator = :id'
        );
        $interogare->execute(['parola_hash' => $parolaHash, 'id' => $idUtilizator]);
    }

    public function actualizeazaRolSiDepartament(int $idUtilizator, int $idRol, ?string $departament): void
    {
        $interogare = $this->pdo->prepare(
            'UPDATE utilizator SET id_rol = :id_rol, departament = :departament WHERE id_utilizator = :id'
        );
        $interogare->execute(['id_rol' => $idRol, 'departament' => $departament, 'id' => $idUtilizator]);
    }

    public function seteazaActiv(int $idUtilizator, bool $activ): void
    {
        $interogare = $this->pdo->prepare('UPDATE utilizator SET activ = :activ WHERE id_utilizator = :id');
        $interogare->execute(['activ' => $activ ? 1 : 0, 'id' => $idUtilizator]);
    }
}
