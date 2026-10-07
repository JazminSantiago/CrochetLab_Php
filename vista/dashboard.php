<?php
// vista/dashboard.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';

require_once __DIR__ . '/../modelo/autorizacion.php';
requierePermiso('dashboard', 'lectura');

$conn = new Conexion();
$db   = $conn->conectar();

// ── Total pedidos activos ──
$totalActivos = $db->query(
    "SELECT COUNT(*) FROM pedidos WHERE estado NOT IN ('completado','entregado','cancelado')"
)->fetchColumn();

// ── Pedidos próximos a vencer esta semana (próximos 7 días, no vencidos) ──
$porVencer = $db->query(
    "SELECT COUNT(*) FROM pedidos
     WHERE estado NOT IN ('completado','entregado','cancelado')
     AND fecha_entrega BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days'"
)->fetchColumn();

// ── Pedidos vencidos ──
$vencidos = $db->query(
    "SELECT COUNT(*) FROM pedidos
     WHERE estado NOT IN ('completado','entregado','cancelado')
     AND fecha_entrega < CURRENT_DATE"
)->fetchColumn();

// ── Pedidos sin asignar ──
$sinAsignar = $db->query(
    "SELECT COUNT(*) FROM pedidos p
     WHERE p.estado NOT IN ('completado','entregado','cancelado')
     AND NOT EXISTS (SELECT 1 FROM asignaciones a WHERE a.pedido_id = p.id)"
)->fetchColumn();

// ── Stock crítico (stock_actual < stock_minimo) ──
$stockCritico = $db->query(
    "SELECT COUNT(*) FROM catalogo WHERE activo = true AND stock_actual < stock_minimo"
)->fetchColumn();

// ── Progreso general del equipo (promedio de todas las asignaciones activas) ──
$progresoEquipo = $db->query(
    "SELECT COALESCE(ROUND(AVG(a.progreso)), 0)
     FROM asignaciones a
     JOIN pedidos p ON a.pedido_id = p.id
     WHERE p.estado NOT IN ('completado','entregado','cancelado')"
)->fetchColumn();

// ── Pedidos por estado (para mini resumen) ──
$porEstado = $db->query(
    "SELECT estado, COUNT(*) as total FROM pedidos
     WHERE estado NOT IN ('entregado','cancelado')
     GROUP BY estado"
)->fetchAll();
$estadoMap = [];
foreach ($porEstado as $row) $estadoMap[$row['estado']] = $row['total'];

// ── Top tejedores por progreso promedio ──
$tejedores = $db->query(
    "SELECT u.nombre, ROUND(AVG(a.progreso)) as promedio, COUNT(a.id) as tareas
     FROM asignaciones a
     JOIN usuarios u ON a.empleado_id = u.id
     JOIN pedidos p ON a.pedido_id = p.id
     WHERE p.estado NOT IN ('completado','entregado','cancelado')
     GROUP BY u.id, u.nombre
     ORDER BY promedio DESC
     LIMIT 5"
)->fetchAll();

// ── Pedidos urgentes activos ──
$urgentes = $db->query(
    "SELECT COUNT(*) FROM pedidos
     WHERE prioridad = 'urgente'
     AND estado NOT IN ('completado','entregado','cancelado')"
)->fetchColumn();

