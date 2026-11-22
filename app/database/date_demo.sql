-- Date demonstrative pentru dezvoltarea locala a aplicatiei M.E.S.H.
--
-- Ruleaza acest fisier DUPA gestiune_cnc.sql.
-- Poate fi rulat de mai multe ori: nu creeaza duplicate.
--
-- Parola tuturor conturilor demonstrative: MeshDemo2026!
-- Conturile sunt doar pentru testare locala. Nu le folosi pe un server public.

USE gestiune_cnc;

-- Rolurile din SRS (RF-002)
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

-- Cate un cont demonstrativ pentru fiecare rol
INSERT INTO utilizator (id_rol, nume_complet, email, parola_hash, departament, activ)
SELECT r.id_rol, 'Operator Demo', 'operator@mesh.test',
       '$2y$10$sWnnaOnQVfL4X0Yai.R1MeE3VFKz1EZ8QWmeIIEVt0WGVQG4UAPHa',
       'Producție', TRUE
FROM rol AS r
WHERE r.denumire = 'operator_cnc'
  AND NOT EXISTS (SELECT 1 FROM utilizator WHERE email = 'operator@mesh.test');

INSERT INTO utilizator (id_rol, nume_complet, email, parola_hash, departament, activ)
SELECT r.id_rol, 'Mecanic Demo', 'mecanic@mesh.test',
       '$2y$10$sWnnaOnQVfL4X0Yai.R1MeE3VFKz1EZ8QWmeIIEVt0WGVQG4UAPHa',
       'Mentenanță', TRUE
FROM rol AS r
WHERE r.denumire = 'mecanic'
  AND NOT EXISTS (SELECT 1 FROM utilizator WHERE email = 'mecanic@mesh.test');

INSERT INTO utilizator (id_rol, nume_complet, email, parola_hash, departament, activ)
SELECT r.id_rol, 'Administrator Mentenanță Demo', 'admin.mentenanta@mesh.test',
       '$2y$10$sWnnaOnQVfL4X0Yai.R1MeE3VFKz1EZ8QWmeIIEVt0WGVQG4UAPHa',
       'Mentenanță', TRUE
FROM rol AS r
WHERE r.denumire = 'administrator_departament'
  AND NOT EXISTS (SELECT 1 FROM utilizator WHERE email = 'admin.mentenanta@mesh.test');

INSERT INTO utilizator (id_rol, nume_complet, email, parola_hash, departament, activ)
SELECT r.id_rol, 'Administrator General Demo', 'admin@mesh.test',
       '$2y$10$sWnnaOnQVfL4X0Yai.R1MeE3VFKz1EZ8QWmeIIEVt0WGVQG4UAPHa',
       NULL, TRUE
FROM rol AS r
WHERE r.denumire = 'administrator_general'
  AND NOT EXISTS (SELECT 1 FROM utilizator WHERE email = 'admin@mesh.test');
