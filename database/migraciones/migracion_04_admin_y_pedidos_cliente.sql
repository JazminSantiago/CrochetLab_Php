-- Forzar UTF-8 para que psql en Windows no corrompa tildes ni enes
SET client_encoding = 'UTF8';

-- =====================================================================
-- CrochetLab - Migración 04: panel de administración y pedidos del cliente
--
-- Ejecutar UNA sola vez, ANTES de copiar el código del paso 4:
--   psql -U postgres -d sistema_login -f migracion_04_admin_y_pedidos_cliente.sql
--
-- Qué hace:
--   1. Crea los permisos del área "mis_pedidos" (el cliente crea y consulta SUS pedidos).
--   2. Los asigna al Usuario Regular y al Administrador (que tiene todos los permisos).
--   3. Actualiza la descripción del rol Usuario Regular (cliente: catálogo y sus pedidos).
--      (El panel del cliente deja de mostrar patrones en el código del paso 4.)
--   4. Índices de apoyo.
-- =====================================================================

BEGIN;

INSERT INTO permisos (area, accion, descripcion) VALUES
    ('mis_pedidos', 'lectura',   'Ver mis pedidos'),
    ('mis_pedidos', 'escritura', 'Crear pedidos y cancelar mis pedidos pendientes');

UPDATE permisos
SET descripcion = 'Consultar el catálogo de productos (solo lectura)'
WHERE area = 'contenido' AND accion = 'lectura';

-- Administrador: todos los permisos (incluye los nuevos)
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT r.id, p.id
FROM roles r
JOIN permisos p ON p.area = 'mis_pedidos'
WHERE r.nombre = 'Administrador'
ON CONFLICT DO NOTHING;

-- Usuario Regular (cliente): catálogo + sus pedidos
INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT r.id, p.id
FROM roles r
JOIN permisos p ON p.area = 'mis_pedidos'
WHERE r.nombre = 'Usuario Regular'
ON CONFLICT DO NOTHING;

UPDATE roles
SET descripcion = 'Cliente: consulta el catálogo y crea y consulta sus propios pedidos.'
WHERE nombre = 'Usuario Regular';

CREATE INDEX IF NOT EXISTS idx_pedidos_creado_por ON pedidos(creado_por, fecha_pedido DESC);

COMMIT;
