<?php
// vista/auditoria.php
// Consulta de solo lectura: historial de accesos y auditoría de acciones.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/autorizacion.php';
requierePermiso('auditoria', 'lectura');

$db = (new Conexion())->conectar();

// ── Parámetros (todos validados) ──
$tab    = (($_GET['t'] ?? 'accesos') === 'acciones') ? 'acciones' : 'accesos';
$q      = trim((string)($_GET['q'] ?? ''));
$filtro = trim((string)($_GET['f'] ?? ''));
$desde  = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['desde'] ?? '')) ? $_GET['desde'] : '';
$hasta  = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['hasta'] ?? '')) ? $_GET['hasta'] : '';
$pagina = max(1, (int)($_GET['p'] ?? 1));
$porPag = 50;

$where  = [];
$params = [];

if ($tab === 'accesos') {
    $eventos = ['login_ok' => 'Inicio correcto', 'login_fallido' => 'Inicio fallido', 'logout' => 'Cierre de sesión'];
    if ($q !== '') {
        $where[] = "COALESCE(u.usuario, h.usuario_intentado, '') ILIKE :q";
        $params[':q'] = '%' . addcslashes($q, '%_\\') . '%';
    }
    if ($filtro !== '' && isset($eventos[$filtro])) {
        $where[] = "h.evento = :f";
        $params[':f'] = $filtro;
    }
    if ($desde !== '') { $where[] = "h.fecha >= CAST(:desde AS date)";     $params[':desde'] = $desde; }
    if ($hasta !== '') { $where[] = "h.fecha <  CAST(:hasta AS date) + 1"; $params[':hasta'] = $hasta; }
    $cond = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $from = "FROM historial_accesos h LEFT JOIN usuarios u ON u.id = h.usuario_id $cond";
    $sqlDatos = "SELECT h.fecha, h.evento, h.motivo, h.ip, h.user_agent,
                        COALESCE(u.usuario, h.usuario_intentado) AS usuario
                 $from ORDER BY h.fecha DESC, h.id DESC";
} else {
    $acciones = $db->query("SELECT DISTINCT accion FROM auditoria ORDER BY accion")->fetchAll(PDO::FETCH_COLUMN);
    if ($q !== '') {
        $where[] = "COALESCE(a.usuario_nombre, u.usuario, '') ILIKE :q";
        $params[':q'] = '%' . addcslashes($q, '%_\\') . '%';
    }
    if ($filtro !== '' && in_array($filtro, $acciones, true)) {
        $where[] = "a.accion = :f";
        $params[':f'] = $filtro;
    }
    if ($desde !== '') { $where[] = "a.fecha >= CAST(:desde AS date)";     $params[':desde'] = $desde; }
    if ($hasta !== '') { $where[] = "a.fecha <  CAST(:hasta AS date) + 1"; $params[':hasta'] = $hasta; }
    $cond = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $from = "FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id $cond";
    $sqlDatos = "SELECT a.fecha, a.accion, a.area, a.registro_id, a.detalle, a.ip,
                        COALESCE(a.usuario_nombre, u.usuario) AS usuario
                 $from ORDER BY a.fecha DESC, a.id DESC";
}

$stmt = $db->prepare("SELECT COUNT(*) $from");
$stmt->execute($params);
$total   = (int)$stmt->fetchColumn();
$paginas = max(1, (int)ceil($total / $porPag));
$pagina  = min($pagina, $paginas);
$offset  = ($pagina - 1) * $porPag;

$stmt = $db->prepare($sqlDatos . " LIMIT $porPag OFFSET $offset");
$stmt->execute($params);
$filas = $stmt->fetchAll();

