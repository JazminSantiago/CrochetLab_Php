<?php
// vista/pedidos/index_pedidos.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controlador/pedidos_ctrl.php';
require_once __DIR__ . '/../../controlador/catalogo_ctrl.php';

require_once __DIR__ . '/../../modelo/autorizacion.php';
requierePermiso('pedidos', 'lectura');

$ctrl      = new PedidosCtrl();
$catCtrl   = new CatalogoCtrl();
$estandar  = $ctrl->listar('estandar');
$personal  = $ctrl->listar('personalizado');
$productos = $catCtrl->listar();

$tab = $_GET['tab'] ?? 'estandar';

$estados   = ['pendiente','en_proceso','completado','entregado','cancelado'];
$prioridades = ['normal','alta','urgente'];

function estadoBadge($estado) {
    $map = [
        'pendiente'   => ['label' => 'Pendiente',   'color' => '#9a7000',  'bg' => 'rgba(247,206,122,0.2)', 'border' => 'rgba(247,206,122,0.5)'],
        'en_proceso'  => ['label' => 'En proceso',  'color' => '#1a6b7a',  'bg' => 'rgba(122,191,204,0.2)', 'border' => 'rgba(122,191,204,0.5)'],
        'completado'  => ['label' => 'Completado',  'color' => '#1a6b53',  'bg' => 'rgba(142,207,192,0.2)', 'border' => 'rgba(142,207,192,0.5)'],
        'entregado'   => ['label' => 'Entregado',   'color' => '#2C3E6B',  'bg' => 'rgba(44,62,107,0.1)',   'border' => 'rgba(44,62,107,0.3)'],
        'cancelado'   => ['label' => 'Cancelado',   'color' => '#b94030',  'bg' => 'rgba(242,144,122,0.2)', 'border' => 'rgba(242,144,122,0.5)'],
    ];
    $s = $map[$estado] ?? $map['pendiente'];
    return "<span style='display:inline-flex;align-items:center;gap:4px;border-radius:20px;font-size:11px;font-weight:700;padding:4px 10px;background:{$s['bg']};color:{$s['color']};border:1px solid {$s['border']}'>{$s['label']}</span>";
}

function prioridadBadge($p) {
    $map = [
        'normal'  => ['label' => 'Normal',  'color' => '#6a7fa8', 'bg' => 'rgba(106,127,168,0.1)'],
        'alta'    => ['label' => 'Alta',    'color' => '#9a7000', 'bg' => 'rgba(247,206,122,0.2)'],
        'urgente' => ['label' => '⚠ Urgente','color' => '#b94030','bg' => 'rgba(242,144,122,0.2)'],
    ];
    $s = $map[$p] ?? $map['normal'];
    return "<span style='font-size:11px;font-weight:700;padding:3px 8px;border-radius:20px;background:{$s['bg']};color:{$s['color']}'>{$s['label']}</span>";
}

