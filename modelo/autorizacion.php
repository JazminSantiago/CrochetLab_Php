<?php
// modelo/autorizacion.php
// Control de acceso basado en roles y permisos (RBAC).
//
// Uso en una vista o controlador:
//   require_once __DIR__ . '/../modelo/autorizacion.php';
//   requierePermiso('catalogo', 'lectura');      // corta la ejecución si no tiene el permiso
//   if (tienePermiso('catalogo', 'escritura')) { ... }
//
// Los permisos se leen de la base de datos en cada petición (no se guardan en la
// sesión), así que quitar un permiso o desactivar a un usuario surte efecto de inmediato.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/registro.php';
require_once __DIR__ . '/seguridad.php';

// Ruta base de la aplicación (vacía si corre en la raíz del servidor,
// "/CrochetLab" si corre en una subcarpeta).
function appBase(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    foreach (['/vista/', '/controlador/'] as $marca) {
        $pos = strpos($script, $marca);
        if ($pos !== false) {
            return substr($script, 0, $pos);
        }
    }
    // En Windows dirname() devuelve '\' en vez de '/': se normaliza antes de recortar
    return rtrim(str_replace('\\', '/', dirname($script)), '/');
}

function usuarioAutenticado(): bool
{
    return !empty($_SESSION['usuario_id']);
}

// Rol y permisos del usuario actual, consultados una vez por petición.
function contextoUsuario(): array
{
    static $ctx = null;
    if ($ctx !== null) {
        return $ctx;
    }

    $ctx = ['rol_id' => null, 'rol' => null, 'permisos' => [], 'totp' => false];
    if (!usuarioAutenticado()) {
        return $ctx;
    }

    $db = (new Conexion())->conectar();

    // Un usuario desactivado no tiene permisos aunque conserve la sesión.
    $stmt = $db->prepare(
        "SELECT u.rol_id, r.nombre AS rol, u.totp_activo
         FROM usuarios u
         JOIN roles r ON r.id = u.rol_id
         WHERE u.id = :id AND u.activo = TRUE"
    );
    $stmt->execute([':id' => $_SESSION['usuario_id']]);
    $fila = $stmt->fetch();
    if (!$fila) {
        return $ctx;
    }

    $ctx['rol_id'] = (int)$fila['rol_id'];
    $ctx['rol']    = $fila['rol'];
    $ctx['totp']   = (bool)$fila['totp_activo'];

    $stmt = $db->prepare(
        "SELECT p.area, p.accion
         FROM rol_permisos rp
         JOIN permisos p ON p.id = rp.permiso_id
         WHERE rp.rol_id = :rol"
    );
    $stmt->execute([':rol' => $ctx['rol_id']]);
    foreach ($stmt->fetchAll() as $p) {
        $ctx['permisos'][$p['area'] . '.' . $p['accion']] = true;
    }

    return $ctx;
}

function tienePermiso(string $area, string $accion): bool
{
    return isset(contextoUsuario()['permisos'][$area . '.' . $accion]);
}

// Página de inicio según lo que el usuario puede hacer (null si no tiene ninguna).
function rutaInicio(): ?string
{
    $base = appBase();
    if (tienePermiso('dashboard', 'lectura'))        return $base . '/vista/menu_principal.php';
    if (tienePermiso('mis_asignaciones', 'lectura')) return $base . '/vista/menu_tejedor.php';
    if (tienePermiso('contenido', 'lectura') || tienePermiso('mis_pedidos', 'lectura'))
        return $base . '/vista/menu_usuario.php';
    // Roles creados por el administrador: inicio genérico con los módulos que sí puede abrir
    if (modulosDisponibles())                        return $base . '/vista/inicio.php';
    return null;
}

