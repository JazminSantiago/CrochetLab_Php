<?php
// vista/catalogo/index.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controlador/catalogo_ctrl.php';

require_once __DIR__ . '/../../modelo/autorizacion.php';
requierePermiso('catalogo', 'lectura');

$ctrl       = new CatalogoCtrl();
$productos  = $ctrl->listar();
$categorias = $ctrl->listarCategorias();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrochetLab — Catálogo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    <style>
        :root {
            --sky:       #7ABFCC;
            --sky-pale:  #e8f5f8;
            --navy:      #2C3E6B;
            --coral:     #F2907A;
            --coral-dark:#d97060;
            --lavender:  #C9B8E8;
            --mint:      #8ECFC0;
            --yellow:    #F7CE7A;
            --white:     #ffffff;
            --text-main: #2C3E6B;
            --text-soft: #6a7fa8;
            --border:    #daedf2;
            --bg:        #f0f8fa;
        }

        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family: 'Nunito', sans-serif;
            background: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
        }

        /* ── NAVBAR ── */
        .navbar {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 200;
            box-shadow: 0 2px 16px rgba(44,62,107,0.07);
        }

        .navbar::before {
            content:'';
            position:absolute;
            top:0;left:0;right:0;
            height:4px;
            background:linear-gradient(90deg,var(--coral),var(--yellow),var(--lavender),var(--mint),var(--sky));
        }

        .navbar-left { display:flex; align-items:center; gap:16px; }

        .back-btn {
            display:flex; align-items:center; gap:6px;
            color:var(--text-soft); text-decoration:none;
            font-size:13px; font-weight:700;
            padding:6px 12px;
            border-radius:8px;
            border:1px solid var(--border);
            background:var(--sky-pale);
            transition:all 0.2s;
        }
        .back-btn:hover { color:var(--navy); border-color:var(--sky); }

        .page-title { font-family:'DM Serif Display',serif; font-size:20px; color:var(--navy); }

        .navbar-brand { display:flex; align-items:center; gap:10px; text-decoration:none; }
        .navbar-brand img { width:32px;height:32px;border-radius:50%;object-fit:cover; }
        .navbar-brand span { font-family:'DM Serif Display',serif;font-size:20px;color:var(--navy); }
        .navbar-brand span em { color:var(--coral);font-style:normal; }

        /* ── CONTAINER ── */
        .container { max-width:1100px; margin:0 auto; padding:32px 24px 60px; }

        /* ── TOOLBAR ── */
        .toolbar {
            display:flex; align-items:center;
            justify-content:space-between;
            margin-bottom:24px; gap:12px; flex-wrap:wrap;
        }
        .toolbar-left { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }

        .search-input {
            padding:10px 16px;
            border:1.5px solid var(--border);
            border-radius:10px;
            font-size:14px;
            font-family:'Nunito',sans-serif;
            background:var(--white);
            color:var(--text-main);
            width:220px;
            transition:border-color 0.2s, box-shadow 0.2s;
        }
        .search-input:focus { outline:none; border-color:var(--sky); box-shadow:0 0 0 3px rgba(122,191,204,0.18); }

        .filter-select {
            padding:10px 14px;
            border:1.5px solid var(--border);
            border-radius:10px;
            font-size:14px;
            font-family:'Nunito',sans-serif;
            background:var(--white);
            color:var(--text-main);
            cursor:pointer;
        }

        .btn-primary {
            display:inline-flex; align-items:center; gap:6px;
            background:var(--navy); color:white; border:none;
            border-radius:10px; padding:10px 20px;
            font-size:14px; font-weight:800;
            font-family:'Nunito',sans-serif;
            cursor:pointer;
            transition:background 0.2s, transform 0.1s;
        }
        .btn-primary:hover { background:var(--coral); transform:translateY(-1px); }

        /* ── MENSAJE ── */
        .mensaje { padding:13px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; font-weight:600; }
        .success { background:#edfaf7; color:#1a6b53; border-left:4px solid var(--mint); }
        .error   { background:#fef0ee; color:#b94030; border-left:4px solid var(--coral); }

        /* ── TABLA ── */
        .table-wrap {
            background:var(--white);
            border-radius:16px;
            border:1px solid var(--border);
            overflow:hidden;
            box-shadow:0 4px 20px rgba(44,62,107,0.06);
            animation: fadeUp 0.5s ease both;
        }

        table { width:100%; border-collapse:collapse; }
        thead { background:var(--sky-pale); }
        th {
            padding:14px 16px; text-align:left;
            font-size:11px; font-weight:800;
            text-transform:uppercase; letter-spacing:0.07em;
            color:var(--text-soft); border-bottom:1px solid var(--border);
            white-space:nowrap;
        }
        td { padding:13px 16px; font-size:14px; border-bottom:1px solid var(--border); vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tbody tr { transition:background 0.15s; }
        tbody tr:hover { background:var(--sky-pale); }

        /* Producto info */
        .prod-name { font-weight:700; color:var(--navy); margin-bottom:2px; }
        .prod-desc { font-size:12px; color:var(--text-soft); }

        /* Badges */
        .badge {
            display:inline-flex; align-items:center; gap:4px;
            border-radius:20px; font-size:11px; font-weight:700; padding:4px 10px;
        }
        .badge-cat      { background:rgba(201,184,232,0.2); color:#5a3e8a; border:1px solid rgba(201,184,232,0.5); }
        .badge-activo   { background:rgba(142,207,192,0.2); color:#1a6b53; }
        .badge-inactivo { background:rgba(242,144,122,0.2); color:#b94030; }

        /* Stock indicator */
        .stock-wrap { display:flex; flex-direction:column; gap:4px; min-width:100px; }
        .stock-nums { font-size:13px; font-weight:700; }
        .stock-bar-bg {
            height:5px; background:var(--border);
            border-radius:4px; overflow:hidden;
        }
        .stock-bar { height:100%; border-radius:4px; transition:width 0.4s; }
        .stock-ok      .stock-bar { background:var(--mint); }
        .stock-low     .stock-bar { background:var(--yellow); }
        .stock-critical .stock-bar { background:var(--coral); }
        .stock-label {
            font-size:10px; font-weight:800;
            text-transform:uppercase; letter-spacing:0.05em;
        }
        .stock-ok      .stock-label { color:#1a6b53; }
        .stock-low     .stock-label { color:#9a7000; }
        .stock-critical .stock-label { color:#b94030; }

        /* Precio */
        .precio { font-weight:800; color:var(--navy); }
        .no-precio { color:var(--text-soft); font-size:12px; }

        /* Acciones */
        .actions { display:flex; gap:6px; }
        .btn-action {
            padding:6px 12px; border-radius:8px;
            font-size:12px; font-weight:700;
            font-family:'Nunito',sans-serif;
            cursor:pointer; border:1.5px solid; transition:all 0.2s;
        }
        .btn-edit { background:var(--sky-pale); color:var(--navy); border-color:var(--border); }
        .btn-edit:hover { background:var(--sky); color:white; border-color:var(--sky); }
        .btn-deactivate { background:rgba(242,144,122,0.1); color:var(--coral-dark); border-color:rgba(242,144,122,0.4); }
        .btn-deactivate:hover { background:var(--coral); color:white; border-color:var(--coral); }
        .btn-activate { background:rgba(142,207,192,0.1); color:#1a6b53; border-color:rgba(142,207,192,0.4); }
        .btn-activate:hover { background:var(--mint); color:white; border-color:var(--mint); }

        .empty-state { text-align:center; padding:60px 20px; color:var(--text-soft); }
        .empty-state .icon { font-size:48px; margin-bottom:12px; }

        /* ── MODAL ── */
        .modal-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(44,62,107,0.45); z-index:500;
            align-items:center; justify-content:center;
            padding:20px; backdrop-filter:blur(3px);
        }
        .modal-overlay.active { display:flex; }

        .modal {
            background:var(--white); border-radius:20px;
            width:100%; max-width:560px; max-height:90vh;
            overflow-y:auto;
            box-shadow:0 20px 60px rgba(44,62,107,0.2);
            animation:modalIn 0.3s cubic-bezier(0.22,1,0.36,1) both;
        }

        @keyframes modalIn {
            from { opacity:0; transform:translateY(20px) scale(0.97); }
            to   { opacity:1; transform:translateY(0) scale(1); }
        }

        .modal-header {
            padding:24px 28px 16px; border-bottom:1px solid var(--border);
            display:flex; align-items:center; justify-content:space-between;
        }
        .modal-title { font-family:'DM Serif Display',serif; font-size:20px; color:var(--navy); }
        .modal-close {
            background:none; border:none; font-size:22px; cursor:pointer;
            color:var(--text-soft); width:32px; height:32px;
            display:flex; align-items:center; justify-content:center;
            border-radius:8px; transition:background 0.2s;
        }
        .modal-close:hover { background:var(--sky-pale); color:var(--navy); }
        .modal-body { padding:24px 28px; }

        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .form-group { margin-bottom:16px; }

        label {
            display:block; margin-bottom:6px;
            font-size:11px; font-weight:800;
            letter-spacing:0.07em; text-transform:uppercase; color:var(--navy);
        }
        .required { color:var(--coral); margin-left:2px; }

        input, select, textarea {
            width:100%; padding:11px 14px;
            border:1.5px solid var(--border); border-radius:10px;
            font-size:14px; font-family:'Nunito',sans-serif;
            background:var(--sky-pale); color:var(--text-main);
            transition:border-color 0.2s, box-shadow 0.2s;
        }
        input:focus, select:focus, textarea:focus {
            outline:none; border-color:var(--sky); background:white;
            box-shadow:0 0 0 3px rgba(122,191,204,0.18);
        }
        textarea { resize:vertical; min-height:72px; }

        .modal-footer {
            padding:16px 28px 24px; display:flex;
            justify-content:flex-end; gap:10px; border-top:1px solid var(--border);
        }
        .btn-cancel {
            padding:10px 20px; border-radius:10px;
            font-size:14px; font-weight:700;
            font-family:'Nunito',sans-serif; cursor:pointer;
            background:var(--sky-pale); color:var(--text-soft);
            border:1.5px solid var(--border); transition:all 0.2s;
        }
        .btn-cancel:hover { background:var(--border); color:var(--navy); }

        .section-divider {
            font-size:11px; font-weight:800; text-transform:uppercase;
            letter-spacing:0.07em; color:var(--text-soft);
            margin:8px 0 16px; display:flex; align-items:center; gap:8px;
        }
        .section-divider::after { content:''; flex:1; height:1px; background:var(--border); }

        @keyframes fadeUp {
            from { opacity:0; transform:translateY(12px); }
            to   { opacity:1; transform:translateY(0); }
        }

        @media(max-width:768px) {
            .navbar { padding:0 16px; }
            .container { padding:20px 16px 48px; }
            .form-row { grid-template-columns:1fr; }
            .search-input { width:160px; }
            td, th { padding:10px 12px; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-left">
        <a href="../menu_principal.php" class="back-btn">← Menú</a>
        <span class="page-title">🧶 Catálogo</span>
    </div>
    <a class="navbar-brand" href="../menu_principal.php">
        <img src="../../assets/img/logo.png" alt="CrochetLab">
        <span>Crochet<em>Lab</em></span>
    </a>
</nav>

<!-- CONTENIDO -->
<div class="container">

    <?php if (isset($_SESSION['mensaje'])): ?>
        <div class="mensaje <?php echo $_SESSION['tipo_mensaje']; ?>">
            <?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); ?>
        </div>
    <?php endif; ?>

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="toolbar-left">
            <input type="text" class="search-input" id="searchInput" placeholder="🔍 Buscar producto...">
            <select class="filter-select" id="filterCat" onchange="filtrar()">
                <option value="">Todas las categorías</option>
                <?php foreach ($categorias as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat['nombre']); ?>">
                    <?php echo htmlspecialchars($cat['nombre']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select class="filter-select" id="filterEstado" onchange="filtrar()">
                <option value="">Todos</option>
                <option value="activo">Activos</option>
                <option value="inactivo">Inactivos</option>
            </select>
        </div>
        <button class="btn-primary" onclick="abrirModalCrear()">+ Nuevo Producto</button>
    </div>

    <!-- Tabla -->
    <div class="table-wrap">
        <table id="tablaProductos">
            <thead>
                <tr>
                    <th>Foto</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Precio</th>
                    <th>Stock</th>
                    <th>Elaboración</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($productos)): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="icon">🧶</div>
                            <p>No hay productos en el catálogo aún.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($productos as $p):
                    $pct = $p['stock_minimo'] > 0 ? min(100, round(($p['stock_actual'] / $p['stock_minimo']) * 100)) : 100;
                    if ($p['stock_actual'] >= $p['stock_minimo']) $stockClass = 'stock-ok';
                    elseif ($p['stock_actual'] > 0)               $stockClass = 'stock-low';
                    else                                           $stockClass = 'stock-critical';
                    $stockLabel = match($stockClass) {
                        'stock-ok'       => 'OK',
                        'stock-low'      => 'Bajo',
                        'stock-critical' => 'Crítico',
                    };
                ?>
                <tr <?php $estaActivo = ($p['activo'] === true || $p['activo'] === 't' || $p['activo'] === 'true'); ?>
                    data-estado="<?php echo $estaActivo ? 'activo' : 'inactivo'; ?>"
                    data-cat="<?php echo htmlspecialchars($p['categoria_nombre'] ?? ''); ?>">
                    <td>
                        <?php if ($p['imagen_ruta']): ?>
                            <img src="../../<?php echo htmlspecialchars($p['imagen_ruta']); ?>"
                                 alt="<?php echo htmlspecialchars($p['nombre']); ?>"
                                 style="width:48px;height:48px;border-radius:8px;object-fit:cover;border:1px solid var(--border);">
                        <?php else: ?>
                            <div style="width:48px;height:48px;border-radius:8px;background:var(--sky-pale);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:20px;">🧶</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="prod-name"><?php echo htmlspecialchars($p['nombre']); ?></div>
                        <?php if ($p['descripcion']): ?>
                        <div class="prod-desc"><?php echo htmlspecialchars(substr($p['descripcion'], 0, 60)) . (strlen($p['descripcion']) > 60 ? '…' : ''); ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['categoria_nombre']): ?>
                            <span class="badge badge-cat"><?php echo htmlspecialchars($p['categoria_nombre']); ?></span>
                        <?php else: ?>
                            <span style="color:var(--text-soft);font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['precio']): ?>
                            <span class="precio">$<?php echo number_format($p['precio'], 2); ?></span>
                        <?php else: ?>
                            <span class="no-precio">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="stock-wrap <?php echo $stockClass; ?>">
                            <div class="stock-nums"><?php echo $p['stock_actual']; ?> / <?php echo $p['stock_minimo']; ?></div>
                            <div class="stock-bar-bg">
                                <div class="stock-bar" style="width:<?php echo $pct; ?>%"></div>
                            </div>
                            <div class="stock-label"><?php echo $stockLabel; ?></div>
                        </div>
                    </td>
                    <td>
                        <?php if ($p['tiempo_elaboracion_dias']): ?>
                            <?php echo $p['tiempo_elaboracion_dias']; ?> día<?php echo $p['tiempo_elaboracion_dias'] != 1 ? 's' : ''; ?>
                        <?php else: ?>
                            <span style="color:var(--text-soft);font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['activo']): ?>
                            <span class="badge badge-activo">● Activo</span>
                        <?php else: ?>
                            <span class="badge badge-inactivo">● Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="actions">
                            <button class="btn-action btn-edit"
                                onclick="abrirModalEditar(<?php echo htmlspecialchars(json_encode($p)); ?>)">
                                ✏️ Editar
                            </button>
                            <form action="../../controlador/catalogo_ctrl.php" method="POST" style="margin:0;">
<?php echo campoCsrf(); ?>
                                <input type="hidden" name="accion" value="toggle_activo">
                                <input type="hidden" name="producto_id" value="<?php echo $p['id']; ?>">
                                <button type="submit"
                                    class="btn-action <?php echo $p['activo'] ? 'btn-deactivate' : 'btn-activate'; ?>"
                                    onclick="return confirm('<?php echo $p['activo'] ? '¿Desactivar' : '¿Activar'; ?> \"<?php echo htmlspecialchars($p['nombre']); ?>\"?')">
                                    <?php echo $p['activo'] ? '🔒 Desactivar' : '🔓 Activar'; ?>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalTitle">Nuevo Producto</span>
            <button class="modal-close" onclick="cerrarModal()">✕</button>
        </div>
        <form action="../../controlador/catalogo_ctrl.php" method="POST" enctype="multipart/form-data">
<?php echo campoCsrf(); ?>
            <input type="hidden" name="accion" id="formAccion" value="crear">
            <input type="hidden" name="producto_id" id="formProductoId" value="">

            <div class="modal-body">
                <div class="section-divider">Imagen del producto</div>
                <div class="form-group">
                    <label>Foto del producto terminado</label>
                    <input type="file" name="imagen" id="f_imagen" accept="image/jpeg,image/png,image/webp">
                    <div id="imgPreviewWrap" style="margin-top:10px;display:none;">
                        <img id="imgPreview" src="" alt="Vista previa"
                             style="max-width:100%;max-height:160px;border-radius:10px;border:1.5px solid var(--border);object-fit:cover;">
                    </div>
                    <div id="imgActualWrap" style="margin-top:10px;display:none;">
                        <p style="font-size:11px;color:var(--text-soft);margin-bottom:6px;">Imagen actual:</p>
                        <img id="imgActual" src="" alt="Imagen actual"
                             style="max-width:100%;max-height:120px;border-radius:10px;border:1.5px solid var(--border);object-fit:cover;">
                    </div>
                </div>

                <div class="section-divider">Información general</div>
                <div class="form-group">
                    <label>Nombre del producto <span class="required">*</span></label>
                    <input type="text" name="nombre" id="f_nombre" placeholder="Ej: Bolso Tote Grande">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Categoría</label>
                        <select name="categoria_id" id="f_categoria">
                            <option value="">Sin categoría</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Precio (MXN)</label>
                        <input type="number" name="precio" id="f_precio" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="descripcion" id="f_descripcion" placeholder="Describe el producto..."></textarea>
                </div>

                <div class="section-divider">Inventario y producción</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Stock actual</label>
                        <input type="number" name="stock_actual" id="f_stock_actual" min="0" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label>Stock mínimo</label>
                        <input type="number" name="stock_minimo" id="f_stock_minimo" min="0" placeholder="5">
                    </div>
                </div>
                <div class="form-group">
                    <label>Tiempo de elaboración (días)</label>
                    <input type="number" name="tiempo_elaboracion_dias" id="f_tiempo" min="1" placeholder="Ej: 3">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn-primary" id="btnSubmit">Agregar producto</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Preview de imagen al seleccionar archivo
    document.getElementById('f_imagen').addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById('imgPreview').src = e.target.result;
                document.getElementById('imgPreviewWrap').style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });

    function abrirModalCrear() {
        document.getElementById('modalTitle').textContent = 'Nuevo Producto';
        document.getElementById('formAccion').value       = 'crear';
        document.getElementById('formProductoId').value   = '';
        document.getElementById('btnSubmit').textContent  = 'Agregar producto';
        ['f_nombre','f_precio','f_descripcion','f_stock_actual','f_stock_minimo','f_tiempo']
            .forEach(id => document.getElementById(id).value = '');
        document.getElementById('f_categoria').value = '';
        document.getElementById('f_imagen').value = '';
        document.getElementById('imgPreviewWrap').style.display = 'none';
        document.getElementById('imgActualWrap').style.display = 'none';
        document.getElementById('modalOverlay').classList.add('active');
    }

    function abrirModalEditar(p) {
        document.getElementById('modalTitle').textContent = 'Editar Producto';
        document.getElementById('formAccion').value       = 'editar';
        document.getElementById('formProductoId').value   = p.id;
        document.getElementById('btnSubmit').textContent  = 'Guardar cambios';
        document.getElementById('f_nombre').value         = p.nombre;
        document.getElementById('f_categoria').value      = p.categoria_id || '';
        document.getElementById('f_precio').value         = p.precio || '';
        document.getElementById('f_descripcion').value    = p.descripcion || '';
        document.getElementById('f_stock_actual').value   = p.stock_actual;
        document.getElementById('f_stock_minimo').value   = p.stock_minimo;
        document.getElementById('f_tiempo').value         = p.tiempo_elaboracion_dias || '';
        document.getElementById('f_imagen').value         = '';
        document.getElementById('imgPreviewWrap').style.display = 'none';

        // Mostrar imagen actual si existe
        if (p.imagen_ruta) {
            document.getElementById('imgActual').src = '../../' + p.imagen_ruta;
            document.getElementById('imgActualWrap').style.display = 'block';
        } else {
            document.getElementById('imgActualWrap').style.display = 'none';
        }

        document.getElementById('modalOverlay').classList.add('active');
    }

    function cerrarModal() {
        document.getElementById('modalOverlay').classList.remove('active');
    }

    document.getElementById('modalOverlay').addEventListener('click', function(e) {
        if (e.target === this) cerrarModal();
    });

    document.getElementById('searchInput').addEventListener('input', filtrar);

    function filtrar() {
        const busqueda = document.getElementById('searchInput').value.toLowerCase();
        const cat      = document.getElementById('filterCat').value;
        const estado   = document.getElementById('filterEstado').value;
        document.querySelectorAll('#tablaProductos tbody tr[data-estado]').forEach(fila => {
            const texto        = fila.textContent.toLowerCase();
            const filaCat      = fila.dataset.cat;
            const filaEstado   = fila.dataset.estado;
            const okTexto  = texto.includes(busqueda);
            const okCat    = !cat    || filaCat   === cat;
            const okEstado = !estado || filaEstado === estado;
            fila.style.display = (okTexto && okCat && okEstado) ? '' : 'none';
        });
    }
</script>
</body>
</html>