-- =====================================================================
-- CrochetLab - Usuario de base de datos con permisos minimos
--
-- La aplicacion NO debe conectarse como "postgres" (superusuario): si alguien
-- lograra inyectar SQL, podria borrar o alterar toda la base. Este usuario
-- solo puede lo que la aplicacion necesita:
--   - leer, insertar, modificar y borrar datos de las tablas de la aplicacion;
--   - SOLO insertar y leer en las tablas de auditoria (historial_accesos,
--     auditoria, log_permisos): un atacante no puede borrar sus huellas;
--   - NO puede crear, alterar ni borrar tablas, ni vaciarlas (TRUNCATE).
-- Las migraciones se siguen aplicando con "postgres".
--
-- Ejecutar (cambia la contrasena!), dentro de la base de la aplicacion:
--   psql -U postgres -d sistema_login -v clave=UnaContrasenaLarga -f database/usuario_app.sql
-- Despues, en el .env:
--   DB_USER=crochetlab_app
--   DB_PASS=UnaContrasenaLarga
-- Es seguro repetirlo. VUELVE A EJECUTARLO despues de cada migracion nueva que
-- cree tablas, para que el usuario reciba permisos sobre ellas.
-- =====================================================================
\set ON_ERROR_STOP on
SET client_encoding = 'UTF8';

\if :{?clave}
\else
  \echo 'Falta la contrasena. Uso: psql -U postgres -d sistema_login -v clave=TuContrasena -f database/usuario_app.sql'
  \quit
\endif

-- Crea el usuario o, si ya existe, solo cambia su contrasena
SELECT CASE WHEN EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'crochetlab_app')
            THEN format('ALTER ROLE crochetlab_app WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD %L', :'clave')
            ELSE format('CREATE ROLE crochetlab_app WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD %L', :'clave')
       END
\gexec

BEGIN;

SELECT format('GRANT CONNECT ON DATABASE %I TO crochetlab_app', current_database())
\gexec

-- Que nadie pueda crear objetos en el esquema public (en PostgreSQL 15 o mas nuevo ya viene asi)
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO crochetlab_app;

-- Datos de la aplicacion
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO crochetlab_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO crochetlab_app;

-- Auditoria: solo anadir y leer (no se puede editar ni borrar el historial)
REVOKE UPDATE, DELETE, TRUNCATE ON historial_accesos, auditoria, log_permisos FROM crochetlab_app;

COMMIT;

\echo 'Listo: usuario crochetlab_app configurado. Actualiza DB_USER y DB_PASS en el .env.'
