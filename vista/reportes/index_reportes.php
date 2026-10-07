<?php
// vista/reportes/index_reportes.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../modelo/conexion.php';

require_once __DIR__ . '/../../modelo/autorizacion.php';
requierePermiso('reportes', 'lectura');

$conn = new Conexion();
$db   = $conn->conectar();

// Rango de meses (últimos 6)
$meses = [];
for ($i = 5; $i >= 0; $i--) {
    $meses[] = date('Y-m', strtotime("-$i months"));
}

// ── Pedidos completados por mes ──
$completadosPorMes = [];
foreach ($meses as $mes) {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM pedidos
         WHERE estado IN ('completado','entregado')
         AND TO_CHAR(fecha_pedido, 'YYYY-MM') = :mes"
    );
    $stmt->execute([':mes' => $mes]);
    $completadosPorMes[$mes] = (int)$stmt->fetchColumn();
}

// ── Pedidos retrasados/vencidos por mes ──
$retrasadosPorMes = [];
foreach ($meses as $mes) {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM pedidos
         WHERE estado NOT IN ('completado','entregado','cancelado')
         AND fecha_entrega < CURRENT_DATE
         AND TO_CHAR(fecha_pedido, 'YYYY-MM') = :mes"
    );
    $stmt->execute([':mes' => $mes]);
    $retrasadosPorMes[$mes] = (int)$stmt->fetchColumn();
}

// ── Pedidos por tipo ──
$porTipo = $db->query(
    "SELECT tipo, COUNT(*) as total FROM pedidos GROUP BY tipo"
)->fetchAll();
$tipoMap = [];
foreach ($porTipo as $row) $tipoMap[$row['tipo']] = $row['total'];
$totalTipos = array_sum(array_column($porTipo, 'total'));

// ── Productividad por tejedor ──
$productividad = $db->query(
    "SELECT u.nombre,
            COUNT(a.id) AS total_asignaciones,
            SUM(CASE WHEN p.estado IN ('completado','entregado') THEN 1 ELSE 0 END) AS completados,
            ROUND(AVG(a.progreso)) AS progreso_promedio
     FROM asignaciones a
     JOIN usuarios u ON a.empleado_id = u.id
     JOIN pedidos p ON a.pedido_id = p.id
     GROUP BY u.id, u.nombre
     ORDER BY completados DESC"
)->fetchAll();

// ── Productos más pedidos ──
$masped = $db->query(
    "SELECT c.nombre, COUNT(p.id) AS veces
     FROM pedidos p
     JOIN catalogo c ON p.catalogo_id = c.id
     WHERE p.catalogo_id IS NOT NULL
     GROUP BY c.id, c.nombre
     ORDER BY veces DESC
     LIMIT 8"
)->fetchAll();

// ── Totales generales ──
$totalPedidos    = $db->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
$totalCompletados= $db->query("SELECT COUNT(*) FROM pedidos WHERE estado IN ('completado','entregado')")->fetchColumn();
$totalVencidos   = $db->query("SELECT COUNT(*) FROM pedidos WHERE estado NOT IN ('completado','entregado','cancelado') AND fecha_entrega < CURRENT_DATE")->fetchColumn();
$totalCancelados = $db->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'cancelado'")->fetchColumn();

$nombreMeses = ['01'=>'Ene','02'=>'Feb','03'=>'Mar','04'=>'Abr','05'=>'May','06'=>'Jun',
                '07'=>'Jul','08'=>'Ago','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'];