$nombre = $_SESSION['nombre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrochetLab — Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    <style>
        :root {
            --sky:#7ABFCC; --sky-pale:#e8f5f8; --navy:#2C3E6B;
            --coral:#F2907A; --coral-dark:#d97060; --lavender:#C9B8E8;
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

        .container { max-width:1150px; margin:0 auto; padding:32px 24px 60px; }

        /* SALUDO */
        .greeting {
            margin-bottom:32px;
            animation: fadeUp 0.4s ease both;
        }
        .greeting h2 {
            font-family:'DM Serif Display',serif; font-size:26px; color:var(--navy); margin-bottom:4px;
        }
        .greeting p { font-size:14px; color:var(--text-soft); }

        /* SECCIÓN */
        .section-title {
            font-family:'DM Serif Display',serif; font-size:17px; color:var(--navy);
            margin-bottom:16px; display:flex; align-items:center; gap:10px;
        }
        .section-title::after { content:''; flex:1; height:1px; background:var(--border); }

        /* GRID STATS */
        .stats-grid {
            display:grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap:16px; margin-bottom:32px;
        }

        .stat-card {
            background:var(--white); border-radius:16px; border:1.5px solid var(--border);
            padding:22px 20px; position:relative; overflow:hidden;
            box-shadow:0 4px 16px rgba(44,62,107,0.05);
            transition:transform 0.2s, box-shadow 0.2s;
            text-decoration:none; color:inherit; display:block;
            animation: fadeUp 0.5s ease both;
        }
        .stat-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(44,62,107,0.10); }

        .stat-card::before {
            content:''; position:absolute; top:0;left:0;right:0; height:4px; border-radius:16px 16px 0 0;
        }
        .stat-card.sky::before    { background:linear-gradient(90deg,var(--sky),var(--mint)); }
        .stat-card.coral::before  { background:linear-gradient(90deg,var(--coral),var(--yellow)); }
        .stat-card.yellow::before { background:linear-gradient(90deg,var(--yellow),var(--coral)); }
        .stat-card.mint::before   { background:linear-gradient(90deg,var(--mint),var(--sky)); }
        .stat-card.lav::before    { background:linear-gradient(90deg,var(--lavender),var(--sky)); }
        .stat-card.navy::before   { background:linear-gradient(90deg,var(--navy),var(--sky)); }

        .stat-icon {
            width:44px; height:44px; border-radius:12px;
            display:flex; align-items:center; justify-content:center;
            font-size:22px; margin-bottom:14px;
        }
        .sky  .stat-icon { background:rgba(122,191,204,0.15); }
        .coral .stat-icon { background:rgba(242,144,122,0.15); }
        .yellow .stat-icon { background:rgba(247,206,122,0.15); }
        .mint  .stat-icon { background:rgba(142,207,192,0.15); }
        .lav   .stat-icon { background:rgba(201,184,232,0.15); }
        .navy  .stat-icon { background:rgba(44,62,107,0.10); }

        .stat-value {
            font-family:'DM Serif Display',serif; font-size:36px; color:var(--navy);
            line-height:1; margin-bottom:4px;
        }
        .stat-label { font-size:13px; color:var(--text-soft); font-weight:700; }
        .stat-sub   { font-size:11px; color:var(--text-soft); margin-top:6px; }

        /* Alerta badge en tarjeta */
        .stat-alert {
            position:absolute; top:16px; right:16px;
            background:rgba(242,144,122,0.2); color:#b94030;
            border:1px solid rgba(242,144,122,0.4);
            border-radius:20px; font-size:10px; font-weight:800;
            padding:3px 8px; text-transform:uppercase; letter-spacing:0.05em;
        }

        /* PROGRESO EQUIPO */
        .equipo-section {
            display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:32px;
        }

        .panel {
            background:var(--white); border-radius:16px; border:1.5px solid var(--border);
            padding:22px 24px; box-shadow:0 4px 16px rgba(44,62,107,0.05);
            animation: fadeUp 0.6s ease 0.1s both;
        }

        /* Barra progreso equipo */
        .big-progress { margin:16px 0 8px; }
        .big-prog-bar-bg {
            height:16px; background:var(--border); border-radius:8px; overflow:hidden;
        }
        .big-prog-bar {
            height:100%; border-radius:8px;
            background:linear-gradient(90deg, var(--sky), var(--mint));
            transition:width 1s ease;
        }
        .prog-numbers {
            display:flex; justify-content:space-between; align-items:center; margin-top:8px;
        }
        .prog-big-pct { font-family:'DM Serif Display',serif; font-size:32px; color:var(--navy); }
        .prog-note    { font-size:12px; color:var(--text-soft); text-align:right; }

        /* Lista tejedores */
        .tejedor-row {
            display:flex; align-items:center; gap:12px;
            padding:10px 0; border-bottom:1px solid var(--border);
        }
        .tejedor-row:last-child { border-bottom:none; }
        .tejedor-avatar {
            width:34px; height:34px; border-radius:50%; flex-shrink:0;
            background:linear-gradient(135deg, var(--mint), var(--sky));
            display:flex; align-items:center; justify-content:center;
            font-size:13px; font-weight:800; color:var(--navy);
        }
        .tejedor-info { flex:1; min-width:0; }
        .tejedor-nombre { font-size:13px; font-weight:800; color:var(--navy); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .tejedor-tareas { font-size:11px; color:var(--text-soft); }
        .tejedor-prog-wrap { width:100px; }
        .tejedor-prog-bg { height:6px; background:var(--border); border-radius:3px; overflow:hidden; margin-bottom:3px; }
        .tejedor-prog-bar { height:100%; border-radius:3px; background:var(--mint); }
        .tejedor-pct { font-size:11px; font-weight:800; color:var(--navy); text-align:right; }

        /* Mini estado pills */
        .estados-wrap { display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; }
        .estado-pill {
            display:flex; flex-direction:column; align-items:center;
            background:var(--sky-pale); border:1px solid var(--border);
            border-radius:12px; padding:10px 16px; min-width:80px;
        }
        .estado-pill .ep-num  { font-family:'DM Serif Display',serif; font-size:22px; color:var(--navy); }
        .estado-pill .ep-label{ font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-soft); margin-top:2px; }

        .empty-tejedores { text-align:center; padding:24px; color:var(--text-soft); font-size:13px; }

        @keyframes fadeUp {
            from { opacity:0; transform:translateY(12px); }
            to   { opacity:1; transform:translateY(0); }
        }

        @media(max-width:900px) {
            .equipo-section { grid-template-columns:1fr; }
        }
        @media(max-width:600px) {
            .stats-grid { grid-template-columns:1fr 1fr; }
            .navbar { padding:0 16px; }
            .container { padding:20px 14px 48px; }
        }
        @media(max-width:380px) {
            .stats-grid { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="navbar-left">
        <a href="menu_principal.php" class="back-btn">← Menú</a>
        <span class="page-title">📊 Dashboard</span>
    </div>
    <a class="navbar-brand" href="menu_principal.php">
        <img src="../assets/img/logo.png" alt="CrochetLab">
        <span>Crochet<em>Lab</em></span>
    </a>
</nav>

<div class="container">

    <div class="greeting">
        <h2>Buenos días, <?php echo htmlspecialchars($nombre); ?> 👋</h2>
        <p>Esto es lo que está pasando hoy · <?php echo date('l d \d\e F \d\e Y', strtotime('today')); ?></p>
    </div>

    <!-- ── TARJETAS RESUMEN ── -->
    <div class="section-title">Resumen general</div>
    <div class="stats-grid">

        <!-- Pedidos activos -->
        <a href="pedidos/index_pedidos.php" class="stat-card sky" style="animation-delay:0s">
            <div class="stat-icon">📦</div>
            <div class="stat-value"><?php echo $totalActivos; ?></div>
            <div class="stat-label">Pedidos activos</div>
            <div class="stat-sub">
                <?php echo $estadoMap['pendiente'] ?? 0; ?> pendientes ·
                <?php echo $estadoMap['en_proceso'] ?? 0; ?> en proceso
            </div>
        </a>

        <!-- Por vencer -->
        <a href="pedidos/index_pedidos.php" class="stat-card yellow" style="animation-delay:0.07s">
            <?php if ($porVencer > 0): ?>
            <div class="stat-alert">Esta semana</div>
            <?php endif; ?>
            <div class="stat-icon">⏰</div>
            <div class="stat-value"><?php echo $porVencer; ?></div>
            <div class="stat-label">Vencen esta semana</div>
            <div class="stat-sub">Próximos 7 días</div>
        </a>

        <!-- Vencidos -->
        <a href="pedidos/index_pedidos.php" class="stat-card coral" style="animation-delay:0.14s">
            <?php if ($vencidos > 0): ?>
            <div class="stat-alert">⚠ Atención</div>
            <?php endif; ?>
            <div class="stat-icon">🚨</div>
            <div class="stat-value"><?php echo $vencidos; ?></div>
            <div class="stat-label">Pedidos vencidos</div>
            <div class="stat-sub">Fecha límite superada</div>
        </a>

        <!-- Sin asignar -->
        <a href="asignaciones/index_asignaciones.php" class="stat-card lav" style="animation-delay:0.21s">
            <?php if ($sinAsignar > 0): ?>
            <div class="stat-alert">Pendiente</div>
            <?php endif; ?>
            <div class="stat-icon">🧵</div>
            <div class="stat-value"><?php echo $sinAsignar; ?></div>
            <div class="stat-label">Sin asignar</div>
            <div class="stat-sub">Necesitan tejedor</div>
        </a>

        <!-- Stock crítico -->
        <a href="catalogo/index_catalogo.php" class="stat-card coral" style="animation-delay:0.28s">
            <?php if ($stockCritico > 0): ?>
            <div class="stat-alert">Stock bajo</div>
            <?php endif; ?>
            <div class="stat-icon">🧶</div>
            <div class="stat-value"><?php echo $stockCritico; ?></div>
            <div class="stat-label">Stock crítico</div>
            <div class="stat-sub">Productos bajo mínimo</div>
        </a>

        <!-- Urgentes -->
        <a href="pedidos/index_pedidos.php" class="stat-card navy" style="animation-delay:0.35s">
            <?php if ($urgentes > 0): ?>
            <div class="stat-alert">⚠ Urgente</div>
            <?php endif; ?>
            <div class="stat-icon">🔥</div>
            <div class="stat-value"><?php echo $urgentes; ?></div>
            <div class="stat-label">Pedidos urgentes</div>
            <div class="stat-sub">Prioridad máxima</div>
        </a>

    </div>

    <!-- ── PROGRESO EQUIPO + TEJEDORES ── -->
    <div class="section-title">Equipo de producción</div>
    <div class="equipo-section">

        <!-- Progreso general -->
        <div class="panel">
            <div class="section-title" style="font-size:15px;margin-bottom:4px;">📈 Progreso general del equipo</div>
            <p style="font-size:13px;color:var(--text-soft);">Promedio de avance en pedidos activos</p>
            <div class="big-progress">
                <div class="big-prog-bar-bg">
                    <div class="big-prog-bar" style="width:<?php echo $progresoEquipo; ?>%"></div>
                </div>
            </div>
            <div class="prog-numbers">
                <span class="prog-big-pct"><?php echo $progresoEquipo; ?>%</span>
                <span class="prog-note">
                    <?php if ($progresoEquipo >= 80): ?>🎉 ¡Excelente ritmo!
                    elseif ($progresoEquipo >= 50): ?>👍 Buen avance
                    <?php elseif ($progresoEquipo >= 25): ?>⚡ En progreso
                    <?php else: ?>⏳ Recién iniciando
                    <?php endif; ?>
                </span>
            </div>

            <!-- Mini resumen por estado -->
            <div style="margin-top:20px;">
                <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-soft);margin-bottom:10px;">Estado de pedidos</div>
                <div class="estados-wrap">
                    <div class="estado-pill">
                        <span class="ep-num"><?php echo $estadoMap['pendiente'] ?? 0; ?></span>
                        <span class="ep-label">Pendiente</span>
                    </div>
                    <div class="estado-pill">
                        <span class="ep-num"><?php echo $estadoMap['en_proceso'] ?? 0; ?></span>
                        <span class="ep-label">En proceso</span>
                    </div>
                    <div class="estado-pill">
                        <span class="ep-num"><?php echo $estadoMap['completado'] ?? 0; ?></span>
                        <span class="ep-label">Completado</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tejedores -->
        <div class="panel">
            <div class="section-title" style="font-size:15px;margin-bottom:4px;">🧶 Progreso por tejedor</div>
            <p style="font-size:13px;color:var(--text-soft);margin-bottom:16px;">Avance promedio en tareas activas</p>

            <?php if (empty($tejedores)): ?>
            <div class="empty-tejedores">
                <div style="font-size:32px;margin-bottom:8px;">🧵</div>
                No hay tejedores con asignaciones activas.
            </div>
            <?php else: ?>
            <?php foreach ($tejedores as $t): ?>
            <div class="tejedor-row">
                <div class="tejedor-avatar"><?php echo strtoupper(substr($t['nombre'], 0, 1)); ?></div>
                <div class="tejedor-info">
                    <div class="tejedor-nombre"><?php echo htmlspecialchars($t['nombre']); ?></div>
                    <div class="tejedor-tareas"><?php echo $t['tareas']; ?> tarea(s) activa(s)</div>
                </div>
                <div class="tejedor-prog-wrap">
                    <div class="tejedor-prog-bg">
                        <div class="tejedor-prog-bar" style="width:<?php echo $t['promedio']; ?>%"></div>
                    </div>
                    <div class="tejedor-pct"><?php echo $t['promedio']; ?>%</div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>