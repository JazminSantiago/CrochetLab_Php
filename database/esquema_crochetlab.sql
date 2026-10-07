-- Forzar UTF-8 para que psql en Windows no corrompa tildes ni enes
SET client_encoding = 'UTF8';

-- =====================================================================
-- CrochetLab - Esquema PostgreSQL RECONSTRUIDO a partir del código PHP
-- Base de datos: sistema_login
--
-- Uso (desde PowerShell, con PostgreSQL instalado):
--   createdb -U postgres sistema_login
--   psql -U postgres -d sistema_login -f esquema_crochetlab.sql
--
-- NOTA: se dedujo leyendo todas las consultas SQL del proyecto. Si en tu
-- PC viejo todavía existe la base real, compara con:
--   pg_dump -U postgres -s sistema_login > esquema_original.sql
-- =====================================================================

BEGIN;

-- ---------------------------------------------------------------------
-- USUARIOS
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id             SERIAL PRIMARY KEY,
    usuario        VARCHAR(50)  NOT NULL UNIQUE,
    nombre         VARCHAR(100) NOT NULL,
    email          VARCHAR(150) NOT NULL UNIQUE,
    password       VARCHAR(255) NOT NULL,              -- hash bcrypt
    rol            VARCHAR(20)  NOT NULL DEFAULT 'tejedor'
                   CHECK (rol IN ('admin', 'tejedor')),
    activo         BOOLEAN      NOT NULL DEFAULT TRUE,
    es_superadmin  BOOLEAN      NOT NULL DEFAULT FALSE,
    fecha_registro TIMESTAMP    NOT NULL DEFAULT NOW(),
    ultimo_acceso  TIMESTAMP
);

CREATE TABLE empleados (
    id            SERIAL PRIMARY KEY,
    usuario_id    INTEGER NOT NULL UNIQUE
                  REFERENCES usuarios(id) ON DELETE CASCADE,
    telefono      VARCHAR(30),
    direccion     TEXT,
    fecha_ingreso DATE NOT NULL DEFAULT CURRENT_DATE,
    especialidad  VARCHAR(100)
);

-- ---------------------------------------------------------------------
-- CATÁLOGO
-- ---------------------------------------------------------------------
CREATE TABLE categorias (
    id     SERIAL PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE
);

CREATE TABLE catalogo (
    id                      SERIAL PRIMARY KEY,
    categoria_id            INTEGER REFERENCES categorias(id) ON DELETE SET NULL,
    nombre                  VARCHAR(150) NOT NULL,
    descripcion             TEXT,
    precio                  NUMERIC(10,2),
    stock_actual            INTEGER NOT NULL DEFAULT 0,
    stock_minimo            INTEGER NOT NULL DEFAULT 5,
    tiempo_elaboracion_dias INTEGER,
    imagen_ruta             VARCHAR(255),
    activo                  BOOLEAN NOT NULL DEFAULT TRUE
);

