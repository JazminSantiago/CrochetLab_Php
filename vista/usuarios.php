<?php
// vista/usuarios.php
// Administración de usuarios: lista, cambio de rol, activar/desactivar y desbloquear.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/autorizacion.php';
require_once __DIR__ . '/_admin_layout.php';
require_once __DIR__ . '/../controlador/usuarios_ctrl.php';
requierePermiso('usuarios', 'lectura');

$ctrl          = new UsuariosCtrl();
$db            = $ctrl->db();
$actorId       = (int)$_SESSION['usuario_id'];
$actorSuper    = esSuperadmin($db, $actorId);
$puedeEscribir = tienePermiso('usuarios', 'escritura');

$q      = trim((string)($_GET['q'] ?? ''));
$rolF   = ctype_digit((string)($_GET['rol'] ?? '')) ? (int)$_GET['rol'] : 0;
$estF   = in_array($_GET['estado'] ?? '', ['activos', 'inactivos', 'bloqueados'], true) ? $_GET['estado'] : '';
$pagina = max(1, (int)($_GET['p'] ?? 1));

$roles = listarRolesResumen($db);
$res   = $ctrl->listar($q, $rolF, $estF, $pagina);
$privPorRol = [];
foreach ($roles as $r) { $privPorRol[(int)$r['id']] = (bool)$r['privilegiado']; }

function urlUsuarios(array $cambios = []): string
{
    global $q, $rolF, $estF, $res;
    $base = ['q' => $q, 'rol' => $rolF ?: '', 'estado' => $estF, 'p' => $res['pagina']];
    return '?' . http_build_query(array_filter(array_merge($base, $cambios), fn($v) => $v !== '' && $v !== null));
}

