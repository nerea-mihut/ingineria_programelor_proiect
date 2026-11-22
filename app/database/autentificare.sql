-- Tabelele folosite de partea de autentificare a aplicatiei M.E.S.H.
--
-- Ruleaza acest fisier DUPA gestiune_cnc.sql.
-- Poate fi rulat de mai multe ori: nu sterge si nu dubleaza nimic.

USE gestiune_cnc;

-- Rolurile din SRS (RF-002). Aplicatia are nevoie de ele ca sa poata aproba conturi.
INSERT INTO rol (denumire)
SELECT 'operator_cnc' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM rol WHERE denumire = 'operator_cnc');

INSERT INTO rol (denumire)
SELECT 'mecanic' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM rol WHERE denumire = 'mecanic');

INSERT INTO rol (denumire)
SELECT 'administrator_departament' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM rol WHERE denumire = 'administrator_departament');

INSERT INTO rol (denumire)
SELECT 'administrator_general' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM rol WHERE denumire = 'administrator_general');

-- Cererile de cont trimise din pagina "Creare cont".
-- Contul apare in tabelul "utilizator" abia dupa ce administratorul general
-- aproba cererea si alege rolul si departamentul (RF-001: nimeni nu isi da singur drepturi).
CREATE TABLE IF NOT EXISTS cerere_cont (
  id_cerere INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nume_complet VARCHAR(120) NOT NULL,
  email VARCHAR(254) NOT NULL,
  parola_hash VARCHAR(255) NOT NULL,
  stare ENUM('in_asteptare', 'aprobata', 'respinsa') NOT NULL DEFAULT 'in_asteptare',
  creata_la DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  procesata_la DATETIME DEFAULT NULL,
  procesata_de INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id_cerere),
  KEY idx_cerere_cont_email (email),
  KEY idx_cerere_cont_stare (stare),
  CONSTRAINT fk_cerere_cont_administrator FOREIGN KEY (procesata_de) REFERENCES utilizator (id_utilizator)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cererile de resetare a parolei si linkurile de resetare.
-- In baza se pastreaza doar amprenta (hash) codului din link, nu codul.
CREATE TABLE IF NOT EXISTS resetare_parola (
  id_resetare INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_utilizator INT UNSIGNED NOT NULL,
  stare ENUM('solicitata', 'link_generat', 'folosita', 'anulata') NOT NULL DEFAULT 'solicitata',
  cod_hash CHAR(64) DEFAULT NULL,
  solicitata_la DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_la DATETIME DEFAULT NULL,
  procesata_la DATETIME DEFAULT NULL,
  procesata_de INT UNSIGNED DEFAULT NULL,
  folosita_la DATETIME DEFAULT NULL,
  PRIMARY KEY (id_resetare),
  UNIQUE KEY uq_resetare_parola_cod (cod_hash),
  KEY idx_resetare_parola_stare (stare),
  CONSTRAINT fk_resetare_parola_utilizator FOREIGN KEY (id_utilizator) REFERENCES utilizator (id_utilizator),
  CONSTRAINT fk_resetare_parola_administrator FOREIGN KEY (procesata_de) REFERENCES utilizator (id_utilizator)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jurnalul incercarilor, folosit pentru limitarea incercarilor repetate.
CREATE TABLE IF NOT EXISTS incercare_acces (
  id_incercare BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tip VARCHAR(30) NOT NULL,
  email VARCHAR(254) DEFAULT NULL,
  adresa_ip VARCHAR(45) NOT NULL,
  reusita TINYINT(1) NOT NULL DEFAULT 0,
  moment DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_incercare),
  KEY idx_incercare_acces_email (tip, email, moment),
  KEY idx_incercare_acces_ip (tip, adresa_ip, moment)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