-- ---------------------------------------------------------------------
-- PEDIDOS
-- ---------------------------------------------------------------------
CREATE TABLE pedidos (
    id                SERIAL PRIMARY KEY,
    tipo              VARCHAR(20) NOT NULL
                      CHECK (tipo IN ('estandar', 'personalizado')),
    catalogo_id       INTEGER REFERENCES catalogo(id) ON DELETE SET NULL,
    descripcion       TEXT,
    imagen_referencia VARCHAR(255),
    cliente_nombre    VARCHAR(150),
    cliente_contacto  VARCHAR(150),
    fecha_entrega     DATE NOT NULL,
    estado            VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                      CHECK (estado IN ('pendiente','en_proceso','completado','entregado','cancelado')),
    prioridad         VARCHAR(20) NOT NULL DEFAULT 'normal'
                      CHECK (prioridad IN ('baja','normal','alta','urgente')),
    entrega_domicilio BOOLEAN NOT NULL DEFAULT FALSE,
    costo_envio       NUMERIC(10,2) NOT NULL DEFAULT 0,
    direccion_entrega TEXT,
    creado_por        INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    fecha_pedido      TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ---------------------------------------------------------------------
-- ASIGNACIONES (pedido <-> tejedor). empleado_id apunta a usuarios.id
-- ---------------------------------------------------------------------
CREATE TABLE asignaciones (
    id                  SERIAL PRIMARY KEY,
    pedido_id           INTEGER NOT NULL REFERENCES pedidos(id)  ON DELETE CASCADE,
    empleado_id         INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    progreso            INTEGER NOT NULL DEFAULT 0 CHECK (progreso BETWEEN 0 AND 100),
    notas               TEXT,
    imagen_prueba       VARCHAR(255),
    estado              VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                        CHECK (estado IN ('pendiente','en_progreso','terminado')),
    fecha_asignacion    TIMESTAMP NOT NULL DEFAULT NOW(),
    fecha_actualizacion TIMESTAMP,
    fecha_fin_real      DATE
);

-- El código PHP actualiza solo "progreso", pero otras vistas leen "estado" y
-- "fecha_fin_real". Este trigger los mantiene coherentes (supuesto: la base
-- original hacía algo equivalente).
CREATE OR REPLACE FUNCTION asignaciones_sync_estado() RETURNS TRIGGER AS $$
BEGIN
    IF NEW.progreso >= 100 THEN
        NEW.estado := 'terminado';
        IF NEW.fecha_fin_real IS NULL THEN
            NEW.fecha_fin_real := CURRENT_DATE;
        END IF;
    ELSIF NEW.progreso > 0 THEN
        NEW.estado := 'en_progreso';
        NEW.fecha_fin_real := NULL;
    ELSE
        NEW.estado := 'pendiente';
        NEW.fecha_fin_real := NULL;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_asignaciones_sync_estado
    BEFORE INSERT OR UPDATE OF progreso ON asignaciones
    FOR EACH ROW EXECUTE FUNCTION asignaciones_sync_estado();

-- ---------------------------------------------------------------------
-- PATRONES y BONUSES
-- ---------------------------------------------------------------------
CREATE TABLE patrones (
    id                  SERIAL PRIMARY KEY,
    catalogo_id         INTEGER REFERENCES catalogo(id) ON DELETE SET NULL,
    titulo              VARCHAR(150) NOT NULL,
    instrucciones       TEXT,
    imagen_ruta         VARCHAR(255),
    estado              VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                        CHECK (estado IN ('pendiente','aprobado','rechazado')),
    creado_por          INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    es_contribucion     BOOLEAN NOT NULL DEFAULT FALSE,
    fecha_creacion      TIMESTAMP NOT NULL DEFAULT NOW(),
    fecha_actualizacion TIMESTAMP
);

CREATE TABLE bonuses (
    id          SERIAL PRIMARY KEY,
    empleado_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    patron_id   INTEGER REFERENCES patrones(id) ON DELETE SET NULL,
    motivo      TEXT,
    fecha       TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ---------------------------------------------------------------------
-- LOG DE PERMISOS (el código lo escribe al promover/degradar admins)
-- ---------------------------------------------------------------------
CREATE TABLE log_permisos (
    id         SERIAL PRIMARY KEY,
    usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    accion     VARCHAR(50) NOT NULL,
    detalle    TEXT,
    fecha      TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ---------------------------------------------------------------------
-- ÍNDICES
-- ---------------------------------------------------------------------
CREATE INDEX idx_pedidos_estado        ON pedidos(estado);
CREATE INDEX idx_pedidos_fecha_entrega ON pedidos(fecha_entrega);
CREATE INDEX idx_asignaciones_pedido   ON asignaciones(pedido_id);
CREATE INDEX idx_asignaciones_empleado ON asignaciones(empleado_id);
CREATE INDEX idx_patrones_estado       ON patrones(estado);
CREATE INDEX idx_log_permisos_fecha    ON log_permisos(fecha DESC);

-- ---------------------------------------------------------------------
-- DATOS INICIALES
-- ---------------------------------------------------------------------
INSERT INTO categorias (nombre) VALUES
    ('Bolsos'), ('Ropa'), ('Amigurumis'), ('Accesorios');

-- Superadmin inicial. Genera el hash con:
--   php -r "echo password_hash('TuContraseñaSegura', PASSWORD_BCRYPT, ['cost'=>12]);"
-- y reemplaza PEGA_AQUI_EL_HASH. Luego descomenta el INSERT.
--
-- INSERT INTO usuarios (usuario, nombre, email, password, rol, activo, es_superadmin)
-- VALUES ('admin', 'Administrador', 'admin@crochetlab.local',
--         'PEGA_AQUI_EL_HASH', 'admin', TRUE, TRUE);

COMMIT;