adminInicio('Usuarios', 'Todas las cuentas registradas. Los cambios quedan en la auditoría.');
?>
        <form class="filtros" method="GET">
            <label class="campo">Buscar
                <input type="text" name="q" value="<?php echo h($q); ?>" placeholder="Usuario, nombre o correo">
            </label>
            <label class="campo">Rol
                <select name="rol">
                    <option value="">Todos</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo (int)$r['id']; ?>" <?php echo $rolF === (int)$r['id'] ? 'selected' : ''; ?>><?php echo h($r['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="campo">Estado
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="activos"    <?php echo $estF === 'activos'    ? 'selected' : ''; ?>>Activos</option>
                    <option value="inactivos"  <?php echo $estF === 'inactivos'  ? 'selected' : ''; ?>>Desactivados</option>
                    <option value="bloqueados" <?php echo $estF === 'bloqueados' ? 'selected' : ''; ?>>Bloqueados</option>
                </select>
            </label>
            <button class="btn" type="submit">Filtrar</button>
            <a class="btn sec" href="usuarios.php">Limpiar</a>
        </form>

        <div class="tabla-wrap">
        <?php if (!$res['filas']): ?>
            <div class="vacio">No hay usuarios con esos filtros.</div>
        <?php else: ?>
            <table>
                <thead><tr><th>Usuario</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($res['filas'] as $u):
                    $esYo      = (int)$u['id'] === $actorId;
                    $protegido = $u['es_superadmin'] || (!$actorSuper && !empty($privPorRol[(int)$u['rol_id']]));
                    $editable  = $puedeEscribir && !$esYo && !$protegido;
                ?>
                    <tr>
                        <td>
                            <strong><?php echo h($u['nombre']); ?></strong>
                            <?php if ($esYo): ?><span class="badge neu">tú</span><?php endif; ?>
                            <?php if ($u['es_superadmin']): ?><span class="badge sup">superadmin</span><?php endif; ?>
                            <div class="sub2">@<?php echo h($u['usuario']); ?> · <?php echo h($u['email']); ?></div>
                        </td>
                        <td>
                            <?php if ($editable): ?>
                            <form method="POST" action="../controlador/usuarios_ctrl.php" class="acciones">
                                <?php echo campoCsrf(); ?>
                                <input type="hidden" name="accion" value="cambiar_rol">
                                <input type="hidden" name="usuario_id" value="<?php echo (int)$u['id']; ?>">
                                <select class="mini" name="rol_id" aria-label="Rol de <?php echo h($u['usuario']); ?>">
                                    <?php foreach ($roles as $r):
                                        $bloq = !$actorSuper && $r['privilegiado']; ?>
                                        <option value="<?php echo (int)$r['id']; ?>"
                                            <?php echo (int)$r['id'] === (int)$u['rol_id'] ? 'selected' : ''; ?>
                                            <?php echo $bloq ? 'disabled' : ''; ?>><?php echo h($r['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn chico" type="submit">Guardar</button>
                            </form>
                            <?php else: ?>
                                <span class="badge neu"><?php echo h($u['rol']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $u['activo'] ? 'ok' : 'mal'; ?>"><?php echo $u['activo'] ? 'Activo' : 'Desactivado'; ?></span>
                            <?php if ($u['bloqueado']): ?><span class="badge warn" title="Hasta <?php echo h(substr((string)$u['bloqueado_hasta'], 0, 16)); ?>">Bloqueado</span><?php endif; ?>
                            <?php if ($u['totp_activo']): ?><span class="badge neu">2FA</span><?php endif; ?>
                        </td>
                        <td class="sub2"><?php echo $u['ultimo_acceso'] ? h(substr((string)$u['ultimo_acceso'], 0, 16)) : 'Nunca'; ?></td>
                        <td>
                            <?php if ($puedeEscribir && !$esYo && !$u['es_superadmin']): ?>
                            <div class="acciones">
                                <?php if ($u['bloqueado']): ?>
                                <form method="POST" action="../controlador/usuarios_ctrl.php">
                                    <?php echo campoCsrf(); ?>
                                    <input type="hidden" name="accion" value="desbloquear">
                                    <input type="hidden" name="usuario_id" value="<?php echo (int)$u['id']; ?>">
                                    <button class="btn chico ok" type="submit">Desbloquear</button>
                                </form>
                                <?php endif; ?>
                                <?php if ($editable): ?>
                                <form method="POST" action="../controlador/usuarios_ctrl.php"
                                      onsubmit="return confirm('<?php echo $u['activo'] ? '¿Desactivar' : '¿Activar'; ?> la cuenta de <?php echo h(addslashes($u['usuario'])); ?>?');">
                                    <?php echo campoCsrf(); ?>
                                    <input type="hidden" name="accion" value="<?php echo $u['activo'] ? 'desactivar' : 'activar'; ?>">
                                    <input type="hidden" name="usuario_id" value="<?php echo (int)$u['id']; ?>">
                                    <button class="btn chico <?php echo $u['activo'] ? 'peligro' : 'ok'; ?>" type="submit"><?php echo $u['activo'] ? 'Desactivar' : 'Activar'; ?></button>
                                </form>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>

        <div class="pie">
            <div><?php echo number_format($res['total']); ?> usuario(s) · página <?php echo $res['pagina']; ?> de <?php echo $res['paginas']; ?></div>
            <div class="pag">
                <?php if ($res['pagina'] > 1): ?><a href="<?php echo h(urlUsuarios(['p' => $res['pagina'] - 1])); ?>">← Anterior</a><?php else: ?><span>← Anterior</span><?php endif; ?>
                <?php if ($res['pagina'] < $res['paginas']): ?><a href="<?php echo h(urlUsuarios(['p' => $res['pagina'] + 1])); ?>">Siguiente →</a><?php else: ?><span>Siguiente →</span><?php endif; ?>
            </div>
        </div>
        <?php if (!$actorSuper): ?>
            <p class="sub2" style="margin-top:12px;">Las cuentas con permisos de administración solo las puede modificar un superadministrador.</p>
        <?php endif; ?>
<?php adminFin(); ?>
