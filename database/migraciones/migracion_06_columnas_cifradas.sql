-- =====================================================================
-- CrochetLab - Migracion 06: columnas preparadas para datos cifrados
--
-- Un valor cifrado con AES-256-GCM ocupa bastante mas que el texto original
-- (prefijo + IV + etiqueta + base64), asi que las columnas con limite de
-- longitud se amplian a TEXT.
--
-- Ejecutar UNA vez (es seguro repetirla):
--   psql -U postgres -d sistema_login -f migracion_06_columnas_cifradas.sql
-- Despues de esto, cifrar los datos que ya existian:
--   php database/cifrar_datos_existentes.php
-- =====================================================================
SET client_encoding = 'UTF8';

BEGIN;

ALTER TABLE empleados ALTER COLUMN telefono         TYPE TEXT;
ALTER TABLE pedidos   ALTER COLUMN cliente_contacto TYPE TEXT;
ALTER TABLE usuarios  ALTER COLUMN totp_secreto     TYPE TEXT;

COMMIT;
