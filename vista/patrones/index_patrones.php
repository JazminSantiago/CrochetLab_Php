<?php

    
// vista/patrones/index_patrones.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controlador/patrones_ctrl.php';
require_once __DIR__ . '/../../controlador/catalogo_ctrl.php';

require_once __DIR__ . '/../../modelo/autorizacion.php';
requierePermiso('patrones', 'lectura');

$ctrl      = new PatronesCtrl();
$catCtrl   = new CatalogoCtrl();
$patrones  = $ctrl->listar();
$productos = $catCtrl->listar();
$pendientes = $ctrl->contarPendientes();

$tab = $_GET['tab'] ?? 'aprobado';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrochetLab — Patrones</title>
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

        .mensaje { padding:13px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; font-weight:600; }
        .success { background:#edfaf7; color:#1a6b53; border-left:4px solid var(--mint); }
        .error   { background:#fef0ee; color:#b94030; border-left:4px solid var(--coral); }

        /* TABS */
        .tabs-wrap {
            display:flex; gap:4px; margin-bottom:28px;
            background:var(--white); border:1px solid var(--border);
            border-radius:12px; padding:6px; width:fit-content;
        }
        .tab-btn {
            padding:10px 20px; border-radius:8px; border:none;
            font-size:13px; font-weight:700; font-family:'Nunito',sans-serif;
            cursor:pointer; transition:all 0.2s; color:var(--text-soft); background:transparent;
            display:flex; align-items:center; gap:6px;
        }
        .tab-btn.active { background:var(--navy); color:white; }
        .tab-btn:not(.active):hover { background:var(--sky-pale); color:var(--navy); }
        .badge-count {
            background:var(--coral); color:white; border-radius:20px;
            font-size:10px; font-weight:800; padding:2px 7px; min-width:20px; text-align:center;
        }
        .tab-btn.active .badge-count { background:rgba(255,255,255,0.3); }

        .tab-panel { display:none; }
        .tab-panel.active { display:block; }

        /* TOOLBAR */
        .toolbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; gap:12px; flex-wrap:wrap; }
        .search-input {
            padding:10px 16px; border:1.5px solid var(--border); border-radius:10px;
            font-size:14px; font-family:'Nunito',sans-serif; background:var(--white);
            color:var(--text-main); width:220px;
        }
        .search-input:focus { outline:none; border-color:var(--sky); box-shadow:0 0 0 3px rgba(122,191,204,0.18); }
        .btn-primary {
            display:inline-flex; align-items:center; gap:6px; background:var(--navy);
            color:white; border:none; border-radius:10px; padding:10px 20px;
            font-size:14px; font-weight:800; font-family:'Nunito',sans-serif;
            cursor:pointer; transition:background 0.2s;
        }
        .btn-primary:hover { background:var(--coral); }

        /* GRID DE PATRONES */
        .patrones-grid {
            display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:20px;
        }

        .patron-card {
            background:var(--white); border-radius:16px; border:1.5px solid var(--border);
            overflow:hidden; box-shadow:0 4px 16px rgba(44,62,107,0.05);
            transition:transform 0.2s, box-shadow 0.2s;
            animation: fadeUp 0.4s ease both;
        }
        .patron-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(44,62,107,0.10); }

        .patron-img {
            width:100%; height:180px; object-fit:cover;
            background:var(--sky-pale); display:flex; align-items:center;
            justify-content:center; font-size:48px;
        }
        .patron-img img { width:100%; height:100%; object-fit:cover; }

        .patron-body { padding:16px 18px; }
        .patron-titulo { font-family:'DM Serif Display',serif; font-size:16px; color:var(--navy); margin-bottom:6px; }
        .patron-producto {
            display:inline-flex; align-items:center; gap:4px;
            background:rgba(201,184,232,0.2); border:1px solid rgba(201,184,232,0.5);
            color:#5a3e8a; border-radius:20px; font-size:11px; font-weight:700;
            padding:3px 10px; margin-bottom:10px;
        }
        .patron-instrucciones {
            font-size:13px; color:var(--text-soft); line-height:1.5;
            display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden;
            margin-bottom:12px;
        }
        .patron-meta { font-size:11px; color:var(--text-soft); margin-bottom:12px; }

        .patron-actions { display:flex; gap:6px; flex-wrap:wrap; }
        .btn-action {
            padding:6px 12px; border-radius:8px; font-size:12px; font-weight:700;
            font-family:'Nunito',sans-serif; cursor:pointer; border:1.5px solid; transition:all 0.2s;
        }
        .btn-edit   { background:var(--sky-pale); color:var(--navy); border-color:var(--border); }
        .btn-edit:hover { background:var(--sky); color:white; border-color:var(--sky); }
        .btn-delete { background:rgba(242,144,122,0.1); color:#b94030; border-color:rgba(242,144,122,0.4); }
        .btn-delete:hover { background:var(--coral); color:white; border-color:var(--coral); }
        .btn-approve { background:rgba(142,207,192,0.15); color:#1a6b53; border-color:rgba(142,207,192,0.5); }
        .btn-approve:hover { background:var(--mint); color:white; border-color:var(--mint); }
        .btn-reject { background:rgba(247,206,122,0.15); color:#9a7000; border-color:rgba(247,206,122,0.5); }
        .btn-reject:hover { background:var(--yellow); color:var(--navy); border-color:var(--yellow); }

        /* Contribución badge */
        .contrib-badge {
            display:inline-flex; align-items:center; gap:4px;
            background:rgba(247,206,122,0.2); border:1px solid rgba(247,206,122,0.5);
            color:#9a7000; border-radius:20px; font-size:10px; font-weight:800;
            padding:2px 8px; margin-bottom:8px;
        }

        .empty-state { text-align:center; padding:60px 20px; color:var(--text-soft); grid-column:1/-1; }
        .empty-state .icon { font-size:48px; margin-bottom:12px; }

        /* MODAL */
        .modal-overlay {
            display:none; position:fixed; inset:0; background:rgba(44,62,107,0.45);
            z-index:500; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(3px);
        }
        .modal-overlay.active { display:flex; }
        .modal {
            background:var(--white); border-radius:20px; width:100%; max-width:580px;
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
        .modal-footer { padding:16px 28px 22px; display:flex; justify-content:flex-end; gap:10px; border-top:1px solid var(--border); }
        .form-group { margin-bottom:16px; }
        label { display:block; margin-bottom:6px; font-size:11px; font-weight:800; letter-spacing:0.07em; text-transform:uppercase; color:var(--navy); }
        .required { color:var(--coral); margin-left:2px; }
        input[type=text], select, textarea {
            width:100%; padding:11px 14px; border:1.5px solid var(--border); border-radius:10px;
            font-size:14px; font-family:'Nunito',sans-serif; background:var(--sky-pale);
            color:var(--text-main); transition:border-color 0.2s;
        }
        input[type=text]:focus, select:focus, textarea:focus {
            outline:none; border-color:var(--sky); background:white;
            box-shadow:0 0 0 3px rgba(122,191,204,0.18);
        }
        input[type=file] {
            width:100%; padding:10px 14px; border:1.5px dashed var(--border); border-radius:10px;
            font-size:13px; font-family:'Nunito',sans-serif; background:var(--sky-pale); cursor:pointer;
        }
        textarea { resize:vertical; min-height:120px; }
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
        .img-preview { max-width:100%; max-height:140px; border-radius:10px; border:1.5px solid var(--border); object-fit:cover; margin-top:10px; display:none; }

        /* Modal detalle patrón */
        .detalle-img { width:100%; max-height:300px; object-fit:contain; border-radius:12px; border:1px solid var(--border); margin-bottom:16px; }
        .detalle-instrucciones { font-size:14px; line-height:1.8; color:var(--text-main); white-space:pre-wrap; }

        @keyframes fadeUp {
            from { opacity:0; transform:translateY(10px); }
            to   { opacity:1; transform:translateY(0); }
        }
        @media(max-width:768px) {
            .navbar { padding:0 16px; }
            .container { padding:20px 14px 48px; }
            .patrones-grid { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="navbar-left">
        <a href="../menu_principal.php" class="back-btn">← Menú</a>
        <span class="page-title">📐 Patrones</span>
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

    <div class="tabs-wrap">
        <button class="tab-btn <?php echo $tab === 'aprobado' ? 'active' : ''; ?>"
                onclick="cambiarTab('aprobado', this)">
            ✅ Aprobados
        </button>
        <button class="tab-btn <?php echo $tab === 'pendiente' ? 'active' : ''; ?>"
                onclick="cambiarTab('pendiente', this)">
            ⏳ Pendientes
            <?php if ($pendientes > 0): ?>
            <span class="badge-count"><?php echo $pendientes; ?></span>
            <?php endif; ?>
        </button>
        <button class="tab-btn <?php echo $tab === 'rechazado' ? 'active' : ''; ?>"
                onclick="cambiarTab('rechazado', this)">
            ❌ Rechazados
        </button>
    </div>

    <?php
    $aprobados  = array_filter($patrones, fn($p) => $p['estado'] === 'aprobado');
    $pendientesArr = array_filter($patrones, fn($p) => $p['estado'] === 'pendiente');
    $rechazados = array_filter($patrones, fn($p) => $p['estado'] === 'rechazado');
    ?>

    <!-- PANEL APROBADOS -->
    <div id="panel-aprobado" class="tab-panel <?php echo $tab === 'aprobado' ? 'active' : ''; ?>">
        <div class="toolbar">
            <input type="text" class="search-input" placeholder="🔍 Buscar patrón..."
                   oninput="filtrarGrid('gridAprobados', this.value)">
            <button class="btn-primary" onclick="abrirModalCrear()">+ Nuevo Patrón</button>
        </div>
        <div class="patrones-grid" id="gridAprobados">
            <?php if (empty($aprobados)): ?>
            <div class="empty-state"><div class="icon">📐</div><p>No hay patrones aprobados todavía.</p></div>
            <?php else: foreach ($aprobados as $i => $p): ?>
            <div class="patron-card" style="animation-delay:<?php echo $i*0.05; ?>s">
                <div class="patron-img">
                    <?php if ($p['imagen_ruta']): ?>
                        <img src="../../<?php echo htmlspecialchars($p['imagen_ruta']); ?>" alt="">
                    <?php else: ?>📐<?php endif; ?>
                </div>
                <div class="patron-body">
                    <?php if ($p['es_contribucion']): ?>
                    <div class="contrib-badge">⭐ Contribución tejedor</div>
                    <?php endif; ?>
                    <div class="patron-titulo"><?php echo htmlspecialchars($p['titulo']); ?></div>
                    <?php if ($p['producto_nombre']): ?>
                    <div class="patron-producto">🧶 <?php echo htmlspecialchars($p['producto_nombre']); ?></div>
                    <?php endif; ?>
                    <div class="patron-instrucciones"><?php echo htmlspecialchars($p['instrucciones'] ?? ''); ?></div>
                    <div class="patron-meta">Subido por <?php echo htmlspecialchars($p['creado_por_nombre'] ?? 'Admin'); ?></div>
                    <div class="patron-actions">
                        <button class="btn-action btn-edit" onclick="verDetalle(<?php echo htmlspecialchars(json_encode($p)); ?>)">👁 Ver</button>
                        <button class="btn-action btn-edit" onclick="abrirModalEditar(<?php echo htmlspecialchars(json_encode($p)); ?>)">✏️ Editar</button>
                        <form action="../../controlador/patrones_ctrl.php" method="POST" style="margin:0">
<?php echo campoCsrf(); ?>
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="patron_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="btn-action btn-delete"
                                onclick="return confirm('¿Eliminar este patrón?')">🗑️</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- PANEL PENDIENTES -->
    <div id="panel-pendiente" class="tab-panel <?php echo $tab === 'pendiente' ? 'active' : ''; ?>">
        <div class="patrones-grid">
            <?php if (empty($pendientesArr)): ?>
            <div class="empty-state"><div class="icon">⏳</div><p>No hay contribuciones pendientes de revisión.</p></div>
            <?php else: foreach ($pendientesArr as $i => $p): ?>
            <div class="patron-card" style="border-left:4px solid var(--yellow);animation-delay:<?php echo $i*0.05; ?>s">
                <div class="patron-img">
                    <?php if ($p['imagen_ruta']): ?>
                        <img src="../../<?php echo htmlspecialchars($p['imagen_ruta']); ?>" alt="">
                    <?php else: ?>📐<?php endif; ?>
                </div>
                <div class="patron-body">
                    <div class="contrib-badge">⭐ Contribución pendiente</div>
                    <div class="patron-titulo"><?php echo htmlspecialchars($p['titulo']); ?></div>
                    <?php if ($p['producto_nombre']): ?>
                    <div class="patron-producto">🧶 <?php echo htmlspecialchars($p['producto_nombre']); ?></div>
                    <?php endif; ?>
                    <div class="patron-instrucciones"><?php echo htmlspecialchars($p['instrucciones'] ?? ''); ?></div>
                    <div class="patron-meta">Enviado por <strong><?php echo htmlspecialchars($p['creado_por_nombre'] ?? '—'); ?></strong></div>
                    <div class="patron-actions">
                        <button class="btn-action btn-edit" onclick="verDetalle(<?php echo htmlspecialchars(json_encode($p)); ?>)">👁 Ver</button>
                        <form action="../../controlador/patrones_ctrl.php" method="POST" style="margin:0">
<?php echo campoCsrf(); ?>
                            <input type="hidden" name="accion" value="aprobar">
                            <input type="hidden" name="patron_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="btn-action btn-approve">✅ Aprobar</button>
                        </form>
                        <form action="../../controlador/patrones_ctrl.php" method="POST" style="margin:0">
<?php echo campoCsrf(); ?>
                            <input type="hidden" name="accion" value="rechazar">
                            <input type="hidden" name="patron_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="btn-action btn-reject"
                                onclick="return confirm('¿Rechazar esta contribución?')">✕ Rechazar</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- PANEL RECHAZADOS -->
    <div id="panel-rechazado" class="tab-panel <?php echo $tab === 'rechazado' ? 'active' : ''; ?>">
        <div class="patrones-grid">
            <?php if (empty($rechazados)): ?>
            <div class="empty-state"><div class="icon">❌</div><p>No hay contribuciones rechazadas.</p></div>
            <?php else: foreach ($rechazados as $i => $p): ?>
            <div class="patron-card" style="opacity:0.7;animation-delay:<?php echo $i*0.05; ?>s">
                <div class="patron-body" style="padding-top:18px;">
                    <div class="patron-titulo"><?php echo htmlspecialchars($p['titulo']); ?></div>
                    <div class="patron-meta">Enviado por <?php echo htmlspecialchars($p['creado_por_nombre'] ?? '—'); ?></div>
                    <div class="patron-actions">
                        <form action="../../controlador/patrones_ctrl.php" method="POST" style="margin:0">
<?php echo campoCsrf(); ?>
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="patron_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="btn-action btn-delete"
                                onclick="return confirm('¿Eliminar?')">🗑️ Eliminar</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- MODAL CREAR/EDITAR -->
<div class="modal-overlay" id="modalForm">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalFormTitle">Nuevo Patrón</span>
            <button class="modal-close" onclick="cerrarModal('modalForm')">✕</button>
        </div>
        <form action="../../controlador/patrones_ctrl.php" method="POST" enctype="multipart/form-data">
<?php echo campoCsrf(); ?>
            <input type="hidden" name="accion" id="f_accion" value="crear">
            <input type="hidden" name="patron_id" id="f_patron_id">
            <div class="modal-body">
                <div class="section-divider">Información</div>
                <div class="form-group">
                    <label>Título <span class="required">*</span></label>
                    <input type="text" name="titulo" id="f_titulo" placeholder="Ej: Patrón Bolso Hexagonal Grande">
                </div>
                <div class="form-group">
                    <label>Producto del catálogo</label>
                    <select name="catalogo_id" id="f_catalogo">
                        <option value="">Sin producto asociado</option>
                        <?php foreach ($productos as $prod): ?>
                        <option value="<?php echo $prod['id']; ?>"><?php echo htmlspecialchars($prod['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="section-divider">Imagen del patrón</div>
                <div class="form-group">
                    <label>Imagen (JPG, PNG, WEBP — máx 5MB)</label>
                    <input type="file" name="imagen" id="f_imagen" accept="image/jpeg,image/png,image/webp">
                    <img id="f_img_preview" class="img-preview" src="" alt="">
                    <div id="f_img_actual_wrap" style="display:none;margin-top:10px;">
                        <p style="font-size:11px;color:var(--text-soft);margin-bottom:6px;">Imagen actual:</p>
                        <img id="f_img_actual" class="img-preview" style="display:block;" src="" alt="">
                    </div>
                </div>
                <div class="section-divider">Instrucciones</div>
                <div class="form-group">
                    <label>Instrucciones del patrón</label>
                    <textarea name="instrucciones" id="f_instrucciones"
                        placeholder="Escribe las instrucciones paso a paso, vueltas, puntos, etc."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="cerrarModal('modalForm')">Cancelar</button>
                <button type="submit" class="btn-primary" id="f_btn_submit">Crear patrón</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL VER DETALLE -->
<div class="modal-overlay" id="modalDetalle">
    <div class="modal" style="max-width:640px;">
        <div class="modal-header">
            <span class="modal-title" id="d_titulo">—</span>
            <button class="modal-close" onclick="cerrarModal('modalDetalle')">✕</button>
        </div>
        <div class="modal-body">
            <div id="d_producto" style="margin-bottom:12px;"></div>
            <img id="d_imagen" class="detalle-img" src="" alt="" style="display:none;">
            <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-soft);margin-bottom:10px;">Instrucciones</div>
            <div class="detalle-instrucciones" id="d_instrucciones"></div>
        </div>
    </div>
</div>

<script>
    function cambiarTab(tab, btn) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('panel-' + tab).classList.add('active');
    }

    function cerrarModal(id) { document.getElementById(id).classList.remove('active'); }
    document.querySelectorAll('.modal-overlay').forEach(el => {
        el.addEventListener('click', e => { if (e.target === el) el.classList.remove('active'); });
    });

    function abrirModalCrear() {
        document.getElementById('modalFormTitle').textContent = 'Nuevo Patrón';
        document.getElementById('f_accion').value   = 'crear';
        document.getElementById('f_patron_id').value = '';
        document.getElementById('f_titulo').value    = '';
        document.getElementById('f_catalogo').value  = '';
        document.getElementById('f_instrucciones').value = '';
        document.getElementById('f_btn_submit').textContent = 'Crear patrón';
        document.getElementById('f_img_preview').style.display = 'none';
        document.getElementById('f_img_actual_wrap').style.display = 'none';
        document.getElementById('modalForm').classList.add('active');
    }

    function abrirModalEditar(p) {
        document.getElementById('modalFormTitle').textContent = 'Editar Patrón';
        document.getElementById('f_accion').value   = 'editar';
        document.getElementById('f_patron_id').value = p.id;
        document.getElementById('f_titulo').value    = p.titulo;
        document.getElementById('f_catalogo').value  = p.catalogo_id || '';
        document.getElementById('f_instrucciones').value = p.instrucciones || '';
        document.getElementById('f_btn_submit').textContent = 'Guardar cambios';
        document.getElementById('f_img_preview').style.display = 'none';
        if (p.imagen_ruta) {
            document.getElementById('f_img_actual').src = '../../' + p.imagen_ruta;
            document.getElementById('f_img_actual_wrap').style.display = 'block';
        } else {
            document.getElementById('f_img_actual_wrap').style.display = 'none';
        }
        document.getElementById('modalForm').classList.add('active');
    }

    function verDetalle(p) {
        document.getElementById('d_titulo').textContent = p.titulo;
        document.getElementById('d_instrucciones').textContent = p.instrucciones || 'Sin instrucciones registradas.';
        const imgEl = document.getElementById('d_imagen');
        if (p.imagen_ruta) { imgEl.src = '../../' + p.imagen_ruta; imgEl.style.display = 'block'; }
        else imgEl.style.display = 'none';
        const prodEl = document.getElementById('d_producto');
        prodEl.innerHTML = p.producto_nombre
            ? `<span style="background:rgba(201,184,232,0.2);border:1px solid rgba(201,184,232,0.5);color:#5a3e8a;border-radius:20px;font-size:11px;font-weight:700;padding:3px 10px;">🧶 ${p.producto_nombre}</span>`
            : '';
        document.getElementById('modalDetalle').classList.add('active');
    }

    document.getElementById('f_imagen').addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = e => {
                const img = document.getElementById('f_img_preview');
                img.src = e.target.result; img.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });

    function filtrarGrid(gridId, texto) {
        document.querySelectorAll('#' + gridId + ' .patron-card').forEach(card => {
            card.style.display = card.textContent.toLowerCase().includes(texto.toLowerCase()) ? '' : 'none';
        });
    }
</script>
</body>
</html>