function diasRestantes($fecha) {
    if (!$fecha) return null;
    $hoy  = new DateTime('today');
    $fin  = new DateTime($fecha);
    $diff = $hoy->diff($fin);
    $dias = (int)$diff->format('%r%a');
    return $dias;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrochetLab — Pedidos</title>
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

        /* TABS */
        .tabs-wrap {
            display:flex; gap:4px; margin-bottom:28px;
            background:var(--white); border:1px solid var(--border);
            border-radius:12px; padding:6px; width:fit-content;
        }
        .tab-btn {
            padding:10px 24px; border-radius:8px; border:none;
            font-size:14px; font-weight:700; font-family:'Nunito',sans-serif;
            cursor:pointer; transition:all 0.2s; color:var(--text-soft); background:transparent;
        }
        .tab-btn.active { background:var(--navy); color:white; }
        .tab-btn:not(.active):hover { background:var(--sky-pale); color:var(--navy); }

        .tab-panel { display:none; }
        .tab-panel.active { display:block; }

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

        /* MENSAJE */
        .mensaje { padding:13px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; font-weight:600; }
        .success { background:#edfaf7; color:#1a6b53; border-left:4px solid var(--mint); }
        .error   { background:#fef0ee; color:#b94030; border-left:4px solid var(--coral); }

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

        /* Días restantes */
        .dias-ok       { color:#1a6b53; font-weight:800; font-size:13px; }
        .dias-warning  { color:#9a7000; font-weight:800; font-size:13px; }
        .dias-critical { color:#b94030; font-weight:800; font-size:13px; }
        .dias-vencido  { color:#b94030; font-weight:800; font-size:13px; }

        /* Acciones */
        .actions { display:flex; gap:6px; flex-wrap:wrap; }
        .btn-action {
            padding:6px 11px; border-radius:8px; font-size:12px; font-weight:700;
            font-family:'Nunito',sans-serif; cursor:pointer; border:1.5px solid; transition:all 0.2s;
        }
        .btn-edit { background:var(--sky-pale); color:var(--navy); border-color:var(--border); }
        .btn-edit:hover { background:var(--sky); color:white; border-color:var(--sky); }
        .btn-estado { background:rgba(201,184,232,0.15); color:#5a3e8a; border-color:rgba(201,184,232,0.4); }
        .btn-estado:hover { background:var(--lavender); color:var(--navy); border-color:var(--lavender); }

        .empty-state { text-align:center; padding:60px 20px; color:var(--text-soft); }
        .empty-state .icon { font-size:48px; margin-bottom:12px; }

        /* MODAL */
        .modal-overlay {
            display:none; position:fixed; inset:0; background:rgba(44,62,107,0.45);
            z-index:500; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(3px);
        }
        .modal-overlay.active { display:flex; }
        .modal {
            background:var(--white); border-radius:20px; width:100%; max-width:600px;
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
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .form-group { margin-bottom:16px; }
        label {
            display:block; margin-bottom:6px; font-size:11px; font-weight:800;
            letter-spacing:0.07em; text-transform:uppercase; color:var(--navy);
        }
        .required { color:var(--coral); margin-left:2px; }
        input, select, textarea {
            width:100%; padding:11px 14px; border:1.5px solid var(--border); border-radius:10px;
            font-size:14px; font-family:'Nunito',sans-serif; background:var(--sky-pale);
            color:var(--text-main); transition:border-color 0.2s, box-shadow 0.2s;
        }
        input:focus, select:focus, textarea:focus {
            outline:none; border-color:var(--sky); background:white;
            box-shadow:0 0 0 3px rgba(122,191,204,0.18);
        }
        textarea { resize:vertical; min-height:80px; }
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

        /* Domicilio toggle */
        .toggle-wrap {
            display:flex; align-items:center; gap:10px;
            padding:12px 14px; border:1.5px solid var(--border);
            border-radius:10px; background:var(--sky-pale); cursor:pointer;
        }
        .toggle-wrap input[type=checkbox] { width:18px; height:18px; cursor:pointer; accent-color:var(--navy); }
        .domicilio-fields { display:none; }
        .domicilio-fields.visible { display:block; }

        /* Imagen preview */
        .img-preview { max-width:100%; max-height:140px; border-radius:10px; border:1.5px solid var(--border); object-fit:cover; margin-top:10px; display:none; }

        @media(max-width:768px) {
            .navbar { padding:0 16px; }
            .container { padding:20px 16px 48px; }
            .form-row { grid-template-columns:1fr; }
            td, th { padding:10px 12px; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-left">
        <a href="../menu_principal.php" class="back-btn">← Menú</a>
        <span class="page-title">📦 Pedidos</span>
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

    <!-- Pestañas -->
    <div class="tabs-wrap">
        <button class="tab-btn <?php echo $tab === 'estandar' ? 'active' : ''; ?>"
            onclick="cambiarTab('estandar', this)">
            📦 Estándar (<?php echo count($estandar); ?>)
        </button>
        <button class="tab-btn <?php echo $tab === 'personalizado' ? 'active' : ''; ?>"
            onclick="cambiarTab('personalizado', this)">
            ✨ Personalizados (<?php echo count($personal); ?>)
        </button>
    </div>

    <!-- ── PANEL ESTÁNDAR ── -->
    <div id="panel-estandar" class="tab-panel <?php echo $tab === 'estandar' ? 'active' : ''; ?>">
        <div class="toolbar">
            <div class="toolbar-left">
                <input type="text" class="search-input" placeholder="🔍 Buscar..." oninput="filtrarTabla('tablaEstandar', this.value)">
                <select class="filter-select" onchange="filtrarEstado('tablaEstandar', this.value)">
                    <option value="">Todos los estados</option>
                    <?php foreach ($estados as $e): ?>
                    <option value="<?php echo $e; ?>"><?php echo ucfirst(str_replace('_',' ',$e)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn-primary" onclick="abrirModal('estandar')">+ Nuevo Pedido Estándar</button>
        </div>

        <div class="table-wrap">
            <table id="tablaEstandar">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Producto</th>
                        <th>Descripción</th>
                        <th>Prioridad</th>
                        <th>Fecha entrega</th>
                        <th>Días restantes</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($estandar)): ?>
                    <tr><td colspan="8"><div class="empty-state"><div class="icon">📦</div><p>No hay pedidos estándar aún.</p></div></td></tr>
                    <?php else: foreach ($estandar as $p):
                        $dias = diasRestantes($p['fecha_entrega']);
                        if ($dias === null) $diasClass = '';
                        elseif ($dias > 7)  $diasClass = 'dias-ok';
                        elseif ($dias >= 1) $diasClass = 'dias-warning';
                        elseif ($dias === 0) $diasClass = 'dias-critical';
                        else               $diasClass = 'dias-vencido';
                    ?>
                    <tr data-estado="<?php echo $p['estado']; ?>">
                        <td style="color:var(--text-soft);font-size:12px;">#<?php echo $p['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($p['producto_nombre'] ?? '—'); ?></strong></td>
                        <td style="color:var(--text-soft);font-size:13px;"><?php echo htmlspecialchars(substr($p['descripcion'] ?? '—', 0, 50)); ?></td>
                        <td><?php echo prioridadBadge($p['prioridad']); ?></td>
                        <td><?php echo $p['fecha_entrega'] ? date('d/m/Y', strtotime($p['fecha_entrega'])) : '—'; ?></td>
                        <td>
                            <?php if ($dias !== null): ?>
                                <span class="<?php echo $diasClass; ?>">
                                    <?php if ($dias < 0) echo abs($dias) . ' día(s) vencido';
                                    elseif ($dias === 0) echo '¡Hoy!';
                                    else echo $dias . ' día(s)'; ?>
                                </span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?php echo estadoBadge($p['estado']); ?></td>
                        <td>
                            <div class="actions">
                                <button class="btn-action btn-edit"
                                    onclick="abrirModalEditar(<?php echo htmlspecialchars(json_encode($p)); ?>)">✏️</button>
                                <button class="btn-action btn-estado"
                                    onclick="abrirModalEstado(<?php echo $p['id']; ?>, '<?php echo $p['estado']; ?>')">🔄 Estado</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── PANEL PERSONALIZADO ── -->
    <div id="panel-personalizado" class="tab-panel <?php echo $tab === 'personalizado' ? 'active' : ''; ?>">
        <div class="toolbar">
            <div class="toolbar-left">
                <input type="text" class="search-input" placeholder="🔍 Buscar cliente..." oninput="filtrarTabla('tablaPersonal', this.value)">
                <select class="filter-select" onchange="filtrarEstado('tablaPersonal', this.value)">
                    <option value="">Todos los estados</option>
                    <?php foreach ($estados as $e): ?>
                    <option value="<?php echo $e; ?>"><?php echo ucfirst(str_replace('_',' ',$e)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn-primary" onclick="abrirModal('personalizado')">+ Nuevo Pedido Personalizado</button>
        </div>

        <div class="table-wrap">
            <table id="tablaPersonal">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cliente</th>
                        <th>Descripción</th>
                        <th>Prioridad</th>
                        <th>Entrega</th>
                        <th>Días restantes</th>
                        <th>Domicilio</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($personal)): ?>
                    <tr><td colspan="9"><div class="empty-state"><div class="icon">✨</div><p>No hay pedidos personalizados aún.</p></div></td></tr>
                    <?php else: foreach ($personal as $p):
                        $dias = diasRestantes($p['fecha_entrega']);
                        if ($dias === null) $diasClass = '';
                        elseif ($dias > 7)  $diasClass = 'dias-ok';
                        elseif ($dias >= 1) $diasClass = 'dias-warning';
                        elseif ($dias === 0) $diasClass = 'dias-critical';
                        else               $diasClass = 'dias-vencido';
                    ?>
                    <tr data-estado="<?php echo $p['estado']; ?>">
                        <td style="color:var(--text-soft);font-size:12px;">#<?php echo $p['id']; ?></td>
                        <td>
                            <div style="font-weight:700;"><?php echo htmlspecialchars($p['cliente_nombre'] ?? '—'); ?></div>
                            <?php if ($p['cliente_contacto']): ?>
                            <div style="font-size:12px;color:var(--text-soft);">📱 <?php echo htmlspecialchars($p['cliente_contacto']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:13px;color:var(--text-soft);"><?php echo htmlspecialchars(substr($p['descripcion'] ?? '—', 0, 50)); ?></td>
                        <td><?php echo prioridadBadge($p['prioridad']); ?></td>
                        <td><?php echo $p['fecha_entrega'] ? date('d/m/Y', strtotime($p['fecha_entrega'])) : '—'; ?></td>
                        <td>
                            <?php if ($dias !== null): ?>
                                <span class="<?php echo $diasClass; ?>">
                                    <?php if ($dias < 0) echo abs($dias) . ' día(s) vencido';
                                    elseif ($dias === 0) echo '¡Hoy!';
                                    else echo $dias . ' día(s)'; ?>
                                </span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['entrega_domicilio']): ?>
                                <span style="color:#1a6b53;font-weight:700;font-size:12px;">🏠 Sí<?php if ($p['costo_envio'] > 0): ?> <br><small>+$<?php echo number_format($p['costo_envio'],2); ?></small><?php endif; ?></span>
                            <?php else: ?>
                                <span style="color:var(--text-soft);font-size:12px;">🏪 Recoger</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo estadoBadge($p['estado']); ?></td>
                        <td>
                            <div class="actions">
                                <button class="btn-action btn-edit"
                                    onclick="abrirModalEditar(<?php echo htmlspecialchars(json_encode($p)); ?>)">✏️</button>
                                <button class="btn-action btn-estado"
                                    onclick="abrirModalEstado(<?php echo $p['id']; ?>, '<?php echo $p['estado']; ?>')">🔄 Estado</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL CREAR/EDITAR -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalTitle">Nuevo Pedido</span>
            <button class="modal-close" onclick="cerrarModal('modalOverlay')">✕</button>
        </div>
        <form action="../../controlador/pedidos_ctrl.php" method="POST" enctype="multipart/form-data">
<?php echo campoCsrf(); ?>
            <input type="hidden" name="accion" id="formAccion" value="crear_estandar">
            <input type="hidden" name="pedido_id" id="formPedidoId" value="">

            <div class="modal-body">
                <!-- Campos estándar -->
                <div id="camposEstandar">
                    <div class="section-divider">Producto</div>
                    <div class="form-group">
                        <label>Producto del catálogo <span class="required">*</span></label>
                        <select name="catalogo_id_estandar" id="f_catalogo_e">
                            <option value="">Selecciona un producto...</option>
                            <?php foreach ($productos as $prod): 
                                $estaActivo = ($prod['activo'] === true || $prod['activo'] === 't' || $prod['activo'] === 'true');
                                if (!$estaActivo) continue; 
                            ?>
                            <option value="<?php echo $prod['id']; ?>"><?php echo htmlspecialchars($prod['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Campos personalizados -->
                <div id="camposPersonal" style="display:none;">
                    <div class="section-divider">Datos del cliente</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre del cliente <span class="required">*</span></label>
                            <input type="text" name="cliente_nombre" id="f_cliente_nombre" placeholder="Nombre completo">
                        </div>
                        <div class="form-group">
                            <label>Teléfono / WhatsApp</label>
                            <input type="text" name="cliente_contacto" id="f_cliente_contacto" placeholder="442 000 0000">
                        </div>
                    </div>

                    <div class="section-divider">Imagen de referencia</div>
                    <div class="form-group">
                        <label>Foto de referencia del cliente</label>
                        <input type="file" name="imagen_referencia" id="f_img_ref" accept="image/jpeg,image/png,image/webp">
                        <img id="imgRefPreview" class="img-preview" src="" alt="Vista previa">
                        <div id="imgRefActualWrap" style="display:none;margin-top:10px;">
                            <p style="font-size:11px;color:var(--text-soft);margin-bottom:6px;">Imagen actual:</p>
                            <img id="imgRefActual" class="img-preview" style="display:block;" src="" alt="Actual">
                        </div>
                    </div>

                    <div class="section-divider">Entrega</div>
                    <div class="form-group">
                        <label class="toggle-wrap">
                            <input type="checkbox" name="entrega_domicilio" id="f_domicilio" onchange="toggleDomicilio()">
                            <span>¿Entrega a domicilio?</span>
                        </label>
                    </div>
                    <div class="domicilio-fields" id="domicilioFields">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Dirección de entrega</label>
                                <input type="text" name="direccion_entrega" id="f_direccion" placeholder="Calle, colonia, ciudad...">
                            </div>
                            <div class="form-group">
                                <label>Costo de envío (MXN)</label>
                                <input type="number" name="costo_envio" id="f_costo_envio" min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <div class="section-divider">Referencia de catálogo (opcional)</div>
                    <div class="form-group">
                        <label>Basado en producto existente</label>
                        <select name="catalogo_id" id="f_catalogo_p">
                            <option value="">Ninguno (completamente personalizado)</option>
                            <?php foreach ($productos as $prod): 
                                $estaActivo = ($prod['activo'] === true || $prod['activo'] === 't' || $prod['activo'] === 'true');
                                if (!$estaActivo) continue; 
                            ?>
                            <option value="<?php echo $prod['id']; ?>"><?php echo htmlspecialchars($prod['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Campos comunes -->
                <div class="section-divider">Detalles del pedido</div>
                <div class="form-group">
                    <label>Descripción / Especificaciones</label>
                    <textarea name="descripcion" id="f_descripcion" placeholder="Describe los detalles, colores, medidas, etc."></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha de entrega <span class="required">*</span></label>
                        <input type="date" name="fecha_entrega" id="f_fecha_entrega">
                    </div>
                    <div class="form-group">
                        <label>Prioridad</label>
                        <select name="prioridad" id="f_prioridad">
                            <option value="normal">Normal</option>
                            <option value="alta">Alta</option>
                            <option value="urgente">Urgente</option>
                        </select>
                    </div>
                </div>
                <div id="estadoField" style="display:none;">
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado" id="f_estado">
                            <?php foreach ($estados as $e): ?>
                            <option value="<?php echo $e; ?>"><?php echo ucfirst(str_replace('_',' ',$e)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="cerrarModal('modalOverlay')">Cancelar</button>
                <button type="submit" class="btn-primary" id="btnSubmit">Crear pedido</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL CAMBIAR ESTADO -->
<div class="modal-overlay" id="modalEstado">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <span class="modal-title">Cambiar Estado</span>
            <button class="modal-close" onclick="cerrarModal('modalEstado')">✕</button>
        </div>
        <form action="../../controlador/pedidos_ctrl.php" method="POST">
<?php echo campoCsrf(); ?>
            <input type="hidden" name="accion" value="cambiar_estado">
            <input type="hidden" name="pedido_id" id="estadoPedidoId">
            <div class="modal-body">
                <div class="form-group">
                    <label>Nuevo estado <span class="required">*</span></label>
                    <select name="estado" id="estadoSelect">
                        <?php foreach ($estados as $e): ?>
                        <option value="<?php echo $e; ?>"><?php echo ucfirst(str_replace('_',' ',$e)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="cerrarModal('modalEstado')">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
    // ── Tabs ──
    function cambiarTab(tab, btn) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('panel-' + tab).classList.add('active');
    }

    // ── Modales ──
    function cerrarModal(id) { document.getElementById(id).classList.remove('active'); }

    document.querySelectorAll('.modal-overlay').forEach(el => {
        el.addEventListener('click', e => { if (e.target === el) el.classList.remove('active'); });
    });

    let tipoActual = 'estandar';

    function abrirModal(tipo) {
        tipoActual = tipo;
        document.getElementById('modalTitle').textContent = tipo === 'estandar' ? 'Nuevo Pedido Estándar' : 'Nuevo Pedido Personalizado';
        document.getElementById('formAccion').value = tipo === 'estandar' ? 'crear_estandar' : 'crear_personalizado';
        document.getElementById('formPedidoId').value = '';
        document.getElementById('btnSubmit').textContent = 'Crear pedido';
        document.getElementById('estadoField').style.display = 'none';

        document.getElementById('camposEstandar').style.display  = tipo === 'estandar'      ? 'block' : 'none';
        document.getElementById('camposPersonal').style.display  = tipo === 'personalizado'  ? 'block' : 'none';

        // Limpiar
        ['f_catalogo_e','f_catalogo_p','f_cliente_nombre','f_cliente_contacto',
         'f_descripcion','f_fecha_entrega','f_direccion','f_costo_envio']
            .forEach(id => { const el = document.getElementById(id); if(el) el.value = ''; });
        document.getElementById('f_prioridad').value = 'normal';
        document.getElementById('f_domicilio').checked = false;
        document.getElementById('domicilioFields').classList.remove('visible');
        document.getElementById('imgRefPreview').style.display = 'none';
        document.getElementById('imgRefActualWrap').style.display = 'none';

        document.getElementById('modalOverlay').classList.add('active');
    }

    function abrirModalEditar(p) {
        tipoActual = p.tipo;
        document.getElementById('modalTitle').textContent = p.tipo === 'estandar' ? 'Editar Pedido Estándar' : 'Editar Pedido Personalizado';
        document.getElementById('formAccion').value = 'editar';
        document.getElementById('formPedidoId').value = p.id;
        document.getElementById('btnSubmit').textContent = 'Guardar cambios';
        document.getElementById('estadoField').style.display = 'block';

        document.getElementById('camposEstandar').style.display  = p.tipo === 'estandar'     ? 'block' : 'none';
        document.getElementById('camposPersonal').style.display  = p.tipo === 'personalizado' ? 'block' : 'none';

        if (p.tipo === 'estandar') {
            document.getElementById('f_catalogo_e').value = p.catalogo_id || '';
        } else {
            document.getElementById('f_cliente_nombre').value   = p.cliente_nombre || '';
            document.getElementById('f_cliente_contacto').value = p.cliente_contacto || '';
            document.getElementById('f_catalogo_p').value       = p.catalogo_id || '';
            document.getElementById('f_domicilio').checked      = p.entrega_domicilio;
            document.getElementById('f_direccion').value        = p.direccion_entrega || '';
            document.getElementById('f_costo_envio').value      = p.costo_envio || '';
            if (p.entrega_domicilio) document.getElementById('domicilioFields').classList.add('visible');
            else document.getElementById('domicilioFields').classList.remove('visible');

            if (p.imagen_referencia) {
                document.getElementById('imgRefActual').src = '../../' + p.imagen_referencia;
                document.getElementById('imgRefActualWrap').style.display = 'block';
            }
        }

        document.getElementById('f_descripcion').value   = p.descripcion || '';
        document.getElementById('f_fecha_entrega').value = p.fecha_entrega || '';
        document.getElementById('f_prioridad').value     = p.prioridad || 'normal';
        document.getElementById('f_estado').value        = p.estado || 'pendiente';

        document.getElementById('modalOverlay').classList.add('active');
    }

    function abrirModalEstado(id, estadoActual) {
        document.getElementById('estadoPedidoId').value = id;
        document.getElementById('estadoSelect').value   = estadoActual;
        document.getElementById('modalEstado').classList.add('active');
    }

    function toggleDomicilio() {
        const checked = document.getElementById('f_domicilio').checked;
        document.getElementById('domicilioFields').classList.toggle('visible', checked);
    }

    // Preview imagen referencia
    document.getElementById('f_img_ref').addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = e => {
                const img = document.getElementById('imgRefPreview');
                img.src = e.target.result;
                img.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });

    // Búsqueda y filtro
    function filtrarTabla(tablaId, texto) {
        const filas = document.querySelectorAll('#' + tablaId + ' tbody tr[data-estado]');
        filas.forEach(f => {
            f.style.display = f.textContent.toLowerCase().includes(texto.toLowerCase()) ? '' : 'none';
        });
    }

    function filtrarEstado(tablaId, estado) {
        const filas = document.querySelectorAll('#' + tablaId + ' tbody tr[data-estado]');
        filas.forEach(f => {
            f.style.display = !estado || f.dataset.estado === estado ? '' : 'none';
        });
    }
</script>
</body>
</html>