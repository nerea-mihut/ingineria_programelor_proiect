<?php

declare(strict_types=1);

// MODEL: jurnalul incercarilor (tabelul "incercare_acces").
// Pe baza lui aplicatia opreste incercarile repetate de ghicire a parolei
// si trimiterea in masa a cererilor.
final class IncercareAcces
{
    public const AUTENTIFICARE = 'autentificare';
    public const CERERE_CONT = 'cerere_cont';
    public const CERERE_RESETARE = 'cerere_resetare';
    public const LINK_RESETARE = 'link_resetare';
    public const SCHIMBARE_PAROLA = 'schimbare_parola';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function inregistreaza(string $tip, ?string $email, string $adresaIp, bool $reusita): void
    {
        $interogare = $this->pdo->prepare(
            'INSERT INTO incercare_acces (tip, email, adresa_ip, reusita)
             VALUES (:tip, :email, :adresa_ip, :reusita)'
        );
        $interogare->execute([
            'tip' => $tip,
            'email' => $email,
            'adresa_ip' => $adresaIp,
            'reusita' => $reusita ? 1 : 0,
        ]);
    }

    // Cate secunde mai dureaza blocarea pentru un e-mail. 0 = nu este blocat.
    public function secundeBlocareEmail(string $tip, string $email, int $maxim, int $minute): int
    {
        return $this->secundeBlocare('email', $tip, $email, $maxim, $minute);
    }

    // La fel, pentru o adresa IP.
    public function secundeBlocareIp(string $tip, string $adresaIp, int $maxim, int $minute): int
    {
        return $this->secundeBlocare('adresa_ip', $tip, $adresaIp, $maxim, $minute);
    }

    // Cate incercari nereusite are un e-mail in ultimele $minute minute.
    public function numaraEsecuriEmail(string $tip, string $email, int $minute): int
    {
        $interogare = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM incercare_acces
             WHERE tip = :tip
               AND email = :email
               AND reusita = 0
               AND moment > DATE_SUB(NOW(), INTERVAL :minute MINUTE)'
        );
        $interogare->execute(['tip' => $tip, 'email' => $email, 'minute' => $minute]);

        return (int) $interogare->fetchColumn();
    }

    // Cate cereri de un anumit tip a trimis o adresa IP in ultimele $minute minute.
    public function numaraPentruIp(string $tip, string $adresaIp, int $minute): int
    {
        $interogare = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM incercare_acces
             WHERE tip = :tip
               AND adresa_ip = :adresa_ip
               AND moment > DATE_SUB(NOW(), INTERVAL :minute MINUTE)'
        );
        $interogare->execute(['tip' => $tip, 'adresa_ip' => $adresaIp, 'minute' => $minute]);

        return (int) $interogare->fetchColumn();
    }

    // Dupa o autentificare reusita, greselile vechi nu mai conteaza.
    public function stergeEsecuriEmail(string $tip, string $email): void
    {
        $interogare = $this->pdo->prepare(
            'DELETE FROM incercare_acces WHERE tip = :tip AND email = :email AND reusita = 0'
        );
        $interogare->execute(['tip' => $tip, 'email' => $email]);
    }

    // Jurnalul nu creste la nesfarsit: inregistrarile mai vechi de o zi se sterg.
    public function stergeVechi(): void
    {
        $this->pdo->exec('DELETE FROM incercare_acces WHERE moment < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    }

    // Blocarea incepe la a $maxim-a greseala din fereastra de timp si tine
    // pana cand acea greseala iese din fereastra.
    private function secundeBlocare(string $coloana, string $tip, string $valoare, int $maxim, int $minute): int
    {
        // $coloana vine doar din cele doua metode de mai sus, niciodata din cerere.
        $interogare = $this->pdo->prepare(
            "SELECT TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(moment, INTERVAL :minute_blocare MINUTE))
             FROM incercare_acces
             WHERE tip = :tip
               AND {$coloana} = :valoare
               AND reusita = 0
               AND moment > DATE_SUB(NOW(), INTERVAL :minute_fereastra MINUTE)
             ORDER BY moment DESC, id_incercare DESC
             LIMIT 1 OFFSET " . max(0, $maxim - 1)
        );
        $interogare->execute([
            'minute_blocare' => $minute,
            'tip' => $tip,
            'valoare' => $valoare,
            'minute_fereastra' => $minute,
        ]);

        $secunde = $interogare->fetchColumn();

        return $secunde === false ? 0 : max(0, (int) $secunde);
    }
}
