<?php
// vista/roles.php
// Roles y permisos: lista de roles, creación y matriz de permisos (área × acción).
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/autorizacion.php';
require_once __DIR__ . '/_admin_layout.php';
require_once __DIR__ . '/../controlador/roles_ctrl.php';
requierePermiso('roles', 'lectura');

$ctrl           = new RolesCtrl();
$db             = $ctrl->db();
$actorSuper     = esSuperadmin($db, (int)$_SESSION['usuario_id']);
$puedeEscribir  = tienePermiso('roles', 'escritura');
$puedeEliminar  = tienePermiso('roles', 'eliminacion');

$roles = listarRolesResumen($db);

// Rol seleccionado (por defecto el primero que no sea Administrador)
$selId = ctype_digit((string)($_GET['rol'] ?? '')) ? (int)$_GET['rol'] : 0;
$sel   = null;
foreach ($roles as $r) { if ((int)$r['id'] === $selId) { $sel = $r; } }
if (!$sel) {
    foreach ($roles as $r) { if ($r['nombre'] !== NOMBRE_ROL_ADMIN) { $sel = $r; break; } }
    $sel = $sel ?? $roles[0] ?? null;
}

$permisos  = $ctrl->permisosDisponibles();
$tiene     = $sel ? array_flip($ctrl->permisosDeRol((int)$sel['id'])) : [];
$privil    = array_flip(idsPermisosPrivilegiados($db));
$esAdminRol = $sel && $sel['nombre'] === NOMBRE_ROL_ADMIN;
$editable  = $sel && $puedeEscribir && !$esAdminRol;

// Agrupa los permisos por área: [area => [accion => permiso]]
$matriz = [];
foreach ($permisos as $p) { $matriz[$p['area']][$p['accion']] = $p; }

$areas = [
    'dashboard' => 'Dashboard', 'catalogo' => 'Catálogo (gestión)', 'pedidos' => 'Pedidos (gestión)',
    'empleados' => 'Empleados', 'asignaciones' => 'Asignaciones (todas)', 'reportes' => 'Reportes',
    'patrones' => 'Patrones (gestión)', 'usuarios' => 'Usuarios', 'roles' => 'Roles y permisos',
    'auditoria' => 'Auditoría y accesos', 'mis_asignaciones' => 'Mis asignaciones (tejedor)',
    'mis_patrones' => 'Mis patrones (tejedor)', 'mi_progreso' => 'Mi progreso (tejedor)',
    'mis_pedidos' => 'Mis pedidos (cliente)', 'contenido' => 'Catálogo (solo ver)',
];

