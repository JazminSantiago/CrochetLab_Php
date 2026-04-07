<?php
// vista/asignaciones/index_asignaciones.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controlador/asignaciones_ctrl.php';
require_once __DIR__ . '/../../controlador/pedidos_ctrl.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../../index.php'); exit();
}

$ctrl        = new AsignacionesCtrl();
$asignaciones = $ctrl->listar();
$pedidos     = $ctrl->listarPedidosDisponibles();
$tejedores   = $ctrl->listarTejedores();

// Agrupar asignaciones por pedido para mostrar progreso promedio
$progresosPedido = [];
foreach ($asignaciones as $a) {
    $pid = $a['pedido_id'];
    if (!isset($progresosPedido[$pid])) $progresosPedido[$pid] = [];
    $progresosPedido[$pid][] = (int)$a['progreso'];
}

function promedioProgreso($arr) {
    return count($arr) ? round(array_sum($arr) / count($arr)) : 0;
}

function diasRestantes($fecha) {
    if (!$fecha) return null;
    $diff = (new DateTime('today'))->diff(new DateTime($fecha));
    return (int)$diff->format('%r%a');
}

function estadoBadge($estado) {
    $map = [
        'pendiente'  => ['Pendiente',  '#9a7000',  'rgba(247,206,122,0.2)', 'rgba(247,206,122,0.5)'],
        'en_proceso' => ['En proceso', '#1a6b7a',  'rgba(122,191,204,0.2)', 'rgba(122,191,204,0.5)'],
        'completado' => ['Completado', '#1a6b53',  'rgba(142,207,192,0.2)', 'rgba(142,207,192,0.5)'],
        'entregado'  => ['Entregado',  '#2C3E6B',  'rgba(44,62,107,0.1)',   'rgba(44,62,107,0.3)'],
        'cancelado'  => ['Cancelado',  '#b94030',  'rgba(242,144,122,0.2)', 'rgba(242,144,122,0.5)'],
    ];
    [$label,$color,$bg,$border] = $map[$estado] ?? $map['pendiente'];
    return "<span style='display:inline-flex;align-items:center;gap:4px;border-radius:20px;font-size:11px;font-weight:700;padding:4px 10px;background:{$bg};color:{$color};border:1px solid {$border}'>{$label}</span>";
}