// Módulos que el usuario actual puede abrir según sus permisos de lectura.
// Cada elemento: [área, ruta relativa a la raíz de la app, nombre, icono]
function modulosDisponibles(): array
{
    $mapa = [
        ['dashboard',        'vista/dashboard.php',                      'Dashboard',            '📊'],
        ['catalogo',         'vista/catalogo/index_catalogo.php',        'Catálogo',             '🧶'],
        ['pedidos',          'vista/pedidos/index_pedidos.php',          'Pedidos',              '📦'],
        ['empleados',        'vista/empleados/index_empleados.php',      'Empleados',            '🧑‍🔧'],
        ['asignaciones',     'vista/asignaciones/index_asignaciones.php','Asignaciones',         '📋'],
        ['reportes',         'vista/reportes/index_reportes.php',        'Reportes',             '📈'],
        ['patrones',         'vista/patrones/index_patrones.php',        'Patrones',             '📐'],
        ['usuarios',         'vista/usuarios.php',                       'Usuarios',             '👥'],
        ['roles',            'vista/roles.php',                          'Roles y permisos',     '🛡️'],
        ['auditoria',        'vista/auditoria.php',                      'Auditoría y accesos',  '🧾'],
        ['mis_asignaciones', 'vista/asignaciones/mis_asignaciones.php',  'Mis asignaciones',     '🧵'],
        ['mis_patrones',     'vista/patrones/mis_patrones.php',          'Patrones aprobados',   '📐'],
        ['mi_progreso',      'vista/mi_progreso.php',                    'Mi progreso',          '🏅'],
        ['mis_pedidos',      'vista/mis_pedidos.php',                    'Mis pedidos',          '🛍️'],
    ];
    $out = [];
    foreach ($mapa as [$area, $ruta, $nombre, $icono]) {
        if (tienePermiso($area, 'lectura')) {
            $out[] = ['ruta' => $ruta, 'nombre' => $nombre, 'icono' => $icono];
        }
    }
    return $out;
}

function cerrarSesionLocal(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

// 401 (sin sesión) o 403 (sin permiso)
function requierePermiso(string $area, string $accion): void
{
    if (!usuarioAutenticado()) {
        header('Location: ' . appBase() . '/index.php');
        exit();
    }
    if (!tienePermiso($area, $accion)) {
        denegarAcceso($area, $accion);
    }
    exigirSegundoFactorAdmin();
    verificarCsrf();   // en peticiones POST
}

// Páginas que solo necesitan una sesión válida (p. ej. "Mi cuenta"), sin un permiso concreto.
function requiereSesion(): void
{
    if (!usuarioAutenticado()) {
        header('Location: ' . appBase() . '/index.php');
        exit();
    }
    if (contextoUsuario()['rol_id'] === null) {   // cuenta desactivada o sin rol
        cerrarSesionLocal();
        header('Location: ' . appBase() . '/index.php');
        exit();
    }
}

// Quien puede gestionar usuarios debe tener activada la verificación en dos pasos.
function exigirSegundoFactorAdmin(): void
{
    if (!ADMIN_REQUIERE_2FA) {
        return;
    }
    if (tienePermiso('usuarios', 'escritura') && !contextoUsuario()['totp']) {
        header('Location: ' . appBase() . '/vista/mi_cuenta.php?forzar_2fa=1');
        exit();
    }
}

function denegarAcceso(string $area = '', string $accion = ''): void
{
    $inicio = rutaInicio();

    // Sin ningún permiso (cuenta desactivada o rol vacío): se cierra la sesión.
    if ($inicio === null) {
        cerrarSesionLocal();
        header('Location: ' . appBase() . '/index.php');
        exit();
    }

    auditar(
        'acceso_denegado',
        $area !== '' ? $area : 'desconocida',
        null,
        'Intentó ' . ($accion !== '' ? $accion : 'acceder') . ' en ' . ($_SERVER['SCRIPT_NAME'] ?? '')
    );

    http_response_code(403);
    $inicio = htmlspecialchars($inicio, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
       . '<title>Acceso denegado</title></head>'
       . '<body style="font-family:sans-serif;text-align:center;padding:60px 20px;color:#2C3E6B;background:#f0f8fa;">'
       . '<h1 style="font-size:64px;margin:0;">403</h1>'
       . '<h2>Acceso denegado</h2>'
       . '<p>No tienes permiso para realizar esta acción o ver esta página.</p>'
       . '<p><a href="' . $inicio . '" style="color:#d97060;font-weight:bold;">Volver a mi panel</a></p>'
       . '</body></html>';
    exit();
}