function urlAud(array $cambios = []): string
{
    global $tab, $q, $filtro, $desde, $hasta, $pagina;
    $base = ['t' => $tab, 'q' => $q, 'f' => $filtro, 'desde' => $desde, 'hasta' => $hasta, 'p' => $pagina];
    return '?' . http_build_query(array_filter(array_merge($base, $cambios), fn($v) => $v !== '' && $v !== null));
}
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrochetLab — Auditoría y Accesos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    <style>
        :root { --sky:#7ABFCC; --sky-pale:#e8f5f8; --navy:#2C3E6B; --navy-light:#3d5491; --coral:#F2907A; --coral-dark:#d97060;
                --mint:#8ECFC0; --white:#fff; --text-soft:#6a7fa8; --border:#daedf2; --bg:#f0f8fa; }
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Nunito',sans-serif; background:var(--bg); color:var(--navy); min-height:100vh; }
        .navbar { background:var(--white); border-bottom:1px solid var(--border); padding:0 24px; height:64px; display:flex; align-items:center; gap:16px; }
        .back-btn { text-decoration:none; color:var(--navy-light); font-weight:700; padding:8px 14px; border:1.5px solid var(--border); border-radius:10px; }
        .navbar-brand { font-family:'DM Serif Display',serif; font-size:20px; color:var(--navy); text-decoration:none; }
        .navbar-brand em { color:var(--coral); font-style:normal; }
        .container { max-width:1200px; margin:0 auto; padding:28px 20px 56px; }
        h1 { font-family:'DM Serif Display',serif; font-size:28px; margin-bottom:4px; }
        .sub { color:var(--text-soft); margin-bottom:20px; }
        .tabs { display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap; }
        .tab { text-decoration:none; padding:9px 18px; border-radius:999px; font-weight:700; border:1.5px solid var(--border); color:var(--navy-light); background:var(--white); }
        .tab.active { background:var(--navy); color:var(--white); border-color:var(--navy); }
        form.filtros { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; background:var(--white); border:1.5px solid var(--border); border-radius:14px; padding:14px 16px; margin-bottom:16px; }
        .campo { display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:700; color:var(--text-soft); }
        .campo input, .campo select { font:inherit; padding:8px 10px; border:1.5px solid var(--border); border-radius:8px; color:var(--navy); background:var(--white); }
        .btn { font:inherit; font-weight:700; padding:9px 18px; border-radius:10px; border:0; background:var(--coral); color:#fff; cursor:pointer; text-decoration:none; }
        .btn.sec { background:var(--sky-pale); color:var(--navy-light); }
        .tabla-wrap { background:var(--white); border:1.5px solid var(--border); border-radius:14px; overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:13.5px; }
        thead { background:var(--sky-pale); }
        th, td { text-align:left; padding:11px 14px; border-bottom:1px solid var(--border); vertical-align:top; }
        th { font-size:11.5px; text-transform:uppercase; letter-spacing:.05em; color:var(--text-soft); }
        tr:last-child td { border-bottom:0; }
        .badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:800; white-space:nowrap; }
        .ok   { background:rgba(142,207,192,.25); color:#2f8a78; }
        .mal  { background:rgba(242,144,122,.22); color:#c0513c; }
        .neu  { background:var(--sky-pale); color:var(--navy-light); }
        .ua   { color:var(--text-soft); font-size:12px; max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .vacio { padding:34px; text-align:center; color:var(--text-soft); }
        .pie { display:flex; justify-content:space-between; align-items:center; margin-top:14px; color:var(--text-soft); font-size:13px; flex-wrap:wrap; gap:10px; }
        .pag a, .pag span { display:inline-block; padding:7px 14px; border-radius:8px; border:1.5px solid var(--border); background:var(--white); color:var(--navy-light); text-decoration:none; font-weight:700; margin-left:6px; }
        .pag span { opacity:.45; }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="menu_principal.php" class="back-btn">← Menú</a>
        <a class="navbar-brand" href="menu_principal.php">Crochet<em>Lab</em></a>
    </nav>

    <div class="container">
        <h1>Auditoría y Accesos</h1>
        <p class="sub">Registro de solo lectura. Las fechas están en la hora del servidor.</p>

        <div class="tabs">
            <a class="tab <?php echo $tab === 'accesos'  ? 'active' : ''; ?>" href="?t=accesos">🔑 Historial de accesos</a>
            <a class="tab <?php echo $tab === 'acciones' ? 'active' : ''; ?>" href="?t=acciones">📝 Acciones de usuarios</a>
        </div>

        <form class="filtros" method="GET">
            <input type="hidden" name="t" value="<?php echo h($tab); ?>">
            <label class="campo">Usuario
                <input type="text" name="q" value="<?php echo h($q); ?>" placeholder="Buscar usuario">
            </label>
            <label class="campo"><?php echo $tab === 'accesos' ? 'Evento' : 'Acción'; ?>
                <select name="f">
                    <option value="">Todos</option>
                    <?php if ($tab === 'accesos'): foreach ($eventos as $k => $v): ?>
                        <option value="<?php echo h($k); ?>" <?php echo $filtro === $k ? 'selected' : ''; ?>><?php echo h($v); ?></option>
                    <?php endforeach; else: foreach ($acciones as $a): ?>
                        <option value="<?php echo h($a); ?>" <?php echo $filtro === $a ? 'selected' : ''; ?>><?php echo h($a); ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </label>
            <label class="campo">Desde <input type="date" name="desde" value="<?php echo h($desde); ?>"></label>
            <label class="campo">Hasta <input type="date" name="hasta" value="<?php echo h($hasta); ?>"></label>
            <button class="btn" type="submit">Filtrar</button>
            <a class="btn sec" href="?t=<?php echo h($tab); ?>">Limpiar</a>
        </form>

        <div class="tabla-wrap">
        <?php if (!$filas): ?>
            <div class="vacio">No hay registros con esos filtros.</div>
        <?php elseif ($tab === 'accesos'): ?>
            <table>
                <thead><tr><th>Fecha</th><th>Usuario</th><th>Evento</th><th>Motivo</th><th>IP</th><th>Navegador</th></tr></thead>
                <tbody>
                <?php foreach ($filas as $f):
                    $clase = $f['evento'] === 'login_ok' ? 'ok' : ($f['evento'] === 'login_fallido' ? 'mal' : 'neu'); ?>
                    <tr>
                        <td><?php echo h($f['fecha']); ?></td>
                        <td><?php echo h($f['usuario'] ?? '—'); ?></td>
                        <td><span class="badge <?php echo $clase; ?>"><?php echo h($eventos[$f['evento']] ?? $f['evento']); ?></span></td>
                        <td><?php echo h($f['motivo'] ?? ''); ?></td>
                        <td><?php echo h($f['ip']); ?></td>
                        <td><div class="ua" title="<?php echo h($f['user_agent']); ?>"><?php echo h($f['user_agent']); ?></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <table>
                <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Área</th><th>ID</th><th>Detalle</th><th>IP</th></tr></thead>
                <tbody>
                <?php foreach ($filas as $f): ?>
                    <tr>
                        <td><?php echo h($f['fecha']); ?></td>
                        <td><?php echo h($f['usuario'] ?? '—'); ?></td>
                        <td><span class="badge <?php echo $f['accion'] === 'acceso_denegado' ? 'mal' : 'neu'; ?>"><?php echo h($f['accion']); ?></span></td>
                        <td><?php echo h($f['area']); ?></td>
                        <td><?php echo h($f['registro_id'] ?? ''); ?></td>
                        <td><?php echo h($f['detalle'] ?? ''); ?></td>
                        <td><?php echo h($f['ip']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>

        <div class="pie">
            <div><?php echo number_format($total); ?> registro(s) · página <?php echo $pagina; ?> de <?php echo $paginas; ?></div>
            <div class="pag">
                <?php if ($pagina > 1): ?><a href="<?php echo h(urlAud(['p' => $pagina - 1])); ?>">← Anterior</a><?php else: ?><span>← Anterior</span><?php endif; ?>
                <?php if ($pagina < $paginas): ?><a href="<?php echo h(urlAud(['p' => $pagina + 1])); ?>">Siguiente →</a><?php else: ?><span>Siguiente →</span><?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
