<?php
// vista/patrones/mis_patrones.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controlador/patrones_ctrl.php';
require_once __DIR__ . '/../../controlador/catalogo_ctrl.php';

require_once __DIR__ . '/../../modelo/autorizacion.php';
requierePermiso('mis_patrones', 'lectura');

$ctrl      = new PatronesCtrl();
$catCtrl   = new CatalogoCtrl();
$patrones  = $ctrl->listarAprobados();
$productos = $catCtrl->listar();
$bonuses   = $ctrl->bonusesPorEmpleado($_SESSION['usuario_id']);
$nombre    = $_SESSION['nombre'];
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
            --coral:#F2907A; --lavender:#C9B8E8;
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

        .container { max-width:1100px; margin:0 auto; padding:32px 24px 60px; }

        .mensaje { padding:13px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; font-weight:600; }
        .success { background:#edfaf7; color:#1a6b53; border-left:4px solid var(--mint); }
        .error   { background:#fef0ee; color:#b94030; border-left:4px solid var(--coral); }

        /* BONUS BANNER */
        .bonus-banner {
            background:linear-gradient(135deg, var(--yellow), var(--coral));
            border-radius:14px; padding:16px 22px; margin-bottom:28px;
            display:flex; align-items:center; gap:14px; color:var(--navy);
        }
        .bonus-icon { font-size:32px; }
        .bonus-text h3 { font-family:'DM Serif Display',serif; font-size:16px; margin-bottom:2px; }
        .bonus-text p  { font-size:13px; opacity:0.8; }
        .bonus-count {
            margin-left:auto; background:rgba(255,255,255,0.4); border-radius:12px;
            padding:10px 16px; text-align:center; flex-shrink:0;
        }
        .bonus-count .num { font-family:'DM Serif Display',serif; font-size:28px; color:var(--navy); }
        .bonus-count .lbl { font-size:11px; font-weight:800; color:rgba(44,62,107,0.7); }

        .section-title {
            font-family:'DM Serif Display',serif; font-size:18px; color:var(--navy);
            margin-bottom:16px; display:flex; align-items:center; gap:10px;
        }
        .section-title::after { content:''; flex:1; height:1px; background:var(--border); }

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

        /* GRID */
        .patrones-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:20px; }

        .patron-card {
            background:var(--white); border-radius:16px; border:1.5px solid var(--border);
            overflow:hidden; box-shadow:0 4px 16px rgba(44,62,107,0.05);
            cursor:pointer; transition:transform 0.2s, box-shadow 0.2s;
            animation: fadeUp 0.4s ease both;
        }
        .patron-card:hover { transform:translateY(-3px); box-shadow:0 10px 28px rgba(44,62,107,0.12); }

        .patron-img { width:100%; height:170px; overflow:hidden; background:var(--sky-pale); display:flex; align-items:center; justify-content:center; font-size:48px; }
        .patron-img img { width:100%; height:100%; object-fit:cover; }
        .patron-body { padding:16px 18px; }
        .patron-titulo { font-family:'DM Serif Display',serif; font-size:15px; color:var(--navy); margin-bottom:6px; }
        .patron-producto {
            display:inline-flex; align-items:center; gap:4px;
            background:rgba(201,184,232,0.2); border:1px solid rgba(201,184,232,0.5);
            color:#5a3e8a; border-radius:20px; font-size:11px; font-weight:700; padding:3px 10px;
        }

        .empty-state { text-align:center; padding:60px 20px; color:var(--text-soft); grid-column:1/-1; }
        .empty-state .icon { font-size:48px; margin-bottom:12px; }

        /* MODAL DETALLE */
        .modal-overlay {
            display:none; position:fixed; inset:0; background:rgba(44,62,107,0.45);
            z-index:500; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(3px);
        }
        .modal-overlay.active { display:flex; }
        .modal {
            background:var(--white); border-radius:20px; width:100%; max-width:640px;
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

        .detalle-img { width:100%; max-height:320px; object-fit:contain; border-radius:12px; border:1px solid var(--border); margin-bottom:20px; }
        .detalle-instrucciones { font-size:14px; line-height:1.9; color:var(--text-main); white-space:pre-wrap; }

        /* MODAL CONTRIBUIR */
        .form-group { margin-bottom:16px; }
        label { display:block; margin-bottom:6px; font-size:11px; font-weight:800; letter-spacing:0.07em; text-transform:uppercase; color:var(--navy); }
        .required { color:var(--coral); margin-left:2px; }
        input[type=text], select, textarea {
            width:100%; padding:11px 14px; border:1.5px solid var(--border); border-radius:10px;
            font-size:14px; font-family:'Nunito',sans-serif; background:var(--sky-pale); color:var(--text-main);
        }
        input[type=text]:focus, select:focus, textarea:focus {
            outline:none; border-color:var(--sky); background:white; box-shadow:0 0 0 3px rgba(122,191,204,0.18);
        }
        input[type=file] {
            width:100%; padding:10px 14px; border:1.5px dashed var(--border); border-radius:10px;
            font-size:13px; font-family:'Nunito',sans-serif; background:var(--sky-pale); cursor:pointer;
        }
        textarea { resize:vertical; min-height:140px; }
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
        .img-preview { max-width:100%; max-height:140px; border-radius:10px; border:1.5px solid var(--border); object-fit:cover; margin-top:10px; display:none; }

        /* Contribución info box */
        .contrib-info {
            background:rgba(247,206,122,0.15); border:1px solid rgba(247,206,122,0.5);
            border-radius:10px; padding:12px 16px; margin-bottom:16px;
            font-size:13px; color:#9a7000;
        }
        .contrib-info strong { display:block; margin-bottom:4px; }

        /* Bonuses list */
        .bonus-list { display:flex; flex-direction:column; gap:8px; margin-top:8px; }
        .bonus-item {
            display:flex; align-items:center; gap:10px; background:var(--sky-pale);
            border:1px solid var(--border); border-radius:10px; padding:10px 14px;
        }
        .bonus-item .bi-icon { font-size:20px; }
        .bonus-item .bi-motivo { font-size:13px; font-weight:700; color:var(--navy); }
        .bonus-item .bi-fecha  { font-size:11px; color:var(--text-soft); margin-top:2px; }

        @keyframes fadeUp {
            from { opacity:0; transform:translateY(10px); }
            to   { opacity:1; transform:translateY(0); }
        }
        @media(max-width:768px) {
            .navbar { padding:0 16px; }
            .container { padding:20px 14px 48px; }
            .patrones-grid { grid-template-columns:1fr; }
            .bonus-banner { flex-wrap:wrap; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="navbar-left">
        <a href="../menu_tejedor.php" class="back-btn">← Menú</a>
        <span class="page-title">📐 Patrones</span>
    </div>
    <a class="navbar-brand" href="../menu_tejedor.php">
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

    <!-- Banner de bonuses -->
    <?php if (!empty($bonuses)): ?>
    <div class="bonus-banner">
        <div class="bonus-icon">⭐</div>
        <div class="bonus-text">
            <h3>¡Tienes bonuses registrados!</h3>
            <p>Gracias por contribuir con patrones al equipo</p>
        </div>
        <div class="bonus-count">
            <div class="num"><?php echo count($bonuses); ?></div>
            <div class="lbl">Bonus(es)</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Patrones disponibles -->
    <div class="section-title">Patrones disponibles</div>
    <div class="toolbar">
        <input type="text" class="search-input" placeholder="🔍 Buscar patrón o producto..."
               oninput="filtrarGrid(this.value)">
        <button class="btn-primary" onclick="abrirModalContribuir()">⭐ Contribuir patrón nuevo</button>
    </div>

    <div class="patrones-grid" id="patronesGrid">
        <?php if (empty($patrones)): ?>
        <div class="empty-state">
            <div class="icon">📐</div>
            <p>No hay patrones disponibles todavía.</p>
        </div>
        <?php else: foreach ($patrones as $i => $p): ?>
        <div class="patron-card" style="animation-delay:<?php echo $i*0.05; ?>s"
             onclick="verDetalle(<?php echo htmlspecialchars(json_encode($p)); ?>)">
            <div class="patron-img">
                <?php if ($p['imagen_ruta']): ?>
                    <img src="../../<?php echo htmlspecialchars($p['imagen_ruta']); ?>" alt="">
                <?php else: ?>📐<?php endif; ?>
            </div>
            <div class="patron-body">
                <div class="patron-titulo"><?php echo htmlspecialchars($p['titulo']); ?></div>
                <?php if ($p['producto_nombre']): ?>
                <div class="patron-producto">🧶 <?php echo htmlspecialchars($p['producto_nombre']); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Mis bonuses -->
    <?php if (!empty($bonuses)): ?>
    <div class="section-title" style="margin-top:40px;">⭐ Mis contribuciones aprobadas</div>
    <div class="bonus-list">
        <?php foreach ($bonuses as $b): ?>
        <div class="bonus-item">
            <div class="bi-icon">🏅</div>
            <div>
                <div class="bi-motivo"><?php echo htmlspecialchars($b['motivo']); ?></div>
                <div class="bi-fecha"><?php echo date('d/m/Y', strtotime($b['fecha'])); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<!-- MODAL VER DETALLE -->
<div class="modal-overlay" id="modalDetalle">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="d_titulo">—</span>
            <button class="modal-close" onclick="cerrarModal('modalDetalle')">✕</button>
        </div>
        <div class="modal-body">
            <div id="d_producto" style="margin-bottom:14px;"></div>
            <img id="d_imagen" class="detalle-img" src="" alt="" style="display:none;">
            <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-soft);margin-bottom:10px;">Instrucciones</div>
            <div class="detalle-instrucciones" id="d_instrucciones"></div>
        </div>
    </div>
</div>

<!-- MODAL CONTRIBUIR -->
<div class="modal-overlay" id="modalContribuir">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">⭐ Contribuir patrón nuevo</span>
            <button class="modal-close" onclick="cerrarModal('modalContribuir')">✕</button>
        </div>
        <form action="../../controlador/patrones_ctrl.php" method="POST" enctype="multipart/form-data">
<?php echo campoCsrf(); ?>
            <input type="hidden" name="accion" value="contribuir">
            <div class="modal-body">
                <div class="contrib-info">
                    <strong>¿Cómo funciona?</strong>
                    Sube tu patrón para revisión del administrador. Si es aprobado, quedará disponible para todo el equipo y recibirás un bonus registrado en tu perfil 🎉
                </div>
                <div class="section-divider">Tu patrón</div>
                <div class="form-group">
                    <label>Título del patrón <span class="required">*</span></label>
                    <input type="text" name="titulo" placeholder="Ej: Patrón Chimuelo Amigurumi">
                </div>
                <div class="form-group">
                    <label>Producto relacionado (si aplica)</label>
                    <select name="catalogo_id">
                        <option value="">Sin producto asociado (nuevo/especial)</option>
                        <?php foreach ($productos as $prod):
                            $estaActivo = ($prod['activo'] === true || $prod['activo'] === 't' || $prod['activo'] === 'true');
                            if (!$estaActivo) continue; ?>
                        <option value="<?php echo $prod['id']; ?>"><?php echo htmlspecialchars($prod['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="section-divider">Imagen del patrón</div>
                <div class="form-group">
                    <label>Foto del patrón <span class="required">*</span></label>
                    <input type="file" name="imagen" id="c_imagen" accept="image/jpeg,image/png,image/webp">
                    <img id="c_img_preview" class="img-preview" src="" alt="">
                </div>
                <div class="section-divider">Instrucciones</div>
                <div class="form-group">
                    <label>Instrucciones paso a paso <span class="required">*</span></label>
                    <textarea name="instrucciones"
                        placeholder="Escribe las instrucciones detalladas: vueltas, puntos, materiales, etc."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="cerrarModal('modalContribuir')">Cancelar</button>
                <button type="submit" class="btn-primary">⭐ Enviar para revisión</button>
            </div>
        </form>
    </div>
</div>

<script>
    function cerrarModal(id) { document.getElementById(id).classList.remove('active'); }
    document.querySelectorAll('.modal-overlay').forEach(el => {
        el.addEventListener('click', e => { if (e.target === el) el.classList.remove('active'); });
    });

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

    function abrirModalContribuir() {
        document.getElementById('modalContribuir').classList.add('active');
    }

    document.getElementById('c_imagen').addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = e => {
                const img = document.getElementById('c_img_preview');
                img.src = e.target.result; img.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });

    function filtrarGrid(texto) {
        document.querySelectorAll('#patronesGrid .patron-card').forEach(card => {
            card.style.display = card.textContent.toLowerCase().includes(texto.toLowerCase()) ? '' : 'none';
        });
    }
</script>
</body>
</html>