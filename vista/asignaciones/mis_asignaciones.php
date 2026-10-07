<?php
// vista/asignaciones/mis_asignaciones.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controlador/asignaciones_ctrl.php';

require_once __DIR__ . '/../../modelo/autorizacion.php';
requierePermiso('mis_asignaciones', 'lectura');

$ctrl         = new AsignacionesCtrl();
$asignaciones = $ctrl->listarPorEmpleado($_SESSION['usuario_id']);
$nombre       = $_SESSION['nombre'];

function diasRestantes($fecha) {
    if (!$fecha) return null;
    $diff = (new DateTime('today'))->diff(new DateTime($fecha));
    return (int)$diff->format('%r%a');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrochetLab — Mis Asignaciones</title>
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

        .container { max-width:960px; margin:0 auto; padding:32px 24px 60px; }

        .mensaje { padding:13px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; font-weight:600; }
        .success { background:#edfaf7; color:#1a6b53; border-left:4px solid var(--mint); }
        .error   { background:#fef0ee; color:#b94030; border-left:4px solid var(--coral); }

        .toolbar { display:flex; align-items:center; gap:10px; margin-bottom:24px; flex-wrap:wrap; }
        .filter-btn {
            padding:8px 16px; border-radius:20px; border:1.5px solid var(--border);
            background:var(--white); font-size:13px; font-weight:700;
            font-family:'Nunito',sans-serif; cursor:pointer; color:var(--text-soft); transition:all 0.2s;
        }
        .filter-btn.active, .filter-btn:hover { background:var(--navy); color:white; border-color:var(--navy); }

        .cards-grid { display:grid; gap:20px; }

        .asig-card {
            background:var(--white); border-radius:16px; border:1.5px solid var(--border);
            overflow:hidden; box-shadow:0 4px 16px rgba(44,62,107,0.05);
            transition:box-shadow 0.2s, transform 0.2s;
            animation: fadeUp 0.4s ease both;
        }
        .asig-card:hover { box-shadow:0 8px 28px rgba(44,62,107,0.10); transform:translateY(-2px); }
        .asig-card.urgente  { border-left:4px solid var(--coral); }
        .asig-card.alta     { border-left:4px solid var(--yellow); }
        .asig-card.normal   { border-left:4px solid var(--mint); }
        .asig-card.completo { border-left:4px solid #3ecf8e; opacity:0.9; }

        .card-top { display:flex; gap:16px; padding:20px 22px 0; }
        .ref-img-wrap {
            width:80px; height:80px; flex-shrink:0; border-radius:12px; overflow:hidden;
            border:1.5px solid var(--border); background:var(--sky-pale);
            display:flex; align-items:center; justify-content:center; font-size:28px;
        }
        .ref-img-wrap img { width:100%; height:100%; object-fit:cover; }

        .card-info { flex:1; min-width:0; }
        .card-pedido-title {
            font-family:'DM Serif Display',serif; font-size:17px;
            color:var(--navy); margin-bottom:4px;
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
        }
        .card-meta { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px; }

        .badge {
            display:inline-flex; align-items:center; gap:3px;
            border-radius:20px; font-size:11px; font-weight:700; padding:3px 9px; border:1px solid;
        }
        .badge-tipo     { background:rgba(122,191,204,0.15); color:#1a6b7a; border-color:rgba(122,191,204,0.4); }
        .badge-urgente  { background:rgba(242,144,122,0.2);  color:#b94030; border-color:rgba(242,144,122,0.5); }
        .badge-alta     { background:rgba(247,206,122,0.2);  color:#9a7000; border-color:rgba(247,206,122,0.5); }
        .badge-normal   { background:rgba(106,127,168,0.1);  color:#6a7fa8; border-color:rgba(106,127,168,0.2); }
        .badge-completo { background:rgba(62,207,142,0.15);  color:#1a6b53; border-color:rgba(62,207,142,0.4); }
        .badge-prueba   { background:rgba(201,184,232,0.2);  color:#5a3e8a; border-color:rgba(201,184,232,0.5); }

        .dias-wrap { display:flex; align-items:center; gap:6px; font-size:13px; font-weight:700; }
        .dias-ok       { color:#1a6b53; }
        .dias-warning  { color:#9a7000; }
        .dias-critical { color:#b94030; }
        .dias-vencido  { color:#b94030; }

        .card-notas {
            margin:14px 22px 0; background:var(--sky-pale); border:1px solid var(--border);
            border-radius:10px; padding:11px 14px; font-size:13px; color:var(--text-soft); line-height:1.5;
        }
        .card-notas strong { color:var(--navy); font-size:11px; text-transform:uppercase; letter-spacing:0.06em; }

        .card-progress { padding:16px 22px 20px; }
        .prog-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
        .prog-label { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.07em; color:var(--navy); }
        .prog-pct { font-size:20px; font-weight:800; color:var(--navy); }

        .prog-bar-bg { height:10px; background:var(--border); border-radius:6px; overflow:hidden; margin-bottom:14px; }
        .prog-bar { height:100%; border-radius:6px; transition:width 0.5s ease; }
        .pct-0    .prog-bar { background:var(--border); }
        .pct-low  .prog-bar { background:var(--coral); }
        .pct-mid  .prog-bar { background:var(--yellow); }
        .pct-hi   .prog-bar { background:var(--mint); }
        .pct-done .prog-bar { background:#3ecf8e; }

        .slider-form { display:flex; flex-direction:column; gap:10px; }
        .slider-row { display:flex; align-items:center; gap:12px; }
        .slider-row input[type=range] {
            flex:1; height:6px; border:none; padding:0;
            background:transparent; accent-color:var(--navy); cursor:pointer;
        }
        .slider-val {
            min-width:44px; text-align:center; font-weight:800; font-size:14px;
            color:var(--navy); background:var(--sky-pale); border:1.5px solid var(--border);
            border-radius:8px; padding:4px 8px;
        }
        .btn-guardar {
            align-self:flex-end; padding:9px 22px;
            background:var(--navy); color:white; border:none;
            border-radius:10px; font-size:13px; font-weight:800;
            font-family:'Nunito',sans-serif; cursor:pointer; transition:background 0.2s;
        }
        .btn-guardar:hover { background:var(--coral); }
        .btn-guardar:disabled { background:var(--border); color:var(--text-soft); cursor:not-allowed; }

        /* SECCIÓN IMAGEN DE PRUEBA */
        .prueba-section {
            margin:0 22px 20px; border-top:1px solid var(--border); padding-top:16px;
        }
        .prueba-title {
            font-size:11px; font-weight:800; text-transform:uppercase;
            letter-spacing:0.07em; color:var(--navy); margin-bottom:12px;
            display:flex; align-items:center; gap:6px;
        }

        /* Imagen ya subida */
        .prueba-existente {
            display:flex; align-items:center; gap:14px;
            background:rgba(142,207,192,0.1); border:1px solid rgba(142,207,192,0.4);
            border-radius:12px; padding:12px 14px; margin-bottom:12px;
        }
        .prueba-thumb {
            width:64px; height:64px; border-radius:10px; object-fit:cover;
            border:1.5px solid var(--border); cursor:pointer; flex-shrink:0;
            transition:transform 0.2s;
        }
        .prueba-thumb:hover { transform:scale(1.05); }
        .prueba-info { flex:1; }
        .prueba-info .pi-label { font-size:12px; font-weight:800; color:#1a6b53; margin-bottom:2px; }
        .prueba-info .pi-sub   { font-size:11px; color:var(--text-soft); }

        /* Formulario subir */
        .prueba-form { display:flex; flex-direction:column; gap:10px; }
        .file-drop {
            border:2px dashed var(--border); border-radius:12px; padding:18px 16px;
            text-align:center; background:var(--sky-pale); cursor:pointer;
            transition:border-color 0.2s, background 0.2s; position:relative;
        }
        .file-drop:hover { border-color:var(--sky); background:white; }
        .file-drop input[type=file] {
            position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%;
        }
        .file-drop .fd-icon { font-size:24px; margin-bottom:6px; }
        .file-drop .fd-text { font-size:13px; font-weight:700; color:var(--text-soft); }
        .file-drop .fd-sub  { font-size:11px; color:var(--text-soft); margin-top:2px; }
        .file-preview {
            width:100%; max-height:160px; object-fit:contain; border-radius:10px;
            border:1.5px solid var(--border); display:none; margin-top:6px;
        }
        .btn-subir-prueba {
            align-self:flex-end; padding:9px 22px;
            background:var(--mint); color:var(--navy); border:none;
            border-radius:10px; font-size:13px; font-weight:800;
            font-family:'Nunito',sans-serif; cursor:pointer; transition:all 0.2s;
        }
        .btn-subir-prueba:hover { background:#6dbfad; color:white; }
        .btn-subir-prueba:disabled { background:var(--border); color:var(--text-soft); cursor:not-allowed; }

        /* Completo banner */
        .completo-banner {
            display:flex; align-items:center; gap:8px;
            background:rgba(62,207,142,0.1); border:1px solid rgba(62,207,142,0.4);
            border-radius:10px; padding:10px 14px;
            font-size:13px; font-weight:700; color:#1a6b53; margin-bottom:14px;
        }

        /* Modal imagen grande */
        .modal-overlay {
            display:none; position:fixed; inset:0; background:rgba(44,62,107,0.7);
            z-index:500; align-items:center; justify-content:center; padding:20px;
            backdrop-filter:blur(4px);
        }
        .modal-overlay.active { display:flex; }
        .modal-img-wrap {
            position:relative; max-width:90vw; max-height:90vh;
            animation:modalIn 0.25s ease both;
        }
        .modal-img-wrap img { max-width:100%; max-height:85vh; border-radius:14px; display:block; }
        .modal-close-btn {
            position:absolute; top:-12px; right:-12px;
            background:white; border:none; border-radius:50%;
            width:32px; height:32px; font-size:16px; cursor:pointer;
            display:flex; align-items:center; justify-content:center;
            box-shadow:0 2px 8px rgba(0,0,0,0.2);
        }
        @keyframes modalIn {
            from { opacity:0; transform:scale(0.92); }
            to   { opacity:1; transform:scale(1); }
        }

        .empty-state { text-align:center; padding:80px 20px; color:var(--text-soft); }
        .empty-state .icon { font-size:56px; margin-bottom:16px; }
        .empty-state h3 { font-family:'DM Serif Display',serif; font-size:20px; color:var(--navy); margin-bottom:8px; }

        @keyframes fadeUp {
            from { opacity:0; transform:translateY(12px); }
            to   { opacity:1; transform:translateY(0); }
        }
        @media(max-width:600px) {
            .navbar { padding:0 16px; }
            .container { padding:20px 14px 48px; }
            .card-top { flex-direction:column; }
            .ref-img-wrap { width:100%; height:140px; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="navbar-left">
        <a href="../menu_tejedor.php" class="back-btn">← Menú</a>
        <span class="page-title">📋 Mis Asignaciones</span>
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

    <?php if (empty($asignaciones)): ?>
        <div class="empty-state">
            <div class="icon">🧵</div>
            <h3>Sin asignaciones por ahora</h3>
            <p>Cuando el administrador te asigne un pedido, aparecerá aquí.</p>
        </div>
    <?php else: ?>

    <div class="toolbar">
        <button class="filter-btn active" onclick="filtrar('todos', this)">Todos (<?php echo count($asignaciones); ?>)</button>
        <?php
            $urgentes  = array_filter($asignaciones, fn($a) => $a['prioridad'] === 'urgente');
            $pendientes = array_filter($asignaciones, fn($a) => (int)$a['progreso'] === 0);
            $enCurso   = array_filter($asignaciones, fn($a) => (int)$a['progreso'] > 0 && (int)$a['progreso'] < 100);
            $listos    = array_filter($asignaciones, fn($a) => (int)$a['progreso'] === 100);
        ?>
        <?php if (count($urgentes)):  ?><button class="filter-btn" onclick="filtrar('urgente', this)">⚠ Urgentes (<?php echo count($urgentes); ?>)</button><?php endif; ?>
        <?php if (count($pendientes)):?><button class="filter-btn" onclick="filtrar('pendiente', this)">⏳ Sin iniciar (<?php echo count($pendientes); ?>)</button><?php endif; ?>
        <?php if (count($enCurso)):   ?><button class="filter-btn" onclick="filtrar('en_curso', this)">🔄 En curso (<?php echo count($enCurso); ?>)</button><?php endif; ?>
        <?php if (count($listos)):    ?><button class="filter-btn" onclick="filtrar('listo', this)">✅ Listos (<?php echo count($listos); ?>)</button><?php endif; ?>
    </div>

    <div class="cards-grid" id="cardsGrid">
        <?php foreach ($asignaciones as $i => $a):
            $prog     = (int)$a['progreso'];
            $dias     = diasRestantes($a['fecha_entrega']);
            $prio     = $a['prioridad'];
            $completo = $prog === 100;
            $tienePrueba = !empty($a['imagen_prueba']);

            $cardClass = $completo ? 'completo' : ($prio === 'urgente' ? 'urgente' : ($prio === 'alta' ? 'alta' : 'normal'));
            $progClass = $completo ? 'pct-done' : ($prog >= 70 ? 'pct-hi' : ($prog >= 40 ? 'pct-mid' : ($prog > 0 ? 'pct-low' : 'pct-0')));

            $diasClass = ''; $diasTxt = '—';
            if ($dias !== null) {
                if ($dias < 0)       { $diasClass = 'dias-vencido';  $diasTxt = abs($dias) . ' día(s) vencido'; }
                elseif ($dias === 0) { $diasClass = 'dias-critical'; $diasTxt = '¡Entrega hoy!'; }
                elseif ($dias <= 3)  { $diasClass = 'dias-critical'; $diasTxt = $dias . ' día(s)'; }
                elseif ($dias <= 7)  { $diasClass = 'dias-warning';  $diasTxt = $dias . ' día(s)'; }
                else                 { $diasClass = 'dias-ok';       $diasTxt = $dias . ' día(s)'; }
            }

            $imgSrc = null;
            if (!empty($a['imagen_referencia']))   $imgSrc = '../../' . $a['imagen_referencia'];
            elseif (!empty($a['producto_imagen'])) $imgSrc = '../../' . $a['producto_imagen'];

            $filterData = 'todos ' . ($completo ? 'listo' : ($prog > 0 ? 'en_curso' : 'pendiente')) . ' ' . $prio;
        ?>
        <div class="asig-card <?php echo $cardClass; ?>" data-filter="<?php echo $filterData; ?>"
             style="animation-delay:<?php echo $i * 0.07; ?>s">

            <div class="card-top">
                <div class="ref-img-wrap">
                    <?php if ($imgSrc): ?>
                        <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="Referencia">
                    <?php else: ?>🧶<?php endif; ?>
                </div>
                <div class="card-info">
                    <div class="card-pedido-title"><?php echo htmlspecialchars($a['pedido_descripcion']); ?></div>
                    <div class="card-meta">
                        <span class="badge badge-tipo">
                            <?php echo $a['pedido_tipo'] === 'estandar' ? '📦 Estándar' : '✨ Personalizado'; ?>
                        </span>
                        <?php if ($prio === 'urgente'): ?>
                            <span class="badge badge-urgente">⚠ Urgente</span>
                        <?php elseif ($prio === 'alta'): ?>
                            <span class="badge badge-alta">Alta</span>
                        <?php else: ?>
                            <span class="badge badge-normal">Normal</span>
                        <?php endif; ?>
                        <?php if ($completo): ?><span class="badge badge-completo">✓ Listo</span><?php endif; ?>
                        <?php if ($tienePrueba): ?><span class="badge badge-prueba">📷 Prueba enviada</span><?php endif; ?>
                    </div>
                    <div class="dias-wrap">
                        📅
                        <?php if ($a['fecha_entrega']): ?>
                            <span><?php echo date('d/m/Y', strtotime($a['fecha_entrega'])); ?></span>
                            <span style="color:var(--border)">·</span>
                            <span class="<?php echo $diasClass; ?>"><?php echo $diasTxt; ?></span>
                        <?php else: ?>
                            <span style="color:var(--text-soft)">Sin fecha límite</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($a['notas'])): ?>
            <div class="card-notas">
                <strong>📝 Notas del administrador</strong><br>
                <?php echo nl2br(htmlspecialchars($a['notas'])); ?>
            </div>
            <?php endif; ?>

            <!-- PROGRESO -->
            <div class="card-progress">
                <div class="prog-header">
                    <span class="prog-label">Mi progreso</span>
                    <span class="prog-pct" id="pct-display-<?php echo $a['id']; ?>"><?php echo $prog; ?>%</span>
                </div>
                <div class="prog-bar-bg <?php echo $progClass; ?>" id="bar-wrap-<?php echo $a['id']; ?>">
                    <div class="prog-bar" id="bar-<?php echo $a['id']; ?>" style="width:<?php echo $prog; ?>%"></div>
                </div>

                <?php if ($completo): ?>
                <div class="completo-banner">
                    ✅ ¡Terminado! El administrador revisará y actualizará el estado del pedido.
                </div>
                <?php else: ?>
                <form action="../../controlador/asignaciones_ctrl.php" method="POST" class="slider-form">
<?php echo campoCsrf(); ?>
                    <input type="hidden" name="accion" value="actualizar_progreso">
                    <input type="hidden" name="asignacion_id" value="<?php echo $a['id']; ?>">
                    <div class="slider-row">
                        <input type="range" name="progreso" id="slider-<?php echo $a['id']; ?>"
                               min="0" max="100" step="5" value="<?php echo $prog; ?>"
                               oninput="actualizarBarra(<?php echo $a['id']; ?>, this.value)">
                        <span class="slider-val" id="slider-val-<?php echo $a['id']; ?>"><?php echo $prog; ?>%</span>
                    </div>
                    <button type="submit" class="btn-guardar" id="btn-<?php echo $a['id']; ?>" disabled>
                        Guardar progreso
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <!-- IMAGEN DE PRUEBA — visible siempre que progreso >= 50 -->
            <?php if ($prog >= 50): ?>
            <div class="prueba-section">
                <div class="prueba-title">
                    📷 Imagen de prueba
                    <?php if ($tienePrueba): ?>
                        <span style="color:#1a6b53;font-size:11px;font-weight:700;">✓ Enviada</span>
                    <?php endif; ?>
                </div>

                <?php if ($tienePrueba): ?>
                <!-- Imagen ya subida -->
                <div class="prueba-existente">
                    <img src="../../<?php echo htmlspecialchars($a['imagen_prueba']); ?>"
                         class="prueba-thumb" alt="Imagen de prueba"
                         onclick="verImagen('../../<?php echo htmlspecialchars($a['imagen_prueba']); ?>')">
                    <div class="prueba-info">
                        <div class="pi-label">✅ Imagen enviada al administrador</div>
                        <div class="pi-sub">Haz clic en la imagen para verla en grande.<br>Puedes reemplazarla subiendo una nueva.</div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Formulario subir nueva imagen -->
                <form action="../../controlador/asignaciones_ctrl.php" method="POST"
                      enctype="multipart/form-data" class="prueba-form">
<?php echo campoCsrf(); ?>
                    <input type="hidden" name="accion" value="subir_prueba">
                    <input type="hidden" name="asignacion_id" value="<?php echo $a['id']; ?>">
                    <div class="file-drop" id="drop-<?php echo $a['id']; ?>">
                        <input type="file" name="imagen_prueba" id="file-<?php echo $a['id']; ?>"
                               accept="image/jpeg,image/png,image/webp"
                               onchange="previewPrueba(<?php echo $a['id']; ?>, this)">
                        <div class="fd-icon">📸</div>
                        <div class="fd-text"><?php echo $tienePrueba ? 'Reemplazar imagen' : 'Subir foto del producto terminado'; ?></div>
                        <div class="fd-sub">JPG, PNG o WEBP · Máx 8MB</div>
                    </div>
                    <img id="preview-<?php echo $a['id']; ?>" class="file-preview" src="" alt="">
                    <button type="submit" class="btn-subir-prueba" id="btn-prueba-<?php echo $a['id']; ?>" disabled>
                        📤 Enviar imagen de prueba
                    </button>
                </form>
            </div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal ver imagen grande -->
<div class="modal-overlay" id="modalImg" onclick="cerrarModal()">
    <div class="modal-img-wrap" onclick="event.stopPropagation()">
        <img id="modalImgSrc" src="" alt="Imagen de prueba">
        <button class="modal-close-btn" onclick="cerrarModal()">✕</button>
    </div>
</div>

<script>
    const progresosOriginales = {
        <?php foreach ($asignaciones as $a): ?>
        <?php echo $a['id']; ?>: <?php echo (int)$a['progreso']; ?>,
        <?php endforeach; ?>
    };

    function actualizarBarra(id, valor) {
        valor = parseInt(valor);
        document.getElementById('pct-display-' + id).textContent = valor + '%';
        document.getElementById('slider-val-' + id).textContent  = valor + '%';
        document.getElementById('bar-' + id).style.width = valor + '%';
        const wrap = document.getElementById('bar-wrap-' + id);
        wrap.className = 'prog-bar-bg ' + (
            valor === 100 ? 'pct-done' : valor >= 70 ? 'pct-hi' :
            valor >= 40 ? 'pct-mid' : valor > 0 ? 'pct-low' : 'pct-0'
        );
        const btn = document.getElementById('btn-' + id);
        if (btn) btn.disabled = (valor === progresosOriginales[id]);
    }

    function previewPrueba(id, input) {
        const file = input.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.getElementById('preview-' + id);
            img.src = e.target.result;
            img.style.display = 'block';
        };
        reader.readAsDataURL(file);
        document.getElementById('btn-prueba-' + id).disabled = false;
    }

    function verImagen(src) {
        document.getElementById('modalImgSrc').src = src;
        document.getElementById('modalImg').classList.add('active');
    }

    function cerrarModal() {
        document.getElementById('modalImg').classList.remove('active');
    }

    function filtrar(tipo, btn) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.asig-card').forEach(card => {
            const filtros = card.dataset.filter.split(' ');
            card.style.display = (tipo === 'todos' || filtros.includes(tipo)) ? '' : 'none';
        });
    }
</script>
</body>
</html>