-- Forzar UTF-8 para que psql en Windows no corrompa tildes ni enes
SET client_encoding = 'UTF8';

-- =====================================================================
-- CrochetLab - Migración 02: historial de accesos y auditoría
--
-- Ejecutar UNA sola vez:
--   psql -U postgres -d sistema_login -f migracion_02_auditoria.sql
-- =====================================================================

BEGIN;

-- Cada inicio de sesión (correcto o fallido) y cada cierre de sesión
CREATE TABLE historial_accesos (
    id                BIGSERIAL PRIMARY KEY,
    usuario_id        INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    usuario_intentado VARCHAR(50),                       -- lo que se escribió en el formulario
    evento            VARCHAR(20) NOT NULL
                      CHECK (evento IN ('login_ok', 'login_fallido', 'logout')),
    motivo            VARCHAR(100),                      -- p. ej. password_incorrecta
    ip                VARCHAR(45) NOT NULL,
    user_agent        VARCHAR(255),
    fecha             TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Acciones de los usuarios sobre los datos (crear, editar, eliminar, etc.)
CREATE TABLE auditoria (
    id             BIGSERIAL PRIMARY KEY,
    usuario_id     INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    usuario_nombre VARCHAR(50),                          -- copia del nombre por si se borra el usuario
    accion         VARCHAR(30) NOT NULL,
    area           VARCHAR(50) NOT NULL,
    registro_id    INTEGER,
    detalle        VARCHAR(500),
    ip             VARCHAR(45) NOT NULL,
    fecha          TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_historial_fecha   ON historial_accesos(fecha DESC);
CREATE INDEX idx_historial_usuario ON historial_accesos(usuario_id);
CREATE INDEX idx_historial_evento  ON historial_accesos(evento);
CREATE INDEX idx_auditoria_fecha   ON auditoria(fecha DESC);
CREATE INDEX idx_auditoria_usuario ON auditoria(usuario_id);
CREATE INDEX idx_auditoria_accion  ON auditoria(accion);

COMMIT;
