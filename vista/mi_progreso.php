<?php
// vista/patrones/mi_progreso.php  →  mover a: vista/mi_progreso.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../controlador/patrones_ctrl.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'tejedor') {
    header('Location: ../index.php'); exit();
}

// ── Conexión directa para queries de progreso ──
require_once __DIR__ . '/../modelo/conexion.php';
$db = (new Conexion())->conectar();
$uid = $_SESSION['usuario_id'];
$nombre = $_SESSION['nombre'];

// Obtener empleado_id
$stmtEmp = $db->prepare("SELECT id FROM empleados WHERE usuario_id = :uid");
$stmtEmp->execute([':uid' => $uid]);
$empleado = $stmtEmp->fetch();
$empleado_id = $_SESSION['usuario_id'];

// ── Stats generales ──
$stats = $db->prepare("
    SELECT
        COUNT(*) AS total,
        COUNT(*) FILTER (WHERE a.estado = 'terminado') AS completadas,
        COUNT(*) FILTER (WHERE a.estado = 'en_progreso') AS en_progreso,
        COUNT(*) FILTER (WHERE a.estado = 'pendiente') AS pendientes,
        COUNT(*) FILTER (WHERE a.estado = 'terminado' AND a.fecha_fin_real <= p.fecha_entrega) AS a_tiempo,
        COUNT(*) FILTER (WHERE a.estado = 'terminado' AND a.fecha_fin_real > p.fecha_entrega) AS tarde
    FROM asignaciones a
    JOIN pedidos p ON a.pedido_id = p.id
    WHERE a.empleado_id = :eid
");
$stats->execute([':eid' => $empleado_id]);
$s = $stats->fetch();

$total       = (int)$s['total'];
$completadas = (int)$s['completadas'];
$en_progreso = (int)$s['en_progreso'];
$pendientes  = (int)$s['pendientes'];
$a_tiempo    = (int)$s['a_tiempo'];
$tarde       = (int)$s['tarde'];
$puntualidad = $completadas > 0 ? round(($a_tiempo / $completadas) * 100) : 0;

// ── Actividad por mes (últimos 6 meses) ──
$actividad = $db->prepare("
    SELECT
        TO_CHAR(DATE_TRUNC('month', a.fecha_asignacion), 'Mon') AS mes,
        DATE_TRUNC('month', a.fecha_asignacion) AS mes_orden,
        COUNT(*) AS total,
        COUNT(*) FILTER (WHERE a.estado = 'terminado') AS hechas
    FROM asignaciones a
    WHERE a.empleado_id = :eid
      AND a.fecha_asignacion >= NOW() - INTERVAL '6 months'
    GROUP BY DATE_TRUNC('month', a.fecha_asignacion)
    ORDER BY mes_orden ASC
");
$actividad->execute([':eid' => $empleado_id]);
$meses = $actividad->fetchAll();

// ── Últimas asignaciones ──
$recientes = $db->prepare("
    SELECT a.*, p.cliente_nombre, p.fecha_entrega, p.prioridad,
           c.nombre AS producto_nombre
    FROM asignaciones a
    JOIN pedidos p ON a.pedido_id = p.id
    LEFT JOIN catalogo c ON p.catalogo_id = c.id
    WHERE a.empleado_id = :eid
    ORDER BY a.fecha_asignacion DESC
    LIMIT 8
");
$recientes->execute([':eid' => $empleado_id]);
$historial = $recientes->fetchAll();

// ── Bonuses ──
$ctrlP   = new PatronesCtrl();
$bonuses = $ctrlP->bonusesPorEmpleado($uid);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrochetLab — Mi Progreso</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    <style>
        :root {
            --sky:#7ABFCC; --sky-pale:#e8f5f8; --navy:#2C3E6B;
            --coral:#F2907A; --lavender:#C9B8E8;
            --mint:#8ECFC0; --yellow:#F7CE7A; --white:#ffffff;
            --text-main:#2C3E6B; --text-soft:#6a7fa8;
            --border:#daedf2; --bg:#f0f8fa;
        }
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Nunito',sans-serif; background:var(--bg); color:var(--text-main); min-height:100vh; }

        /* NAVBAR */
        .navbar {
            background:var(--white); border-bottom:1px solid var(--border);
            padding:0 32px; height:64px; display:flex; align-items:center;
            justify-content:space-between; position:sticky; top:0; z-index:200;
            box-shadow:0 2px 16px rgba(44,62,107,0.07);
        }
        .navbar::before {
            content:''; position:absolute; top:0;left:0;right:0; height:4px;
            background:linear-gradient(90deg,var(--coral),var(--yellow),var(--lavender),var(--mint),var(--sky));
        }
        .navbar-left { display:flex; align-items:center; gap:16px; }
        .back-btn {
            display:flex; align-items:center; gap:6px; color:var(--text-soft);
            text-decoration:none; font-size:13px; font-weight:700; padding:6px 12px;
            border-radius:8px; border:1px solid var(--border); background:var(--sky-pale); transition:all 0.2s;
        }
        .back-btn:hover { color:var(--navy); border-color:var(--sky); }
        .page-title { font-family:'DM Serif Display',serif; font-size:20px; color:var(--navy); }
        .navbar-brand { display:flex; align-items:center; gap:10px; text-decoration:none; }
        .navbar-brand img { width:32px; height:32px; border-radius:50%; object-fit:cover; }
        .navbar-brand span { font-family:'DM Serif Display',serif; font-size:20px; color:var(--navy); }
        .navbar-brand span em { color:var(--coral); font-style:normal; }

        .container { max-width:1100px; margin:0 auto; padding:32px 24px 60px; }

        /* HERO BANNER */
        .hero {
            background:linear-gradient(135deg, var(--navy) 0%, #3d5491 100%);
            border-radius:20px; padding:32px 40px; margin-bottom:32px;
            display:flex; align-items:center; justify-content:space-between; gap:20px;
            position:relative; overflow:hidden;
            animation: fadeUp 0.5s ease both;
        }
        .hero::before {
            content:'📊'; position:absolute; right:32px; top:50%;
            transform:translateY(-50%); font-size:96px; opacity:0.08; pointer-events:none;
        }
        .hero-text h1 { font-family:'DM Serif Display',serif; font-size:26px; color:white; margin-bottom:6px; }
        .hero-text p  { font-size:14px; color:rgba(255,255,255,0.65); }
        .hero-puntualidad {
            background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.2);
            border-radius:16px; padding:18px 24px; text-align:center; flex-shrink:0;
        }
        .hero-puntualidad .pct {
            font-family:'DM Serif Display',serif; font-size:40px;
            color:<?php echo $puntualidad >= 80 ? '#8ECFC0' : ($puntualidad >= 50 ? '#F7CE7A' : '#F2907A'); ?>;
            line-height:1;
        }
        .hero-puntualidad .lbl { font-size:11px; font-weight:800; color:rgba(255,255,255,0.6); text-transform:uppercase; letter-spacing:0.07em; margin-top:4px; }

        /* STATS GRID */
        .stats-grid {
            display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));
            gap:16px; margin-bottom:32px;
            animation: fadeUp 0.5s ease 0.1s both;
        }
        .stat-card {
            background:var(--white); border-radius:14px; border:1.5px solid var(--border);
            padding:20px 22px; box-shadow:0 4px 16px rgba(44,62,107,0.05);
            display:flex; align-items:center; gap:14px;
        }
        .stat-icon {
            width:44px; height:44px; border-radius:12px;
            display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0;
        }
        .stat-num { font-family:'DM Serif Display',serif; font-size:28px; color:var(--navy); line-height:1; }
        .stat-lbl { font-size:12px; color:var(--text-soft); font-weight:700; margin-top:2px; }

        .stat-total    .stat-icon { background:rgba(44,62,107,0.1); }
        .stat-done     .stat-icon { background:rgba(142,207,192,0.2); }
        .stat-progress .stat-icon { background:rgba(122,191,204,0.2); }
        .stat-pending  .stat-icon { background:rgba(247,206,122,0.2); }
        .stat-bonus    .stat-icon { background:rgba(242,144,122,0.15); }

        /* SECTION TITLE */
        .section-title {
            font-family:'DM Serif Display',serif; font-size:18px; color:var(--navy);
            margin-bottom:16px; display:flex; align-items:center; gap:10px;
        }
        .section-title::after { content:''; flex:1; height:1px; background:var(--border); }

        /* GRÁFICA DE BARRAS */
        .chart-card {
            background:var(--white); border-radius:16px; border:1.5px solid var(--border);
            padding:24px 28px; margin-bottom:32px; box-shadow:0 4px 16px rgba(44,62,107,0.05);
            animation: fadeUp 0.5s ease 0.15s both;
        }
        .chart-bars {
            display:flex; align-items:flex-end; gap:12px; height:120px; margin-top:16px;
        }
        .bar-group { flex:1; display:flex; flex-direction:column; align-items:center; gap:6px; }
        .bar-wrap { width:100%; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; flex:1; gap:3px; }
        .bar {
            width:100%; border-radius:6px 6px 0 0; min-height:4px;
            transition:height 0.6s cubic-bezier(0.22,1,0.36,1);
        }
        .bar-total { background:var(--border); }
        .bar-hechas { background:var(--mint); }
        .bar-mes { font-size:11px; font-weight:700; color:var(--text-soft); }
        .chart-legend { display:flex; gap:16px; margin-top:12px; }
        .legend-item { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-soft); font-weight:700; }
        .legend-dot { width:10px; height:10px; border-radius:3px; }

        /* TABLA HISTORIAL */
        .table-card {
            background:var(--white); border-radius:16px; border:1.5px solid var(--border);
            overflow:hidden; box-shadow:0 4px 16px rgba(44,62,107,0.05); margin-bottom:32px;
            animation: fadeUp 0.5s ease 0.2s both;
        }
        table { width:100%; border-collapse:collapse; }
        thead { background:var(--sky-pale); }
        th {
            padding:12px 16px; text-align:left; font-size:11px; font-weight:800;
            text-transform:uppercase; letter-spacing:0.07em; color:var(--text-soft);
            border-bottom:1px solid var(--border);
        }
        td { padding:13px 16px; font-size:13px; border-bottom:1px solid var(--border); vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tbody tr { transition:background 0.15s; }
        tbody tr:hover { background:var(--sky-pale); }

        .badge {
            display:inline-flex; align-items:center; gap:4px; border-radius:20px;
            font-size:11px; font-weight:700; padding:3px 10px;
        }
        .badge-terminado  { background:rgba(142,207,192,0.2); color:#1a6b53; }
        .badge-en_progreso{ background:rgba(122,191,204,0.2); color:#1a6b7a; }
        .badge-pendiente  { background:rgba(247,206,122,0.2); color:#9a7000; }
        .badge-aceptado   { background:rgba(201,184,232,0.2); color:#5a3e8a; }

        .prio-alta   { color:#b94030; font-weight:800; font-size:11px; }
        .prio-media  { color:#9a7000; font-weight:800; font-size:11px; }
        .prio-normal { color:var(--text-soft); font-size:11px; }

        .puntual-si  { color:#1a6b53; font-weight:700; font-size:12px; }
        .puntual-no  { color:#b94030; font-weight:700; font-size:12px; }
        .puntual-nd  { color:var(--text-soft); font-size:12px; }

        /* BONUSES */
        .bonuses-grid {
            display:grid; grid-template-columns:repeat(auto-fill, minmax(280px,1fr));
            gap:14px; animation: fadeUp 0.5s ease 0.25s both;
        }
        .bonus-item {
            background:var(--white); border:1.5px solid var(--border); border-radius:12px;
            padding:14px 16px; display:flex; align-items:center; gap:12px;
            box-shadow:0 2px 8px rgba(44,62,107,0.04);
        }
        .bonus-icon { font-size:24px; flex-shrink:0; }
        .bonus-motivo { font-size:13px; font-weight:700; color:var(--navy); }
        .bonus-fecha  { font-size:11px; color:var(--text-soft); margin-top:2px; }

        .empty-state { text-align:center; padding:40px 20px; color:var(--text-soft); }
        .empty-state .icon { font-size:40px; margin-bottom:10px; }

        @keyframes fadeUp {
            from { opacity:0; transform:translateY(12px); }
            to   { opacity:1; transform:translateY(0); }
        }
        @media(max-width:768px) {
            .navbar { padding:0 16px; }
            .container { padding:20px 14px 48px; }
            .hero { padding:24px 20px; flex-wrap:wrap; }
            .hero::before { display:none; }
            .stats-grid { grid-template-columns:1fr 1fr; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="navbar-left">
        <a href="menu_tejedor.php" class="back-btn">← Menú</a>
        <span class="page-title">📊 Mi Progreso</span>
    </div>
    <a class="navbar-brand" href="menu_tejedor.php">
        <img src="../assets/img/logo.png" alt="CrochetLab">
        <span>Crochet<em>Lab</em></span>
    </a>
</nav>

<div class="container">

    <!-- HERO -->
    <div class="hero">
        <div class="hero-text">
            <h1>Tu desempeño, <?php echo htmlspecialchars($nombre); ?> 🧶</h1>
            <p>Resumen de tu actividad y pedidos completados en CrochetLab</p>
        </div>
        <div class="hero-puntualidad">
            <div class="pct"><?php echo $puntualidad; ?>%</div>
            <div class="lbl">Puntualidad</div>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card stat-total">
            <div class="stat-icon">📋</div>
            <div>
                <div class="stat-num"><?php echo $total; ?></div>
                <div class="stat-lbl">Total asignaciones</div>
            </div>
        </div>
        <div class="stat-card stat-done">
            <div class="stat-icon">✅</div>
            <div>
                <div class="stat-num"><?php echo $completadas; ?></div>
                <div class="stat-lbl">Completadas</div>
            </div>
        </div>
        <div class="stat-card stat-progress">
            <div class="stat-icon">🔄</div>
            <div>
                <div class="stat-num"><?php echo $en_progreso; ?></div>
                <div class="stat-lbl">En progreso</div>
            </div>
        </div>
        <div class="stat-card stat-pending">
            <div class="stat-icon">⏳</div>
            <div>
                <div class="stat-num"><?php echo $pendientes; ?></div>
                <div class="stat-lbl">Pendientes</div>
            </div>
        </div>
        <div class="stat-card stat-bonus">
            <div class="stat-icon">⭐</div>
            <div>
                <div class="stat-num"><?php echo count($bonuses); ?></div>
                <div class="stat-lbl">Bonuses ganados</div>
            </div>
        </div>
    </div>

    <!-- GRÁFICA ACTIVIDAD -->
    <?php if (!empty($meses)): ?>
    <div class="chart-card">
        <div class="section-title">Actividad últimos 6 meses</div>
        <?php
        $maxTotal = max(array_column($meses, 'total')) ?: 1;
        ?>
        <div class="chart-bars">
            <?php foreach ($meses as $m): ?>
            <?php
                $hTotal  = round(($m['total'] / $maxTotal) * 100);
                $hHechas = $m['total'] > 0 ? round(($m['hechas'] / $m['total']) * $hTotal) : 0;
            ?>
            <div class="bar-group">
                <div class="bar-wrap">
                    <div class="bar bar-hechas" style="height:<?php echo $hHechas; ?>px"></div>
                    <div class="bar bar-total"  style="height:<?php echo max(0,$hTotal-$hHechas); ?>px"></div>
                </div>
                <div class="bar-mes"><?php echo htmlspecialchars($m['mes']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="chart-legend">
            <div class="legend-item"><div class="legend-dot" style="background:var(--mint)"></div> Completadas</div>
            <div class="legend-item"><div class="legend-dot" style="background:var(--border)"></div> Asignadas</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- HISTORIAL -->
    <div class="section-title">Historial de asignaciones</div>
    <div class="table-card">
        <?php if (empty($historial)): ?>
        <div class="empty-state">
            <div class="icon">📋</div>
            <p>No tienes asignaciones registradas aún.</p>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Producto / Cliente</th>
                    <th>Prioridad</th>
                    <th>Fecha entrega</th>
                    <th>Estado</th>
                    <th>Puntualidad</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historial as $a): ?>
                <tr>
                    <td>
                        <div style="font-weight:700;color:var(--navy)"><?php echo htmlspecialchars($a['producto_nombre'] ?? '—'); ?></div>
                        <div style="font-size:12px;color:var(--text-soft)"><?php echo htmlspecialchars($a['cliente_nombre'] ?? '—'); ?></div>
                    </td>
                    <td>
                        <?php
                            $prio = strtolower($a['prioridad'] ?? 'normal');
                            $prioClass = $prio === 'alta' ? 'prio-alta' : ($prio === 'media' ? 'prio-media' : 'prio-normal');
                            $prioIcon  = $prio === 'alta' ? '⚠️' : ($prio === 'media' ? '🔶' : '🔹');
                        ?>
                        <span class="<?php echo $prioClass; ?>"><?php echo $prioIcon; ?> <?php echo ucfirst($prio); ?></span>
                    </td>
                    <td style="font-size:12px">
                        <?php echo $a['fecha_entrega'] ? date('d/m/Y', strtotime($a['fecha_entrega'])) : '—'; ?>
                    </td>
                    <td>
                        <?php
                            $est = $a['estado'] ?? 'pendiente';
                            $labels = ['terminado'=>'✅ Terminado','en_progreso'=>'🔄 En progreso','pendiente'=>'⏳ Pendiente','aceptado'=>'👍 Aceptado'];
                        ?>
                        <span class="badge badge-<?php echo $est; ?>"><?php echo $labels[$est] ?? ucfirst($est); ?></span>
                    </td>
                    <td>
                        <?php if ($est === 'terminado' && $a['fecha_fin_real'] && $a['fecha_entrega']): ?>
                            <?php if ($a['fecha_fin_real'] <= $a['fecha_entrega']): ?>
                                <span class="puntual-si">✓ A tiempo</span>
                            <?php else: ?>
                                <span class="puntual-no">✗ Tarde</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="puntual-nd">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- BONUSES -->
    <?php if (!empty($bonuses)): ?>
    <div class="section-title">⭐ Mis bonuses por contribuciones</div>
    <div class="bonuses-grid">
        <?php foreach ($bonuses as $b): ?>
        <div class="bonus-item">
            <div class="bonus-icon">🏅</div>
            <div>
                <div class="bonus-motivo"><?php echo htmlspecialchars($b['motivo']); ?></div>
                <div class="bonus-fecha"><?php echo date('d/m/Y', strtotime($b['fecha'])); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>
</body>
</html>