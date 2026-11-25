<?php

declare(strict_types=1);

// MODEL: cererile de cont trimise din pagina "Creare cont" (tabelul "cerere_cont").
final class CerereCont
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function creeaza(string $numeComplet, string $email, string $parolaHash): void
    {
        $interogare = $this->pdo->prepare(
            'INSERT INTO cerere_cont (nume_complet, email, parola_hash)
             VALUES (:nume, :email, :parola_hash)'
        );
        $interogare->execute(['nume' => $numeComplet, 'email' => $email, 'parola_hash' => $parolaHash]);
    }

    public function existaInAsteptare(string $email): bool
    {
        $interogare = $this->pdo->prepare(
            "SELECT 1 FROM cerere_cont WHERE email = :email AND stare = 'in_asteptare' LIMIT 1"
        );
        $interogare->execute(['email' => $email]);

        return $interogare->fetchColumn() !== false;
    }

    // Cea mai noua cerere inca neaprobata pentru un e-mail. Folosita la autentificare,
    // ca omul sa afle ca asteapta aprobarea sau ca cererea a fost respinsa.
    public function ultimaNeaprobata(string $email): ?array
    {
        $interogare = $this->pdo->prepare(
            "SELECT stare, parola_hash
             FROM cerere_cont
             WHERE email = :email AND stare IN ('in_asteptare', 'respinsa')
             ORDER BY id_cerere DESC
             LIMIT 1"
        );
        $interogare->execute(['email' => $email]);

        $cerere = $interogare->fetch();

        return $cerere === false ? null : $cerere;
    }

    // Cererile care asteapta decizia administratorului. Fara parole.
    public function inAsteptare(): array
    {
        return $this->pdo->query(
            "SELECT id_cerere,
                    nume_complet,
                    email,
                    DATE_FORMAT(creata_la, '%d.%m.%Y %H:%i') AS creata_la
             FROM cerere_cont
             WHERE stare = 'in_asteptare'
             ORDER BY id_cerere"
        )->fetchAll();
    }

    // Citeste cererea si o blocheaza pana la sfarsitul tranzactiei,
    // ca doi administratori sa nu o poata procesa in acelasi timp.
    public function gasesteInAsteptarePentruProcesare(int $idCerere): ?array
    {
        $interogare = $this->pdo->prepare(
            "SELECT id_cerere, nume_complet, email, parola_hash
             FROM cerere_cont
             WHERE id_cerere = :id AND stare = 'in_asteptare'
             FOR UPDATE"
        );
        $interogare->execute(['id' => $idCerere]);

        $cerere = $interogare->fetch();

        return $cerere === false ? null : $cerere;
    }

    // Dupa aprobare parola nu mai ramane in cerere: a fost mutata in contul creat.
    public function marcheazaAprobata(int $idCerere, int $idAdministrator): void
    {
        $interogare = $this->pdo->prepare(
            "UPDATE cerere_cont
             SET stare = 'aprobata', parola_hash = '', procesata_la = NOW(), procesata_de = :administrator
             WHERE id_cerere = :id"
        );
        $interogare->execute(['administrator' => $idAdministrator, 'id' => $idCerere]);
    }

    // Intoarce false daca cererea nu mai era in asteptare.
    public function respinge(int $idCerere, int $idAdministrator): bool
    {
        $interogare = $this->pdo->prepare(
            "UPDATE cerere_cont
             SET stare = 'respinsa', procesata_la = NOW(), procesata_de = :administrator
             WHERE id_cerere = :id AND stare = 'in_asteptare'"
        );
        $interogare->execute(['administrator' => $idAdministrator, 'id' => $idCerere]);

        return $interogare->rowCount() === 1;
    }
}
