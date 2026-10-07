<?php
// modelo/admin_util.php
// Reglas compartidas del panel de administración (usuarios, roles y permisos).
//
// Un permiso es "de administración" (privilegiado) si permite gestionar cuentas o roles:
//   usuarios.escritura, roles.escritura, roles.eliminacion
// Quien lo posee puede, en la práctica, darse más poder a sí mismo. Por eso solo un
// superadministrador (usuarios.es_superadmin) puede conceder, quitar o tocar roles que
// los incluyan. Así un administrador "delegado" no puede escalar sus propios privilegios.

require_once __DIR__ . '/conexion.php';

const SQL_PERMISO_PRIVILEGIADO =
    "((p.area = 'usuarios' AND p.accion = 'escritura') OR (p.area = 'roles' AND p.accion IN ('escritura', 'eliminacion')))";

const NOMBRE_ROL_ADMIN = 'Administrador';

function esSuperadmin(PDO $db, $uid): bool
{
    $st = $db->prepare("SELECT es_superadmin FROM usuarios WHERE id = :id");
    $st->execute([':id' => $uid]);
    return (bool)$st->fetchColumn();
}

function rolEsPrivilegiado(PDO $db, $rolId): bool
{
    $st = $db->prepare(
        "SELECT EXISTS (SELECT 1 FROM rol_permisos rp JOIN permisos p ON p.id = rp.permiso_id
                        WHERE rp.rol_id = :r AND " . SQL_PERMISO_PRIVILEGIADO . ")"
    );
    $st->execute([':r' => $rolId]);
    return (bool)$st->fetchColumn();
}

// Ids de los permisos privilegiados (para filtrarlos al guardar la matriz)
function idsPermisosPrivilegiados(PDO $db): array
{
    $ids = $db->query("SELECT p.id FROM permisos p WHERE " . SQL_PERMISO_PRIVILEGIADO)->fetchAll(PDO::FETCH_COLUMN);
    return array_map('intval', $ids);
}

function idRolAdministrador(PDO $db): int
{
    $st = $db->prepare("SELECT id FROM roles WHERE nombre = :n");
    $st->execute([':n' => NOMBRE_ROL_ADMIN]);
    return (int)$st->fetchColumn();
}

// Administradores activos, sin contar a $excluirId (para no dejar el sistema sin administrador)
function contarAdminsActivos(PDO $db, int $excluirId): int
{
    $st = $db->prepare(
        "SELECT COUNT(*) FROM usuarios
         WHERE activo = TRUE AND rol_id = (SELECT id FROM roles WHERE nombre = :n) AND id <> :x"
    );
    $st->execute([':n' => NOMBRE_ROL_ADMIN, ':x' => $excluirId]);
    return (int)$st->fetchColumn();
}

// Roles con datos de resumen y la marca "privilegiado"
function listarRolesResumen(PDO $db): array
{
    return $db->query(
        "SELECT r.id, r.nombre, r.descripcion, r.es_sistema,
                (SELECT COUNT(*) FROM usuarios u WHERE u.rol_id = r.id)        AS usuarios,
                (SELECT COUNT(*) FROM rol_permisos rp WHERE rp.rol_id = r.id)  AS permisos,
                EXISTS (SELECT 1 FROM rol_permisos rp JOIN permisos p ON p.id = rp.permiso_id
                        WHERE rp.rol_id = r.id AND " . SQL_PERMISO_PRIVILEGIADO . ")      AS privilegiado
         FROM roles r
         ORDER BY r.es_sistema DESC, r.id ASC"
    )->fetchAll();
}
