-- M.E.S.H. - import echipamente si rol monitor
-- Se poate rula de mai multe ori (nu dubleaza datele).
SET NAMES utf8mb4;
USE gestiune_cnc;

-- 1. Coloana cod (adaugata doar daca lipseste)
SET @are_cod = (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = 'gestiune_cnc'
                  AND TABLE_NAME = 'echipament' AND COLUMN_NAME = 'cod');
SET @ddl = IF(@are_cod = 0,
  'ALTER TABLE echipament ADD COLUMN cod VARCHAR(30) NULL AFTER id_echipament',
  'SELECT ''coloana cod exista deja'' AS info');
PREPARE pas FROM @ddl; EXECUTE pas; DEALLOCATE PREPARE pas;

-- 2. Index unic pe cod (adaugat doar daca lipseste)
SET @are_idx = (SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = 'gestiune_cnc'
                  AND TABLE_NAME = 'echipament' AND INDEX_NAME = 'uq_echipament_cod');
SET @ddl = IF(@are_idx = 0,
  'ALTER TABLE echipament ADD UNIQUE KEY uq_echipament_cod (cod)',
  'SELECT ''indexul exista deja'' AS info');
PREPARE pas FROM @ddl; EXECUTE pas; DEALLOCATE PREPARE pas;

-- 3. Cele 11 utilaje (pozitiile sunt procente din desenul halei)
START TRANSACTION;

INSERT INTO echipament (cod, denumire, hala, prioritate, pozitie_x, pozitie_y) VALUES
('FLEX_FST',    'Flexomat FST',     'Hala principala', 2, 13.10, 17.90),
('FLEX_FSG',    'Flexomat FSG',     'Hala principala', 2, 46.80, 17.90),
('AAP11_U13',   'AAP 11 U 13',      'Hala principala', 2, 68.00, 45.20),
('SIEMENS_FSG', 'Siemens FSG',      'Hala principala', 2, 14.00, 63.70),
('SIEMENS_FST', 'Siemens FST',      'Hala principala', 2, 61.80, 84.80),
('AEB35',       'AEB 35',           'Hala principala', 2, 86.60, 68.20),
('UNS_CLEME',   'Masina uns cleme', 'Hala principala', 2, 29.70, 81.50),
('LASER',       'Laser gravura',    'Hala principala', 2, 29.70, 69.20),
('PRESA',       'Presa automata',   'Hala principala', 2, 29.70, 57.10),
('ROBOT_CNC',   'CNC/robot mic',    'Hala principala', 2, 90.60, 14.40),
('ELECTROD',    'Electrod',         'Hala principala', 2, 92.10, 26.30)
AS nou
ON DUPLICATE KEY UPDATE
  denumire  = nou.denumire,
  hala      = nou.hala,
  pozitie_x = nou.pozitie_x,
  pozitie_y = nou.pozitie_y;

-- 4. Rolul monitor (cu litere mici, ca in config/roluri.php)
INSERT IGNORE INTO rol (denumire) VALUES ('monitor');

COMMIT;

-- 5. Verificare
SELECT cod, denumire, hala, prioritate, pozitie_x, pozitie_y
FROM echipament ORDER BY id_echipament;
SELECT * FROM rol ORDER BY id_rol;
