-- =====================================================================
-- CrochetLab - Migracion 07: firma digital de comprobantes de pedido
--
-- Cada fila es una VERSION firmada del comprobante de un pedido:
--   contenido    : el texto JSON exacto que se firmo (no se vuelve a generar al verificar)
--   firma        : firma RSA-2048 + SHA-256 en base64 (hecha con la llave PRIVADA, que no esta en la base)
--   huella_llave : huella SHA-256 de la llave PUBLICA con la que se debe verificar
-- Si alguien altera 'contenido' o 'firma' en la base, la verificacion con la llave publica falla.
--
-- Ejecutar UNA vez (es seguro repetirla):
--   psql -U postgres -d sistema_login -f database/migraciones/migracion_07_firma_digital.sql
-- Despues vuelve a ejecutar database/usuario_app.sql para darle permisos a crochetlab_app.
-- =====================================================================
SET client_encoding = 'UTF8';

BEGIN;

CREATE TABLE IF NOT EXISTS pedido_firmas (
    id            SERIAL PRIMARY KEY,
    pedido_id     INTEGER     NOT NULL REFERENCES pedidos(id) ON DELETE CASCADE,
    version       INTEGER     NOT NULL,
    contenido     TEXT        NOT NULL,
    firma         TEXT        NOT NULL,
    huella_llave  VARCHAR(120) NOT NULL,
    firmado_por   INTEGER     REFERENCES usuarios(id) ON DELETE SET NULL,
    firmado_en    TIMESTAMP   NOT NULL DEFAULT now(),
    UNIQUE (pedido_id, version)
);

CREATE INDEX IF NOT EXISTS idx_pedido_firmas_pedido ON pedido_firmas (pedido_id, version DESC);

COMMIT;
