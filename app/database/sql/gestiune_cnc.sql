gestiune_cnc-- --------------------------------------------------------
-- Server:                       127.0.0.1
-- Versiune server:              8.4.3 - MySQL Community Server - GPL
-- SO server:                    Win64
-- HeidiSQL Versiune:            12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Descarcă structura bazei de date pentru gestiune_cnc
CREATE DATABASE IF NOT EXISTS `gestiune_cnc` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `gestiune_cnc`;

-- Descarcă structura pentru tabelă gestiune_cnc.amplasare
CREATE TABLE IF NOT EXISTS `amplasare` (
  `id_amplasare` int unsigned NOT NULL AUTO_INCREMENT,
  `hala` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rotomat` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `raft` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id_amplasare`),
  UNIQUE KEY `uq_amplasare` (`hala`,`rotomat`,`raft`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Descarcă structura pentru tabelă gestiune_cnc.articol
CREATE TABLE IF NOT EXISTS `articol` (
  `id_articol` int unsigned NOT NULL AUTO_INCREMENT,
  `cod_articol` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `denumire` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `producator` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categorie` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prag_minim` int unsigned NOT NULL DEFAULT '0',
  `arhivat` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_articol`),
  UNIQUE KEY `cod_articol` (`cod_articol`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Descarcă structura pentru tabelă gestiune_cnc.echipament
CREATE TABLE IF NOT EXISTS `echipament` (
  `id_echipament` int unsigned NOT NULL AUTO_INCREMENT,
  `denumire` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hala` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prioritate` tinyint unsigned NOT NULL,
  `pozitie_x` decimal(5,2) DEFAULT NULL,
  `pozitie_y` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id_echipament`),
  CONSTRAINT `chk_echipament_pozitie` CHECK ((((`pozitie_x` is null) and (`pozitie_y` is null)) or ((`pozitie_x` is not null) and (`pozitie_y` is not null) and (`pozitie_x` between 0 and 100) and (`pozitie_y` between 0 and 100)))),
  CONSTRAINT `chk_echipament_prioritate` CHECK ((`prioritate` between 1 and 3))
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Descarcă structura pentru tabelă gestiune_cnc.interventie
CREATE TABLE IF NOT EXISTS `interventie` (
  `id_interventie` int unsigned NOT NULL AUTO_INCREMENT,
  `id_sesizare` int unsigned NOT NULL,
  `id_mecanic` int unsigned NOT NULL,
  `stare` enum('in_lucru','in_asteptare','finalizata') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_lucru',
  `preluata_la` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finalizata_la` datetime DEFAULT NULL,
  `constatari` text COLLATE utf8mb4_unicode_ci,
  `lucrari` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id_interventie`),
  UNIQUE KEY `id_sesizare` (`id_sesizare`),
  KEY `fk_interventie_mecanic` (`id_mecanic`),
  CONSTRAINT `fk_interventie_mecanic` FOREIGN KEY (`id_mecanic`) REFERENCES `utilizator` (`id_utilizator`),
  CONSTRAINT `fk_interventie_sesizare` FOREIGN KEY (`id_sesizare`) REFERENCES `sesizare` (`id_sesizare`),
  CONSTRAINT `chk_interventie_finalizare` CHECK ((((`stare` = _utf8mb4'finalizata') and (`finalizata_la` is not null) and (`finalizata_la` >= `preluata_la`)) or ((`stare` <> _utf8mb4'finalizata') and (`finalizata_la` is null))))
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Descarcă structura pentru tabelă gestiune_cnc.istoric_interventie
CREATE TABLE IF NOT EXISTS `istoric_interventie` (
  `id_eveniment` int unsigned NOT NULL AUTO_INCREMENT,
  `id_interventie` int unsigned NOT NULL,
  `id_autor` int unsigned NOT NULL,
  `tip_eveniment` enum('preluare','schimbare_stare','predare') COLLATE utf8mb4_unicode_ci NOT NULL,
  `inregistrat_la` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `stare_veche` enum('in_lucru','in_asteptare','finalizata') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stare_noua` enum('in_lucru','in_asteptare','finalizata') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_mecanic_vechi` int unsigned DEFAULT NULL,
  `id_mecanic_nou` int unsigned DEFAULT NULL,
  `observatii` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id_eveniment`),
  KEY `id_autor` (`id_autor`),
  KEY `id_mecanic_vechi` (`id_mecanic_vechi`),
  KEY `id_mecanic_nou` (`id_mecanic_nou`),
  KEY `idx_istoric_cronologic` (`id_interventie`,`inregistrat_la`,`id_eveniment`),
  CONSTRAINT `istoric_interventie_ibfk_1` FOREIGN KEY (`id_interventie`) REFERENCES `interventie` (`id_interventie`),
  CONSTRAINT `istoric_interventie_ibfk_2` FOREIGN KEY (`id_autor`) REFERENCES `utilizator` (`id_utilizator`),
  CONSTRAINT `istoric_interventie_ibfk_3` FOREIGN KEY (`id_mecanic_vechi`) REFERENCES `utilizator` (`id_utilizator`),
  CONSTRAINT `istoric_interventie_ibfk_4` FOREIGN KEY (`id_mecanic_nou`) REFERENCES `utilizator` (`id_utilizator`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Descarcă structura pentru tabelă gestiune_cnc.miscare_stoc
CREATE TABLE IF NOT EXISTS `miscare_stoc` (
  `id_miscare` int unsigned NOT NULL AUTO_INCREMENT,
  `id_stoc` int unsigned NOT NULL,
  `id_utilizator` int unsigned NOT NULL,
  `id_interventie` int unsigned DEFAULT NULL,
  `tip` enum('receptie','eliberare','corectie','eliminare','reintroducere') COLLATE utf8mb4_unicode_ci NOT NULL,
  `variatie_cantitate` int NOT NULL,
  `motiv` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `inregistrata_la` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_miscare`),
  KEY `fk_miscare_stoc` (`id_stoc`),
  KEY `fk_miscare_utilizator` (`id_utilizator`),
  KEY `fk_miscare_interventie` (`id_interventie`),
  KEY `idx_miscare_data` (`inregistrata_la`),
  CONSTRAINT `fk_miscare_interventie` FOREIGN KEY (`id_interventie`) REFERENCES `interventie` (`id_interventie`),
  CONSTRAINT `fk_miscare_stoc` FOREIGN KEY (`id_stoc`) REFERENCES `stoc_piesa` (`id_stoc`),
  CONSTRAINT `fk_miscare_utilizator` FOREIGN KEY (`id_utilizator`) REFERENCES `utilizator` (`id_utilizator`),
  CONSTRAINT `chk_miscare_eliberare` CHECK (((`tip` <> _utf8mb4'eliberare') or (`id_interventie` is not null))),
  CONSTRAINT `chk_miscare_motiv` CHECK ((char_length(trim(`motiv`)) > 0)),
  CONSTRAINT `chk_miscare_variatie` CHECK ((((`tip` in (_utf8mb4'receptie',_utf8mb4'reintroducere')) and (`variatie_cantitate` > 0)) or ((`tip` in (_utf8mb4'eliberare',_utf8mb4'eliminare')) and (`variatie_cantitate` < 0)) or ((`tip` = _utf8mb4'corectie') and (`variatie_cantitate` <> 0))))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Descarcă structura pentru tabelă gestiune_cnc.oprire
CREATE TABLE IF NOT EXISTS `oprire` (
  `id_oprire` int unsigned NOT NULL AUTO_INCREMENT,
  `id_sesizare` int unsigned NOT NULL,
  `oprita_la` datetime NOT NULL,
  `reluata_la` datetime DEFAULT NULL,
  `executa_comanda_inainte` tinyint(1) NOT NULL,
  `necesita_reparatie` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id_oprire`),
  KEY `fk_oprire_sesizare` (`id_sesizare`),
  CONSTRAINT `fk_oprire_sesizare` FOREIGN KEY (`id_sesizare`) REFERENCES `sesizare` (`id_sesizare`),
  CONSTRAINT `chk_oprire_comanda` CHECK ((`executa_comanda_inainte` in (0,1))),
  CONSTRAINT `chk_oprire_necesita_reparatie` CHECK ((`necesita_reparatie` in (0,1))),
  CONSTRAINT `chk_oprire_reluare` CHECK (((`reluata_la` is null) or (`reluata_la` >= `oprita_la`)))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Descarcă structura pentru tabelă gestiune_cnc.rol
CREATE TABLE IF NOT EXISTS `rol` (
  `id_rol` int unsigned NOT NULL AUTO_INCREMENT,
  `denumire` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `denumire` (`denumire`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Descarcă structura pentru tabelă gestiune_cnc.sesizare
CREATE TABLE IF NOT EXISTS `sesizare` (
  `id_sesizare` int unsigned NOT NULL AUTO_INCREMENT,
  `id_echipament` int unsigned NOT NULL,
  `id_operator` int unsigned NOT NULL,
  `descriere_problema` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `raportata_la` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_sesizare`),
  KEY `fk_sesizare_echipament` (`id_echipament`),
  KEY `fk_sesizare_operator` (`id_operator`),
  CONSTRAINT `fk_sesizare_echipament` FOREIGN KEY (`id_echipament`) REFERENCES `echipament` (`id_echipament`),
  CONSTRAINT `fk_sesizare_operator` FOREIGN KEY (`id_operator`) REFERENCES `utilizator` (`id_utilizator`),
  CONSTRAINT `chk_sesizare_descriere` CHECK ((char_length(trim(`descriere_problema`)) > 0))
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Descarcă structura pentru tabelă gestiune_cnc.stoc_piesa
CREATE TABLE IF NOT EXISTS `stoc_piesa` (
  `id_stoc` int unsigned NOT NULL AUTO_INCREMENT,
  `id_articol` int unsigned NOT NULL,
  `id_amplasare` int unsigned NOT NULL,
  `stare` enum('utilizabila','defecta','de_verificat') COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantitate` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_stoc`),
  UNIQUE KEY `uq_stoc_articol_amplasare_stare` (`id_articol`,`id_amplasare`,`stare`),
  KEY `fk_stoc_amplasare` (`id_amplasare`),
  CONSTRAINT `fk_stoc_amplasare` FOREIGN KEY (`id_amplasare`) REFERENCES `amplasare` (`id_amplasare`),
  CONSTRAINT `fk_stoc_articol` FOREIGN KEY (`id_articol`) REFERENCES `articol` (`id_articol`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Descarcă structura pentru tabelă gestiune_cnc.utilizator
CREATE TABLE IF NOT EXISTS `utilizator` (
  `id_utilizator` int unsigned NOT NULL AUTO_INCREMENT,
  `id_rol` int unsigned NOT NULL,
  `nume_complet` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(254) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parola_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `departament` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activ` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_utilizator`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_utilizator_rol` (`id_rol`),
  CONSTRAINT `fk_utilizator_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