function labelMes($ym) {
    global $nombreMeses;
    [$y, $m] = explode('-', $ym);
    return $nombreMeses[$m] . ' ' . substr($y, 2);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrochetLab — Reportes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
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

        .btn-pdf {
            display:inline-flex; align-items:center; gap:6px;
            background:var(--coral); color:white; border:none; border-radius:10px;
            padding:9px 18px; font-size:13px; font-weight:800;
            font-family:'Nunito',sans-serif; cursor:pointer; transition:background 0.2s;
            text-decoration:none;
        }
        .btn-pdf:hover { background:var(--coral-dark, #d97060); }

        .container { max-width:1150px; margin:0 auto; padding:32px 24px 60px; }

        .section-title {
            font-family:'DM Serif Display',serif; font-size:18px; color:var(--navy);
            margin:32px 0 16px; display:flex; align-items:center; gap:10px;
        }
        .section-title::after { content:''; flex:1; height:1px; background:var(--border); }
        .section-title:first-of-type { margin-top:0; }

        /* STATS RÁPIDOS */
        .quick-stats {
            display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:8px;
        }
        .qs-card {
            background:var(--white); border-radius:14px; border:1.5px solid var(--border);
            padding:18px 20px; text-align:center;
            box-shadow:0 3px 12px rgba(44,62,107,0.05);
        }
        .qs-num  { font-family:'DM Serif Display',serif; font-size:32px; color:var(--navy); }
        .qs-label{ font-size:12px; color:var(--text-soft); font-weight:700; margin-top:2px; }
        .qs-card.green .qs-num { color:#1a6b53; }
        .qs-card.red   .qs-num { color:#b94030; }
        .qs-card.yellow .qs-num { color:#9a7000; }

        /* GRID GRÁFICAS */
        .charts-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        .chart-full  { grid-column:1/-1; }

        .panel {
            background:var(--white); border-radius:16px; border:1.5px solid var(--border);
            padding:22px 24px; box-shadow:0 4px 16px rgba(44,62,107,0.05);
        }
        .panel-title {
            font-size:14px; font-weight:800; color:var(--navy); margin-bottom:16px;
            display:flex; align-items:center; gap:8px;
        }
        .chart-wrap { position:relative; height:220px; }
        .chart-wrap-tall { position:relative; height:260px; }

        /* TABLA PRODUCTIVIDAD */
        .prod-table { width:100%; border-collapse:collapse; }
        .prod-table th {
            padding:10px 14px; text-align:left; font-size:11px; font-weight:800;
            text-transform:uppercase; letter-spacing:0.07em; color:var(--text-soft);
            border-bottom:1px solid var(--border);
        }
        .prod-table td { padding:11px 14px; font-size:14px; border-bottom:1px solid var(--border); vertical-align:middle; }
        .prod-table tr:last-child td { border-bottom:none; }
        .prod-table tbody tr:hover { background:var(--sky-pale); }

        .mini-bar-bg { height:7px; background:var(--border); border-radius:4px; overflow:hidden; width:100px; }
        .mini-bar    { height:100%; border-radius:4px; background:var(--mint); }

        .badge-num {
            display:inline-flex; align-items:center; justify-content:center;
            background:rgba(44,62,107,0.08); color:var(--navy);
            border-radius:20px; font-size:12px; font-weight:800;
            padding:2px 10px; min-width:32px;
        }

        @keyframes fadeUp {
            from { opacity:0; transform:translateY(10px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .panel { animation: fadeUp 0.4s ease both; }

        /* ── PRINT / PDF ── */
        @media print {
            * { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
            body { background:white; }
            .navbar, .no-print { display:none !important; }
            .container { padding:0; max-width:100%; }
            .charts-grid { grid-template-columns:1fr 1fr; }
            .panel { break-inside:avoid; box-shadow:none; border:1px solid #ddd; }
            .quick-stats { grid-template-columns:repeat(4,1fr); }
            .section-title { margin-top:20px; }
            .print-header { display:block !important; }
        }

        .print-header {
            display:none;
            margin-bottom:24px;
            padding-bottom:16px;
            border-bottom:2px solid var(--navy);
        }
        .print-header h1 { font-family:'DM Serif Display',serif; font-size:24px; color:var(--navy); }
        .print-header p  { font-size:13px; color:var(--text-soft); margin-top:4px; }

        @media(max-width:900px) {
            .charts-grid { grid-template-columns:1fr; }
            .chart-full  { grid-column:1; }
            .quick-stats { grid-template-columns:repeat(2,1fr); }
        }
        @media(max-width:500px) {
            .quick-stats { grid-template-columns:1fr 1fr; }
            .navbar { padding:0 16px; }
            .container { padding:20px 14px 48px; }
        }
    </style>
</head>
<body>

<nav class="navbar no-print">
    <div class="navbar-left">
        <a href="../menu_principal.php" class="back-btn">← Menú</a>
        <span class="page-title">📈 Reportes</span>
    </div>
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="btn-pdf" onclick="imprimirReporte()">🖨️ Guardar PDF</button>
        <a class="navbar-brand" href="../menu_principal.php">
            <img src="../../assets/img/logo.png" alt="CrochetLab">
            <span>Crochet<em>Lab</em></span>
        </a>
    </div>
</nav>

<div class="container">

    <!-- Cabecera que solo aparece al imprimir -->
    <div class="print-header">
        <h1>CrochetLab — Reporte de Producción</h1>
        <p>Generado el <?php echo date('d/m/Y \a \l\a\s H:i'); ?> · Últimos 6 meses</p>
    </div>

    <!-- ── RESUMEN GENERAL ── -->
    <div class="section-title">Resumen general</div>
    <div class="quick-stats">
        <div class="qs-card">
            <div class="qs-num"><?php echo $totalPedidos; ?></div>
            <div class="qs-label">Total pedidos</div>
        </div>
        <div class="qs-card green">
            <div class="qs-num"><?php echo $totalCompletados; ?></div>
            <div class="qs-label">Completados / Entregados</div>
        </div>
        <div class="qs-card red">
            <div class="qs-num"><?php echo $totalVencidos; ?></div>
            <div class="qs-label">Actualmente vencidos</div>
        </div>
        <div class="qs-card yellow">
            <div class="qs-num"><?php echo $totalCancelados; ?></div>
            <div class="qs-label">Cancelados</div>
        </div>
    </div>

    <!-- ── GRÁFICAS ── -->
    <div class="section-title">Actividad mensual (últimos 6 meses)</div>
    <div class="charts-grid">

        <!-- Completados por mes — barra -->
        <div class="panel chart-full">
            <div class="panel-title">📦 Pedidos completados vs vencidos por mes</div>
            <div class="chart-wrap-tall">
                <canvas id="chartMeses"></canvas>
            </div>
        </div>

        <!-- Tipo de pedido — dona -->
        <div class="panel">
            <div class="panel-title">📊 Pedidos por tipo</div>
            <div class="chart-wrap">
                <canvas id="chartTipo"></canvas>
            </div>
        </div>

        <!-- Productos más pedidos — horizontal -->
        <div class="panel">
            <div class="panel-title">🧶 Productos más solicitados</div>
            <div class="chart-wrap">
                <canvas id="chartProductos"></canvas>
            </div>
        </div>

    </div>

    <!-- ── PRODUCTIVIDAD POR TEJEDOR ── -->
    <div class="section-title">Productividad por tejedor</div>
    <div class="panel">
        <table class="prod-table">
            <thead>
                <tr>
                    <th>Tejedor</th>
                    <th>Asignaciones</th>
                    <th>Completados</th>
                    <th>% Completado</th>
                    <th>Progreso promedio actual</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($productividad)): ?>
                <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-soft);">Sin datos de productividad aún.</td></tr>
                <?php else: foreach ($productividad as $t):
                    $pct = $t['total_asignaciones'] > 0
                        ? round(($t['completados'] / $t['total_asignaciones']) * 100) : 0;
                ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--mint),var(--sky));display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:var(--navy);flex-shrink:0;">
                                <?php echo strtoupper(substr($t['nombre'],0,1)); ?>
                            </div>
                            <span style="font-weight:700;"><?php echo htmlspecialchars($t['nombre']); ?></span>
                        </div>
                    </td>
                    <td><span class="badge-num"><?php echo $t['total_asignaciones']; ?></span></td>
                    <td><span class="badge-num" style="background:rgba(142,207,192,0.2);color:#1a6b53;"><?php echo $t['completados']; ?></span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="mini-bar-bg"><div class="mini-bar" style="width:<?php echo $pct; ?>%"></div></div>
                            <span style="font-size:12px;font-weight:800;color:var(--navy);"><?php echo $pct; ?>%</span>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="mini-bar-bg"><div class="mini-bar" style="width:<?php echo $t['progreso_promedio']; ?>%;background:var(--sky);"></div></div>
                            <span style="font-size:12px;font-weight:800;color:var(--navy);"><?php echo $t['progreso_promedio']; ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
const navy   = '#2C3E6B';
const sky    = '#7ABFCC';
const coral  = '#F2907A';
const mint   = '#8ECFC0';
const yellow = '#F7CE7A';
const lav    = '#C9B8E8';

Chart.defaults.font.family = "'Nunito', sans-serif";
Chart.defaults.color = '#6a7fa8';

// ── Completados vs Vencidos por mes ──
new Chart(document.getElementById('chartMeses'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_map('labelMes', $meses)); ?>,
        datasets: [
            {
                label: 'Completados',
                data: <?php echo json_encode(array_values($completadosPorMes)); ?>,
                backgroundColor: 'rgba(142,207,192,0.7)',
                borderColor: mint,
                borderWidth: 2,
                borderRadius: 6,
            },
            {
                label: 'Vencidos activos',
                data: <?php echo json_encode(array_values($retrasadosPorMes)); ?>,
                backgroundColor: 'rgba(242,144,122,0.7)',
                borderColor: coral,
                borderWidth: 2,
                borderRadius: 6,
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: {
            y: { beginAtZero:true, ticks:{ stepSize:1 }, grid:{ color:'rgba(218,237,242,0.8)' } },
            x: { grid:{ display:false } }
        }
    }
});

// ── Tipo de pedido ──
new Chart(document.getElementById('chartTipo'), {
    type: 'doughnut',
    data: {
        labels: ['Estándar', 'Personalizado'],
        datasets: [{
            data: [
                <?php echo $tipoMap['estandar'] ?? 0; ?>,
                <?php echo $tipoMap['personalizado'] ?? 0; ?>
            ],
            backgroundColor: [sky, lav],
            borderColor: ['white','white'],
            borderWidth: 3,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position:'bottom' },
            tooltip: {
                callbacks: {
                    label: ctx => {
                        const total = ctx.dataset.data.reduce((a,b) => a+b, 0);
                        const pct = total > 0 ? Math.round(ctx.parsed / total * 100) : 0;
                        return ` ${ctx.label}: ${ctx.parsed} (${pct}%)`;
                    }
                }
            }
        }
    }
});

// ── Productos más pedidos ──
<?php if (!empty($masped)): ?>
new Chart(document.getElementById('chartProductos'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($masped, 'nombre')); ?>,
        datasets: [{
            label: 'Veces pedido',
            data: <?php echo json_encode(array_column($masped, 'veces')); ?>,
            backgroundColor: 'rgba(122,191,204,0.7)',
            borderColor: sky,
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display:false } },
        scales: {
            x: { beginAtZero:true, ticks:{ stepSize:1 }, grid:{ color:'rgba(218,237,242,0.8)' } },
            y: { grid:{ display:false }, ticks:{ font:{ size:11 } } }
        }
    }
});
<?php else: ?>
document.getElementById('chartProductos').parentElement.innerHTML =
    '<p style="text-align:center;padding:40px;color:#6a7fa8;font-size:13px;">Sin datos de productos pedidos aún.</p>';
<?php endif; ?>

function imprimirReporte() {
    window.print();
}
</script>
</body>
</html>