function prioridadBadge($p) {
    $map = [
        'normal'  => ['Normal',    '#6a7fa8', 'rgba(106,127,168,0.1)'],
        'alta'    => ['Alta',      '#9a7000', 'rgba(247,206,122,0.2)'],
        'urgente' => ['⚠ Urgente', '#b94030', 'rgba(242,144,122,0.2)'],
    ];
    [$label,$color,$bg] = $map[$p] ?? $map['normal'];
    return "<span style='font-size:11px;font-weight:700;padding:3px 8px;border-radius:20px;background:{$bg};color:{$color}'>{$label}</span>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrochetLab — Asignaciones</title>
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

        .container { max-width:1200px; margin:0 auto; padding:32px 24px 60px; }

        /* MENSAJE */
        .mensaje { padding:13px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; font-weight:600; }
        .success { background:#edfaf7; color:#1a6b53; border-left:4px solid var(--mint); }
        .error   { background:#fef0ee; color:#b94030; border-left:4px solid var(--coral); }

        /* TOOLBAR */
        .toolbar {
            display:flex; align-items:center; justify-content:space-between;
            margin-bottom:20px; gap:12px; flex-wrap:wrap;
        }
        .toolbar-left { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .search-input {
            padding:10px 16px; border:1.5px solid var(--border); border-radius:10px;
            font-size:14px; font-family:'Nunito',sans-serif; background:var(--white);
            color:var(--text-main); width:220px; transition:border-color 0.2s;
        }
        .search-input:focus { outline:none; border-color:var(--sky); box-shadow:0 0 0 3px rgba(122,191,204,0.18); }
        .filter-select {
            padding:10px 14px; border:1.5px solid var(--border); border-radius:10px;
            font-size:14px; font-family:'Nunito',sans-serif; background:var(--white); cursor:pointer;
        }
        .btn-primary {
            display:inline-flex; align-items:center; gap:6px; background:var(--navy);
            color:white; border:none; border-radius:10px; padding:10px 20px;
            font-size:14px; font-weight:800; font-family:'Nunito',sans-serif;
            cursor:pointer; transition:background 0.2s, transform 0.1s;
        }
        .btn-primary:hover { background:var(--coral); transform:translateY(-1px); }

        /* TABLA */
        .table-wrap {
            background:var(--white); border-radius:16px; border:1px solid var(--border);
            overflow:hidden; box-shadow:0 4px 20px rgba(44,62,107,0.06);
        }
        table { width:100%; border-collapse:collapse; }
        thead { background:var(--sky-pale); }
        th {
            padding:13px 16px; text-align:left; font-size:11px; font-weight:800;
            text-transform:uppercase; letter-spacing:0.07em; color:var(--text-soft);
            border-bottom:1px solid var(--border); white-space:nowrap;
        }
        td { padding:13px 16px; font-size:14px; border-bottom:1px solid var(--border); vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tbody tr { transition:background 0.15s; }
        tbody tr:hover { background:var(--sky-pale); }

        /* BARRA DE PROGRESO */
        .progress-wrap { display:flex; flex-direction:column; gap:5px; min-width:120px; }
        .progress-label { display:flex; justify-content:space-between; align-items:center; }
        .progress-pct { font-size:13px; font-weight:800; color:var(--navy); }
        .progress-tejedor { font-size:11px; color:var(--text-soft); }
        .progress-bar-bg {
            height:8px; background:var(--border); border-radius:4px; overflow:hidden;
        }
        .progress-bar { height:100%; border-radius:4px; transition:width 0.5s ease; }
        .prog-0   .progress-bar { background:var(--border); }
        .prog-low .progress-bar { background:var(--coral); }
        .prog-mid .progress-bar { background:var(--yellow); }
        .prog-hi  .progress-bar { background:var(--mint); }
        .prog-done .progress-bar { background:#3ecf8e; }

        /* TEJEDORES CHIPS */
        .tejedores-list { display:flex; flex-wrap:wrap; gap:5px; }
        .tejedor-chip {
            display:inline-flex; align-items:center; gap:5px;
            background:rgba(201,184,232,0.2); border:1px solid rgba(201,184,232,0.5);
            color:#5a3e8a; border-radius:20px; font-size:11px; font-weight:700;
            padding:3px 10px;
        }
        .tejedor-chip .chip-prog { 
            background:rgba(201,184,232,0.4); border-radius:10px; 
            padding:1px 6px; font-size:10px;
        }

        /* DÍAS */
        .dias-ok       { color:#1a6b53; font-weight:800; font-size:13px; }
        .dias-warning  { color:#9a7000; font-weight:800; font-size:13px; }
        .dias-critical { color:#b94030; font-weight:800; font-size:13px; }
        .dias-vencido  { color:#b94030; font-weight:800; font-size:13px; }

        /* ACCIONES */
        .actions { display:flex; gap:6px; flex-wrap:wrap; }
        .btn-action {
            padding:6px 11px; border-radius:8px; font-size:12px; font-weight:700;
            font-family:'Nunito',sans-serif; cursor:pointer; border:1.5px solid; transition:all 0.2s;
        }
        .btn-edit   { background:var(--sky-pale); color:var(--navy); border-color:var(--border); }
        .btn-edit:hover { background:var(--sky); color:white; border-color:var(--sky); }
        .btn-delete { background:rgba(242,144,122,0.1); color:#b94030; border-color:rgba(242,144,122,0.4); }
        .btn-delete:hover { background:var(--coral); color:white; border-color:var(--coral); }

        .empty-state { text-align:center; padding:60px 20px; color:var(--text-soft); }
        .empty-state .icon { font-size:48px; margin-bottom:12px; }

        /* MODAL */
        .modal-overlay {
            display:none; position:fixed; inset:0; background:rgba(44,62,107,0.45);
            z-index:500; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(3px);
        }
        .modal-overlay.active { display:flex; }
        .modal {
            background:var(--white); border-radius:20px; width:100%; max-width:540px;
            max-height:92vh; overflow-y:auto;
            box-shadow:0 20px 60px rgba(44,62,107,0.2);
            animation:modalIn 0.3s cubic-bezier(0.22,1,0.36,1) both;
        }
        @keyframes modalIn {
            from { opacity:0; transform:translateY(20px) scale(0.97); }
            to   { opacity:1; transform:translateY(0) scale(1); }
        }
        .modal-header {
            padding:22px 28px 16px; border-bottom:1px solid var(--border);
            display:flex; align-items:center; justify-content:space-between;
        }
        .modal-title { font-family:'DM Serif Display',serif; font-size:20px; color:var(--navy); }
        .modal-close {
            background:none; border:none; font-size:22px; cursor:pointer; color:var(--text-soft);
            width:32px; height:32px; display:flex; align-items:center; justify-content:center;
            border-radius:8px; transition:background 0.2s;
        }
        .modal-close:hover { background:var(--sky-pale); }
        .modal-body { padding:22px 28px; }
        .modal-footer {
            padding:16px 28px 22px; display:flex; justify-content:flex-end;
            gap:10px; border-top:1px solid var(--border);
        }
        .form-group { margin-bottom:16px; }
        label {
            display:block; margin-bottom:6px; font-size:11px; font-weight:800;
            letter-spacing:0.07em; text-transform:uppercase; color:var(--navy);
        }
        .required { color:var(--coral); margin-left:2px; }
        input[type=text], input[type=number], select, textarea {
            width:100%; padding:11px 14px; border:1.5px solid var(--border); border-radius:10px;
            font-size:14px; font-family:'Nunito',sans-serif; background:var(--sky-pale);
            color:var(--text-main); transition:border-color 0.2s, box-shadow 0.2s;
        }
        input:focus, select:focus, textarea:focus {
            outline:none; border-color:var(--sky); background:white;
            box-shadow:0 0 0 3px rgba(122,191,204,0.18);
        }
        textarea { resize:vertical; min-height:70px; }
        .section-divider {
            font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.07em;
            color:var(--text-soft); margin:8px 0 16px; display:flex; align-items:center; gap:8px;
        }
        .section-divider::after { content:''; flex:1; height:1px; background:var(--border); }
        .btn-cancel {
            padding:10px 20px; border-radius:10px; font-size:14px; font-weight:700;
            font-family:'Nunito',sans-serif; cursor:pointer; background:var(--sky-pale);
            color:var(--text-soft); border:1.5px solid var(--border); transition:all 0.2s;
        }
        .btn-cancel:hover { background:var(--border); color:var(--navy); }

        /* Slider de progreso */
        .slider-wrap { display:flex; align-items:center; gap:14px; }
        .slider-wrap input[type=range] {
            flex:1; padding:0; height:6px; border:none; background:transparent;
            accent-color:var(--navy);
        }
        .slider-value {
            min-width:42px; text-align:center; font-weight:800; font-size:15px;
            color:var(--navy); background:var(--sky-pale); border:1.5px solid var(--border);
            border-radius:8px; padding:4px 8px;
        }

        /* Info pedido en modal */
        .pedido-info-box {
            background:var(--sky-pale); border:1px solid var(--border); border-radius:10px;
            padding:12px 16px; margin-bottom:16px; font-size:13px; display:none;
        }
        .pedido-info-box strong { color:var(--navy); }

        @media(max-width:768px) {
            .navbar { padding:0 16px; }
            .container { padding:20px 16px 48px; }
            td, th { padding:10px 12px; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="navbar-left">
        <a href="../menu_principal.php" class="back-btn">← Menú</a>
        <span class="page-title">🧵 Asignaciones</span>
    </div>
    <a class="navbar-brand" href="../menu_principal.php">
        <img src="../../assets/img/logo.png" alt="CrochetLab">
        <span>Crochet<em>Lab</em></span>
    </a>
</nav>

<div class="container">

    <?php if (isset($_SESSION['mensaje'])): ?>
        <div class="mensaje <?php echo $_SESSION['tipo_mensaje']; ?>">
            <?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); ?>
        </div>
    <?php endif; ?>

    <div class="toolbar">
        <div class="toolbar-left">
            <input type="text" class="search-input" placeholder="🔍 Buscar pedido o tejedor..."
                   oninput="filtrarTabla(this.value)">
            <select class="filter-select" onchange="filtrarEstado(this.value)">
                <option value="">Todos los estados</option>
                <option value="pendiente">Pendiente</option>
                <option value="en_proceso">En proceso</option>
                <option value="completado">Completado</option>
                <option value="entregado">Entregado</option>
            </select>
        </div>
        <button class="btn-primary" onclick="abrirModalCrear()">+ Nueva Asignación</button>
    </div>

    <div class="table-wrap">
        <table id="tablaAsignaciones">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Pedido</th>
                    <th>Tejedor</th>
                    <th>Progreso</th>
                    <th>Fecha límite</th>
                    <th>Días restantes</th>
                    <th>Prioridad</th>
                    <th>Estado pedido</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($asignaciones)): ?>
                <tr><td colspan="9">
                    <div class="empty-state">
                        <div class="icon">🧵</div>
                        <p>No hay asignaciones todavía.<br>Crea una para vincular pedidos con tejedores.</p>
                    </div>
                </td></tr>
                <?php else: foreach ($asignaciones as $a):
                    $prog = (int)$a['progreso'];
                    $progClass = $prog === 100 ? 'prog-done' : ($prog >= 70 ? 'prog-hi' : ($prog >= 40 ? 'prog-mid' : ($prog > 0 ? 'prog-low' : 'prog-0')));
                    $dias = diasRestantes($a['fecha_entrega']);
                    $diasClass = $dias === null ? '' : ($dias > 7 ? 'dias-ok' : ($dias >= 1 ? 'dias-warning' : ($dias === 0 ? 'dias-critical' : 'dias-vencido')));
                    // Promedio del pedido completo
                    $progsDelPedido = $progresosPedido[$a['pedido_id']] ?? [$prog];
                    $promedio = promedioProgreso($progsDelPedido);
                    $hayVarios = count($progsDelPedido) > 1;
                ?>
                <tr data-estado="<?php echo $a['pedido_estado']; ?>">
                    <td style="color:var(--text-soft);font-size:12px;">#<?php echo $a['id']; ?></td>
                    <td>
                        <div style="font-weight:700;"><?php echo htmlspecialchars($a['pedido_descripcion']); ?></div>
                        <div style="font-size:11px;color:var(--text-soft);margin-top:2px;">
                            <?php echo $a['pedido_tipo'] === 'estandar' ? '📦 Estándar' : '✨ Personalizado'; ?>
                            · Pedido #<?php echo $a['pedido_id']; ?>
                        </div>
                        <?php if ($hayVarios): ?>
                        <div style="font-size:11px;color:var(--text-soft);margin-top:3px;">
                            Promedio pedido: <strong><?php echo $promedio; ?>%</strong>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="tejedor-chip">
                            🧶 <?php echo htmlspecialchars($a['empleado_nombre']); ?>
                            <span class="chip-prog"><?php echo $prog; ?>%</span>
                        </div>
                    </td>
                    <td>
                        <div class="progress-wrap <?php echo $progClass; ?>">
                            <div class="progress-label">
                                <span class="progress-pct"><?php echo $prog; ?>%</span>
                                <?php if ($prog === 100): ?><span style="font-size:11px;color:#1a6b53;">✓ Listo</span><?php endif; ?>
                            </div>
                            <div class="progress-bar-bg">
                                <div class="progress-bar" style="width:<?php echo $prog; ?>%"></div>
                            </div>
                        </div>
                    </td>
                    <td><?php echo $a['fecha_entrega'] ? date('d/m/Y', strtotime($a['fecha_entrega'])) : '—'; ?></td>
                    <td>
                        <?php if ($dias !== null): ?>
                            <span class="<?php echo $diasClass; ?>">
                                <?php if ($dias < 0) echo abs($dias) . ' día(s) vencido';
                                elseif ($dias === 0) echo '¡Hoy!';
                                else echo $dias . ' día(s)'; ?>
                            </span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><?php echo prioridadBadge($a['prioridad']); ?></td>
                    <td><?php echo estadoBadge($a['pedido_estado']); ?></td>
                    <td>
                        <div class="actions">
                            <button class="btn-action btn-edit"
                                onclick="abrirModalEditar(<?php echo htmlspecialchars(json_encode($a)); ?>)">✏️</button>
                            <form action="../../controlador/asignaciones_ctrl.php" method="POST" style="margin:0">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="asignacion_id" value="<?php echo $a['id']; ?>">
                                <button type="submit" class="btn-action btn-delete"
                                    onclick="return confirm('¿Eliminar esta asignación?')">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL CREAR -->
<div class="modal-overlay" id="modalCrear">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Nueva Asignación</span>
            <button class="modal-close" onclick="cerrarModal('modalCrear')">✕</button>
        </div>
        <form action="../../controlador/asignaciones_ctrl.php" method="POST">
            <input type="hidden" name="accion" value="crear">
            <div class="modal-body">
                <div class="section-divider">Pedido</div>
                <div class="form-group">
                    <label>Pedido a asignar <span class="required">*</span></label>
                    <select name="pedido_id" id="c_pedido" onchange="mostrarInfoPedido(this)">
                        <option value="">Selecciona un pedido...</option>
                        <?php foreach ($pedidos as $p): ?>
                        <option value="<?php echo $p['id']; ?>"
                            data-tipo="<?php echo $p['tipo']; ?>"
                            data-fecha="<?php echo $p['fecha_entrega']; ?>"
                            data-prioridad="<?php echo $p['prioridad']; ?>">
                            #<?php echo $p['id']; ?> — <?php echo htmlspecialchars($p['nombre_display']); ?>
                            (<?php echo $p['tipo'] === 'estandar' ? '📦' : '✨'; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pedido-info-box" id="pedidoInfoBox">
                        <strong id="infoPedidoNombre"></strong><br>
                        <span id="infoPedidoFecha" style="color:var(--text-soft);font-size:12px;"></span>
                    </div>
                </div>

                <div class="section-divider">Tejedor</div>
                <div class="form-group">
                    <label>Tejedor asignado <span class="required">*</span></label>
                    <select name="empleado_id">
                        <option value="">Selecciona un tejedor...</option>
                        <?php foreach ($tejedores as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="section-divider">Detalles</div>
                <div class="form-group">
                    <label>Progreso inicial (%)</label>
                    <div class="slider-wrap">
                        <input type="range" name="progreso" id="c_prog" min="0" max="100" value="0"
                               oninput="document.getElementById('c_prog_val').textContent = this.value + '%'">
                        <span class="slider-value" id="c_prog_val">0%</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Notas para el tejedor</label>
                    <textarea name="notas" placeholder="Instrucciones especiales, colores, referencias..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="cerrarModal('modalCrear')">Cancelar</button>
                <button type="submit" class="btn-primary">Crear asignación</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR -->
<div class="modal-overlay" id="modalEditar">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Editar Asignación</span>
            <button class="modal-close" onclick="cerrarModal('modalEditar')">✕</button>
        </div>
        <form action="../../controlador/asignaciones_ctrl.php" method="POST">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="asignacion_id" id="e_id">
            <div class="modal-body">
                <div id="e_info_box" class="pedido-info-box" style="display:block;">
                    <strong id="e_pedido_nombre"></strong><br>
                    <span id="e_tejedor_nombre" style="color:var(--text-soft);font-size:12px;"></span>
                </div>

                <div class="section-divider">Progreso</div>
                <div class="form-group">
                    <label>Progreso (%)</label>
                    <div class="slider-wrap">
                        <input type="range" name="progreso" id="e_prog" min="0" max="100" value="0"
                               oninput="document.getElementById('e_prog_val').textContent = this.value + '%'">
                        <span class="slider-value" id="e_prog_val">0%</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Notas</label>
                    <textarea name="notas" id="e_notas" placeholder="Notas o instrucciones..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="cerrarModal('modalEditar')">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
    function cerrarModal(id) { document.getElementById(id).classList.remove('active'); }
    document.querySelectorAll('.modal-overlay').forEach(el => {
        el.addEventListener('click', e => { if (e.target === el) el.classList.remove('active'); });
    });

    function abrirModalCrear() {
        document.getElementById('c_pedido').value = '';
        document.getElementById('c_prog').value = 0;
        document.getElementById('c_prog_val').textContent = '0%';
        document.getElementById('pedidoInfoBox').style.display = 'none';
        document.getElementById('modalCrear').classList.add('active');
    }

    function abrirModalEditar(a) {
        document.getElementById('e_id').value       = a.id;
        document.getElementById('e_prog').value     = a.progreso;
        document.getElementById('e_prog_val').textContent = a.progreso + '%';
        document.getElementById('e_notas').value    = a.notas || '';
        document.getElementById('e_pedido_nombre').textContent = 'Pedido #' + a.pedido_id + ' — ' + a.pedido_descripcion;
        document.getElementById('e_tejedor_nombre').textContent = '🧶 Tejedor: ' + a.empleado_nombre;
        document.getElementById('modalEditar').classList.add('active');
    }

    function mostrarInfoPedido(sel) {
        const opt = sel.options[sel.selectedIndex];
        const box = document.getElementById('pedidoInfoBox');
        if (!sel.value) { box.style.display = 'none'; return; }
        const fecha = opt.dataset.fecha ? new Date(opt.dataset.fecha).toLocaleDateString('es-MX') : 'Sin fecha';
        document.getElementById('infoPedidoNombre').textContent = opt.textContent.trim();
        document.getElementById('infoPedidoFecha').textContent  = '📅 Entrega: ' + fecha + '  ·  Prioridad: ' + opt.dataset.prioridad;
        box.style.display = 'block';
    }

    function filtrarTabla(texto) {
        document.querySelectorAll('#tablaAsignaciones tbody tr[data-estado]').forEach(f => {
            f.style.display = f.textContent.toLowerCase().includes(texto.toLowerCase()) ? '' : 'none';
        });
    }

    function filtrarEstado(estado) {
        document.querySelectorAll('#tablaAsignaciones tbody tr[data-estado]').forEach(f => {
            f.style.display = !estado || f.dataset.estado === estado ? '' : 'none';
        });
    }
</script>
</body>
</html>