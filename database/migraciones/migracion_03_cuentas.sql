-- =====================================================================
-- CrochetLab - Migración 03: cuentas (bloqueo, 2FA, recuperación)
--
-- Ejecutar UNA sola vez, ANTES de copiar el código del paso 3:
--   psql -U postgres -d sistema_login -f migracion_03_cuentas.sql
-- =====================================================================

BEGIN;

ALTER TABLE usuarios
    ADD COLUMN intentos_fallidos INTEGER   NOT NULL DEFAULT 0,
    ADD COLUMN bloqueado_hasta   TIMESTAMP,
    ADD COLUMN totp_secreto      VARCHAR(64),
    ADD COLUMN totp_activo       BOOLEAN   NOT NULL DEFAULT FALSE,
    ADD COLUMN totp_ultimo_paso  BIGINT    NOT NULL DEFAULT 0;

-- Tokens de recuperación de contraseña (solo se guarda el hash SHA-256)
CREATE TABLE recuperacion_tokens (
    id         BIGSERIAL PRIMARY KEY,
    usuario_id INTEGER   NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    token_hash CHAR(64)  NOT NULL UNIQUE,
    expira     TIMESTAMP NOT NULL,
    usado      BOOLEAN   NOT NULL DEFAULT FALSE,
    ip         VARCHAR(45),
    fecha      TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Códigos de respaldo del segundo factor (un solo uso, solo se guarda el hash)
CREATE TABLE codigos_respaldo (
    id          SERIAL PRIMARY KEY,
    usuario_id  INTEGER  NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    codigo_hash CHAR(64) NOT NULL,
    usado       BOOLEAN  NOT NULL DEFAULT FALSE,
    fecha_uso   TIMESTAMP,
    UNIQUE (usuario_id, codigo_hash)
);

CREATE INDEX idx_recuperacion_usuario ON recuperacion_tokens(usuario_id, fecha DESC);
CREATE INDEX idx_codigos_usuario      ON codigos_respaldo(usuario_id);

COMMIT;