adminInicio('Roles y Permisos', 'Define qué puede hacer cada rol en cada área. Los permisos se aplican en el servidor en cada petición.');
?>
        <div class="grid2">
            <div>
                <div class="panel">
                    <h2>Roles</h2>
                    <?php foreach ($roles as $r): ?>
                        <a class="rol-item <?php echo $sel && (int)$r['id'] === (int)$sel['id'] ? 'activo' : ''; ?>" href="?rol=<?php echo (int)$r['id']; ?>">
                            <b><?php echo h($r['nombre']); ?>
                                <?php if ($r['es_sistema']): ?><span class="badge neu">base</span><?php endif; ?>
                                <?php if ($r['privilegiado']): ?><span class="badge sup" title="Incluye permisos de administración">🔒</span><?php endif; ?>
                            </b>
                            <span class="sub2"><?php echo (int)$r['usuarios']; ?> usuario(s) · <?php echo (int)$r['permisos']; ?> permiso(s)</span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($puedeEscribir): ?>
                <div class="panel">
                    <h2>Crear rol</h2>
                    <form method="POST" action="../controlador/roles_ctrl.php" autocomplete="off">
                        <?php echo campoCsrf(); ?>
                        <input type="hidden" name="accion" value="crear_rol">
                        <label class="campo">Nombre
                            <input type="text" name="nombre" required minlength="3" maxlength="50" placeholder="Ej. Supervisor">
                        </label>
                        <label class="campo">Descripción (opcional)
                            <input type="text" name="descripcion" maxlength="200">
                        </label>
                        <label class="campo">Partir de los permisos de
                            <select name="copiar_de">
                                <option value="">— Ninguno (rol vacío) —</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?php echo (int)$r['id']; ?>"><?php echo h($r['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button class="btn" type="submit">Crear rol</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <div>
            <?php if ($sel): ?>
                <div class="panel">
                    <h2>Permisos de «<?php echo h($sel['nombre']); ?>»</h2>
                    <?php if (!empty($sel['descripcion'])): ?><p class="sub2" style="margin-bottom:10px;"><?php echo h($sel['descripcion']); ?></p><?php endif; ?>
                    <?php if ($esAdminRol): ?>
                        <div class="mensaje aviso">El rol Administrador siempre tiene todos los permisos y no se puede modificar.</div>
                    <?php elseif (!$puedeEscribir): ?>
                        <div class="mensaje aviso">Solo lectura: tu rol no puede modificar permisos.</div>
                    <?php elseif (!$actorSuper): ?>
                        <div class="mensaje aviso">Los permisos marcados con 🔒 son de administración y solo los puede cambiar un superadministrador.</div>
                    <?php endif; ?>

                    <form method="POST" action="../controlador/roles_ctrl.php">
                        <?php echo campoCsrf(); ?>
                        <input type="hidden" name="accion" value="guardar_permisos">
                        <input type="hidden" name="rol_id" value="<?php echo (int)$sel['id']; ?>">
                        <div class="tabla-wrap">
                            <table class="matriz">
                                <thead><tr><th>Área</th><th>Lectura</th><th>Escritura</th><th>Eliminación</th></tr></thead>
                                <tbody>
                                <?php foreach ($areas as $clave => $etq):
                                    if (!isset($matriz[$clave])) continue; ?>
                                    <tr>
                                        <td><?php echo h($etq); ?></td>
                                        <?php foreach (['lectura', 'escritura', 'eliminacion'] as $acc):
                                            $p = $matriz[$clave][$acc] ?? null; ?>
                                            <td>
                                            <?php if ($p):
                                                $pid  = (int)$p['id'];
                                                $esPriv = isset($privil[$pid]);
                                                $dis  = !$editable || ($esPriv && !$actorSuper); ?>
                                                <input type="checkbox" name="permisos[]" value="<?php echo $pid; ?>"
                                                       title="<?php echo h($p['descripcion']); ?>"
                                                       <?php echo ($esAdminRol || isset($tiene[$pid])) ? 'checked' : ''; ?>
                                                       <?php echo $dis ? 'disabled' : ''; ?>>
                                                <?php if ($esPriv): ?>🔒<?php endif; ?>
                                            <?php else: ?>
                                                <span class="sub2">—</span>
                                            <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($editable): ?>
                        <div class="pie-form">
                            <button class="btn" type="submit">Guardar permisos</button>
                            <span class="sub2">Pasa el cursor sobre una casilla para ver qué permite.</span>
                        </div>
                        <?php endif; ?>
                    </form>

                    <?php if ($puedeEliminar && !$sel['es_sistema']): ?>
                    <form method="POST" action="../controlador/roles_ctrl.php" style="margin-top:16px;"
                          onsubmit="return confirm('¿Eliminar el rol «<?php echo h(addslashes($sel['nombre'])); ?>»? Esta acción no se puede deshacer.');">
                        <?php echo campoCsrf(); ?>
                        <input type="hidden" name="accion" value="eliminar_rol">
                        <input type="hidden" name="rol_id" value="<?php echo (int)$sel['id']; ?>">
                        <button class="btn peligro chico" type="submit" <?php echo (int)$sel['usuarios'] > 0 ? 'disabled title="Hay usuarios con este rol"' : ''; ?>>Eliminar este rol</button>
                    </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            </div>
        </div>
<?php adminFin(); ?>
