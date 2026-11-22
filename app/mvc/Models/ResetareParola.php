<?php

declare(strict_types=1);

// MODEL: cererile si linkurile de resetare a parolei (tabelul "resetare_parola").
//
// Linkul contine un cod aleator. In baza se pastreaza doar amprenta lui (SHA-256),
// asa ca cine citeste tabelul nu poate reconstitui linkul.
final class ResetareParola
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function amprentaCodului(string $cod): string
    {
        return hash('sha256', $cod);
    }

    public function areCerereDeschisa(int $idUtilizator): bool
    {
        $interogare = $this->pdo->prepare(
            "SELECT 1 FROM resetare_parola WHERE id_utilizator = :id AND stare = 'solicitata' LIMIT 1"
        );
        $interogare->execute(['id' => $idUtilizator]);

        return $interogare->fetchColumn() !== false;
    }

    // Cererea facuta de utilizator din pagina "Am uitat parola".
    public function creeazaCerere(int $idUtilizator): void
    {
        $interogare = $this->pdo->prepare('INSERT INTO resetare_parola (id_utilizator) VALUES (:id)');
        $interogare->execute(['id' => $idUtilizator]);
    }

    // Cererile la care administratorul nu a raspuns inca.
    public function solicitate(): array
    {
        return $this->pdo->query(
            "SELECT p.id_resetare,
                    u.nume_complet,
                    u.email,
                    DATE_FORMAT(p.solicitata_la, '%d.%m.%Y %H:%i') AS solicitata_la
             FROM resetare_parola AS p
             JOIN utilizator AS u ON u.id_utilizator = p.id_utilizator
             WHERE p.stare = 'solicitata' AND u.activ = 1
             ORDER BY p.id_resetare"
        )->fetchAll();
    }

    public function gasesteSolicitata(int $idResetare): ?array
    {
        $interogare = $this->pdo->prepare(
            "SELECT id_resetare, id_utilizator
             FROM resetare_parola
             WHERE id_resetare = :id AND stare = 'solicitata'"
        );
        $interogare->execute(['id' => $idResetare]);

        $cerere = $interogare->fetch();

        return $cerere === false ? null : $cerere;
    }

    // Administratorul raspunde unei cereri: ii ataseaza un link valabil cateva minute.
    public function ataseazaLink(int $idResetare, string $codHash, int $idAdministrator, int $minute): void
    {
        $interogare = $this->pdo->prepare(
            "UPDATE resetare_parola
             SET stare = 'link_generat',
                 cod_hash = :cod_hash,
                 expira_la = DATE_ADD(NOW(), INTERVAL :minute MINUTE),
                 procesata_la = NOW(),
                 procesata_de = :administrator
             WHERE id_resetare = :id AND stare = 'solicitata'"
        );
        $interogare->execute([
            'cod_hash' => $codHash,
            'minute' => $minute,
            'administrator' => $idAdministrator,
            'id' => $idResetare,
        ]);
    }

    // Administratorul genereaza un link direct din lista de conturi, fara cerere.
    public function creeazaCuLink(int $idUtilizator, string $codHash, int $idAdministrator, int $minute): void
    {
        $interogare = $this->pdo->prepare(
            "INSERT INTO resetare_parola
                 (id_utilizator, stare, cod_hash, expira_la, procesata_la, procesata_de)
             VALUES
                 (:id, 'link_generat', :cod_hash, DATE_ADD(NOW(), INTERVAL :minute MINUTE), NOW(), :administrator)"
        );
        $interogare->execute([
            'id' => $idUtilizator,
            'cod_hash' => $codHash,
            'minute' => $minute,
            'administrator' => $idAdministrator,
        ]);
    }

    // Un link este bun daca nu a fost folosit, nu a expirat si contul este activ.
    public function gasesteLinkValid(string $codHash): ?array
    {
        $interogare = $this->pdo->prepare(
            "SELECT p.id_resetare, p.id_utilizator, u.email
             FROM resetare_parola AS p
             JOIN utilizator AS u ON u.id_utilizator = p.id_utilizator
             WHERE p.cod_hash = :cod_hash
               AND p.stare = 'link_generat'
               AND p.expira_la > NOW()
               AND u.activ = 1
             LIMIT 1"
        );
        $interogare->execute(['cod_hash' => $codHash]);

        $resetare = $interogare->fetch();

        return $resetare === false ? null : $resetare;
    }

    // Un link se poate folosi o singura data. Intoarce false daca tocmai a fost folosit.
    public function marcheazaFolosita(int $idResetare): bool
    {
        $interogare = $this->pdo->prepare(
            "UPDATE resetare_parola
             SET stare = 'folosita', folosita_la = NOW()
             WHERE id_resetare = :id AND stare = 'link_generat'"
        );
        $interogare->execute(['id' => $idResetare]);

        return $interogare->rowCount() === 1;
    }

    // Anuleaza cererile si linkurile ramase deschise pentru un utilizator.
    public function anuleazaDeschise(int $idUtilizator): void
    {
        $interogare = $this->pdo->prepare(
            "UPDATE resetare_parola
             SET stare = 'anulata'
             WHERE id_utilizator = :id AND stare IN ('solicitata', 'link_generat')"
        );
        $interogare->execute(['id' => $idUtilizator]);
    }

    // Un utilizator are cel mult un link valabil: cele vechi se anuleaza.
    public function anuleazaLinkuri(int $idUtilizator): void
    {
        $interogare = $this->pdo->prepare(
            "UPDATE resetare_parola
             SET stare = 'anulata'
             WHERE id_utilizator = :id AND stare = 'link_generat'"
        );
        $interogare->execute(['id' => $idUtilizator]);
    }

    // Intoarce false daca cererea nu mai astepta raspuns.
    public function respinge(int $idResetare, int $idAdministrator): bool
    {
        $interogare = $this->pdo->prepare(
            "UPDATE resetare_parola
             SET stare = 'anulata', procesata_la = NOW(), procesata_de = :administrator
             WHERE id_resetare = :id AND stare = 'solicitata'"
        );
        $interogare->execute(['administrator' => $idAdministrator, 'id' => $idResetare]);

        return $interogare->rowCount() === 1;
    }
}
