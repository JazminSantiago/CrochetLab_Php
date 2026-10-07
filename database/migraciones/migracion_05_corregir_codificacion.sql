-- =====================================================================
-- CrochetLab - Migracion 05: corrige tildes y enes danadas en la BD
--
-- Sintoma: textos como "configuraciÃ³n" o "catÃ¡logo" en Roles y Permisos.
-- Causa: psql en Windows leyo las migraciones (UTF-8) como WIN1252.
--
-- Ejecutar UNA vez:
--   psql -U postgres -d sistema_login -f migracion_05_corregir_codificacion.sql
-- (Es seguro repetirla: solo toca textos que aun contienen el caracter danado.)
-- =====================================================================
SET client_encoding = 'UTF8';

BEGIN;

UPDATE roles
SET descripcion = convert_from(convert_to(descripcion, 'WIN1252'), 'UTF8')
WHERE descripcion LIKE '%' || chr(195) || '%' OR descripcion LIKE '%' || chr(194) || '%';

UPDATE permisos
SET descripcion = convert_from(convert_to(descripcion, 'WIN1252'), 'UTF8')
WHERE descripcion LIKE '%' || chr(195) || '%' OR descripcion LIKE '%' || chr(194) || '%';

COMMIT;
