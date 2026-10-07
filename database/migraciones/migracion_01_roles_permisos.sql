-- =====================================================================
-- CrochetLab - Migración 01: roles y permisos dinámicos (RBAC)
--
-- Ejecutar UNA sola vez, sobre la base ya creada con esquema_crochetlab.sql:
--   psql -U postgres -d sistema_login -f migracion_01_roles_permisos.sql
--
-- Qué hace:
--   1. Crea roles, permisos y rol_permisos.
--   2. Siembra los 3 roles (Administrador, Editor, Usuario Regular) y la
--      matriz de permisos por área y acción (lectura / escritura / eliminacion).
--   3. Migra usuarios.rol ('admin' / 'tejedor') a usuarios.rol_id
--      (admin -> Administrador, tejedor -> Editor) y elimina la columna vieja.
-- =====================================================================

BEGIN;

CREATE TABLE roles (
    id             SERIAL PRIMARY KEY,
    nombre         VARCHAR(50) NOT NULL UNIQUE,
    descripcion    TEXT,
    es_sistema     BOOLEAN NOT NULL DEFAULT FALSE,   -- los 3 roles base no se pueden borrar
    fecha_creacion TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE permisos (
    id          SERIAL PRIMARY KEY,
    area        VARCHAR(50) NOT NULL,
    accion      VARCHAR(20) NOT NULL CHECK (accion IN ('lectura', 'escritura', 'eliminacion')),
    descripcion VARCHAR(200),
    UNIQUE (area, accion)
);

CREATE TABLE rol_permisos (
    rol_id     INTEGER NOT NULL REFERENCES roles(id)    ON DELETE CASCADE,
    permiso_id INTEGER NOT NULL REFERENCES permisos(id) ON DELETE CASCADE,
    PRIMARY KEY (rol_id, permiso_id)
);

CREATE INDEX idx_rol_permisos_rol ON rol_permisos(rol_id);

-- ---------------------------------------------------------------------
-- Roles base
-- ---------------------------------------------------------------------
INSERT INTO roles (nombre, descripcion, es_sistema) VALUES
    ('Administrador',   'Acceso completo a todas las funcionalidades.', TRUE),
    ('Editor',          'Trabaja sus asignaciones y contribuye patrones. No gestiona usuarios ni configuración.', TRUE),
    ('Usuario Regular', 'Solo puede ver y consumir contenido (catálogo y patrones aprobados).', TRUE);

-- ---------------------------------------------------------------------
-- Permisos por área y acción
-- ---------------------------------------------------------------------
INSERT INTO permisos (area, accion, descripcion) VALUES
    ('dashboard',        'lectura',     'Ver el dashboard de productividad'),
    ('catalogo',         'lectura',     'Ver la gestión del catálogo'),
    ('catalogo',         'escritura',   'Crear, editar y activar/desactivar productos'),
    ('catalogo',         'eliminacion', 'Eliminar productos'),
    ('pedidos',          'lectura',     'Ver pedidos'),
    ('pedidos',          'escritura',   'Crear y editar pedidos, cambiar su estado'),
    ('pedidos',          'eliminacion', 'Eliminar pedidos'),
    ('empleados',        'lectura',     'Ver empleados'),
    ('empleados',        'escritura',   'Crear, editar y activar/desactivar empleados'),
    ('empleados',        'eliminacion', 'Eliminar empleados'),
    ('asignaciones',     'lectura',     'Ver todas las asignaciones'),
    ('asignaciones',     'escritura',   'Crear y editar asignaciones'),
    ('asignaciones',     'eliminacion', 'Eliminar asignaciones'),
    ('reportes',         'lectura',     'Ver reportes de productividad'),
    ('patrones',         'lectura',     'Ver la gestión de patrones'),
    ('patrones',         'escritura',   'Crear, editar, aprobar y rechazar patrones'),
    ('patrones',         'eliminacion', 'Eliminar patrones'),
    ('usuarios',         'lectura',     'Ver administradores y cambiar la propia contraseña'),
    ('usuarios',         'escritura',   'Promover y degradar administradores'),
    ('roles',            'lectura',     'Ver roles y permisos'),
    ('roles',            'escritura',   'Crear roles y asignar permisos'),
    ('roles',            'eliminacion', 'Eliminar roles'),
    ('auditoria',        'lectura',     'Consultar el registro de auditoría'),
    ('mis_asignaciones', 'lectura',     'Ver mis asignaciones'),
    ('mis_asignaciones', 'escritura',   'Actualizar mi progreso y subir pruebas'),
    ('mis_patrones',     'lectura',     'Consultar patrones aprobados'),
    ('mis_patrones',     'escritura',   'Enviar contribuciones de patrones'),
    ('mi_progreso',      'lectura',     'Ver mi historial y desempeño'),
    ('contenido',        'lectura',     'Consultar catálogo y patrones aprobados (solo lectura)');

-- Administrador: todos los permisos
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permisos p
WHERE r.nombre = 'Administrador';

-- Editor (antes "tejedor"): solo su espacio de trabajo
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT r.id, p.id FROM roles r
JOIN permisos p ON (p.area, p.accion) IN (
    ('mis_asignaciones', 'lectura'),
    ('mis_asignaciones', 'escritura'),
    ('mis_patrones',     'lectura'),
    ('mis_patrones',     'escritura'),
    ('mi_progreso',      'lectura')
)
WHERE r.nombre = 'Editor';

-- Usuario Regular: solo consumir contenido
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT r.id, p.id FROM roles r
JOIN permisos p ON (p.area, p.accion) IN (('contenido', 'lectura'))
WHERE r.nombre = 'Usuario Regular';

-- ---------------------------------------------------------------------
-- Migrar usuarios.rol -> usuarios.rol_id
-- ---------------------------------------------------------------------
ALTER TABLE usuarios ADD COLUMN rol_id INTEGER REFERENCES roles(id);

UPDATE usuarios u
SET rol_id = r.id
FROM roles r
WHERE r.nombre = CASE u.rol
                    WHEN 'admin'   THEN 'Administrador'
                    WHEN 'tejedor' THEN 'Editor'
                 END;

ALTER TABLE usuarios ALTER COLUMN rol_id SET NOT NULL;
ALTER TABLE usuarios DROP COLUMN rol;

CREATE INDEX idx_usuarios_rol ON usuarios(rol_id);

COMMIT;
