<?php
// vista/menu_usuario.php
// Panel del cliente (rol "Usuario Regular"): consulta el catálogo y entra a "Mis pedidos".
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/autorizacion.php';
requiereSesion();
$veCatalogo = tienePermiso('contenido', 'lectura');
$vePedidos  = tienePermiso('mis_pedidos', 'lectura');
if (!$veCatalogo && !$vePedidos) {
    denegarAcceso('contenido', 'lectura');
}

$nombre  = $_SESSION['nombre'];
$usuario = $_SESSION['usuario'];

$db = (new Conexion())->conectar();

$productos = !$veCatalogo ? [] : $db->query(
    "SELECT c.id, c.nombre, c.descripcion, c.precio, c.imagen_ruta, cat.nombre AS categoria
     FROM catalogo c
     LEFT JOIN categorias cat ON cat.id = c.categoria_id
     WHERE c.activo = TRUE
     ORDER BY cat.nombre ASC NULLS LAST, c.nombre ASC"
)->fetchAll();
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
            --sky:        #7ABFCC;
            --sky-light:  #aad8e2;
            --sky-pale:   #e8f5f8;
            --navy:       #2C3E6B;
            --navy-light: #3d5491;
            --coral:      #F2907A;
            --coral-dark: #d97060;
            --lavender:   #C9B8E8;
            --mint:       #8ECFC0;
            --yellow:     #F7CE7A;
            --white:      #ffffff;
            --text-main:  #2C3E6B;
            --text-soft:  #6a7fa8;
            --border:     #daedf2;
            --bg:         #f0f8fa;
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Nunito', sans-serif;
            background-color: var(--bg);
            background-image:
                radial-gradient(circle at 0% 0%,    rgba(142,207,192,0.15) 0%, transparent 45%),
                radial-gradient(circle at 100% 100%, rgba(247,206,122,0.12) 0%, transparent 45%);
            min-height: 100vh;
            color: var(--text-main);
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
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg,
                var(--coral) 0%, var(--yellow) 25%,
                var(--lavender) 50%, var(--mint) 75%, var(--sky) 100%
            );
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .navbar-brand img {
            width: 36px; height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }

        .navbar-brand span {
            font-family: 'DM Serif Display', serif;
            font-size: 22px;
            color: var(--navy);
        }

        .navbar-brand span em { color: var(--coral); font-style: normal; }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--sky-pale);
            border: 1px solid var(--border);
            border-radius: 50px;
            padding: 6px 14px 6px 8px;
        }

        .user-avatar {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--mint), var(--yellow));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
            color: var(--navy);
            flex-shrink: 0;
        }

        .user-info { line-height: 1.2; }
        .user-name { font-size: 13px; font-weight: 800; color: var(--navy); }
        .user-role {
            font-size: 11px;
            color: var(--white);
            background: var(--mint);
            border-radius: 20px;
            padding: 1px 7px;
            display: inline-block;
            font-weight: 700;
        }

        .btn-logout {
            background: transparent;
            border: 1.5px solid var(--navy);
            color: var(--navy);
            padding: 8px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            font-family: 'Nunito', sans-serif;
            transition: all 0.2s;
        }

        .btn-logout:hover {
            background: var(--coral);
            border-color: var(--coral);
            color: white;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--navy);
            cursor: pointer;
            padding: 4px;
        }

        .mobile-drawer {
            display: none;
            position: fixed;
            top: 64px; right: 0;
            background: white;
            border-radius: 12px 0 0 12px;
            box-shadow: -4px 4px 20px rgba(44,62,107,0.15);
            padding: 20px;
            z-index: 199;
            min-width: 220px;
            flex-direction: column;
            gap: 14px;
        }

        .mobile-drawer.active { display: flex; }

        /* ── MAIN ── */
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 36px 24px 60px;
        }

        /* Welcome banner — tono más cálido para tejedores */
        .welcome-banner {
            background: linear-gradient(135deg, var(--mint) 0%, var(--sky) 100%);
            border-radius: 18px;
            padding: 36px 48px;
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            position: relative;
            overflow: hidden;
            animation: fadeUp 0.5s ease both;
        }

        .welcome-banner::before {
            content: '🧵';
            position: absolute;
            right: 40px; top: 50%;
            transform: translateY(-50%);
            font-size: 90px;
            opacity: 0.12;
            pointer-events: none;
        }

        .welcome-text h1 {
            font-family: 'DM Serif Display', serif;
            font-size: 28px;
            color: var(--navy);
            margin-bottom: 6px;
        }

        .welcome-text h1 span { color: var(--white); }

        .welcome-text p {
            font-size: 14px;
            color: rgba(44,62,107,0.7);
            font-style: italic;
        }

        .welcome-badge {
            background: rgba(255,255,255,0.45);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 12px;
            padding: 14px 20px;
            text-align: center;
            flex-shrink: 0;
        }

        .welcome-badge .date { font-size: 11px; color: var(--navy); opacity: 0.6; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px; }
        .welcome-badge .date-val { font-size: 15px; font-weight: 800; color: var(--navy); }

        /* Section title */
        .section-title {
            font-family: 'DM Serif Display', serif;
            font-size: 20px;
            color: var(--navy);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* ── MENU GRID ── */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
            animation: fadeUp 0.6s ease 0.1s both;
        }

        .menu-card {
            background: var(--white);
            border-radius: 16px;
            padding: 28px 26px;
            border: 1.5px solid var(--border);
            text-decoration: none;
            color: inherit;
            display: block;
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .menu-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            border-radius: 16px 16px 0 0;
            opacity: 0;
            transition: opacity 0.25s;
        }

        .menu-card:hover { transform: translateY(-5px); box-shadow: 0 12px 32px rgba(44,62,107,0.12); }
        .menu-card:hover::before { opacity: 1; }

        .card-asignaciones::before { background: linear-gradient(90deg, var(--coral), var(--yellow)); }
        .card-patrones::before     { background: linear-gradient(90deg, var(--lavender), var(--sky)); }
        .card-progreso::before     { background: linear-gradient(90deg, var(--mint), var(--sky)); }

        .card-asignaciones:hover { border-color: var(--coral); }
        .card-patrones:hover     { border-color: var(--lavender); }
        .card-progreso:hover     { border-color: var(--mint); }

        .card-icon-wrap {
            width: 52px; height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin-bottom: 16px;
        }

        .card-asignaciones .card-icon-wrap { background: rgba(242,144,122,0.15); }
        .card-patrones     .card-icon-wrap { background: rgba(201,184,232,0.15); }
        .card-progreso     .card-icon-wrap { background: rgba(142,207,192,0.15); }

        .card-title {
            font-family: 'DM Serif Display', serif;
            font-size: 19px;
            color: var(--navy);
            margin-bottom: 6px;
        }

        .card-desc {
            font-size: 13.5px;
            color: var(--text-soft);
            line-height: 1.55;
            margin-bottom: 14px;
        }

        /* Status chips dentro de las tarjetas */
        .chip-list { display: flex; flex-wrap: wrap; gap: 6px; }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border: 1px solid;
        }

        .chip-pending  { background: rgba(247,206,122,0.2); color: #9a7000; border-color: rgba(247,206,122,0.5); }
        .chip-progress { background: rgba(122,191,204,0.2); color: #1a6b7a; border-color: rgba(122,191,204,0.5); }
        .chip-done     { background: rgba(142,207,192,0.2); color: #1a6b53; border-color: rgba(142,207,192,0.5); }
        .chip-critical { background: rgba(242,144,122,0.2); color: #b94030; border-color: rgba(242,144,122,0.5); }

        /* ── FOOTER ── */
        .footer {
            text-align: center;
            padding: 28px 20px;
            color: var(--text-soft);
            font-size: 13px;
            border-top: 1px solid var(--border);
            margin-top: 20px;
        }

        .footer-dots { display: flex; justify-content: center; gap: 6px; margin-bottom: 10px; }
        .footer-dots span { width: 6px; height: 6px; border-radius: 50%; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .navbar { padding: 0 20px; }
            .navbar-right .user-pill,
            .navbar-right .btn-logout { display: none; }
            .menu-toggle { display: block; }
            .welcome-banner { padding: 28px 24px; flex-direction: column; align-items: flex-start; }
            .welcome-banner::before { font-size: 60px; top: 20px; right: 20px; transform: none; }
            .menu-grid { grid-template-columns: 1fr 1fr; gap: 14px; }
            .container { padding: 24px 16px 48px; }
        }

        @media (max-width: 480px) {
            .menu-grid { grid-template-columns: 1fr; }
            .welcome-text h1 { font-size: 22px; }
        }
    

        /* ── Solo lectura: tarjetas de contenido ── */
        .prod-card { background: var(--white); border: 1.5px solid var(--border); border-radius: 16px; overflow: hidden; }
        .prod-img { width: 100%; height: 170px; object-fit: cover; display: block; background: var(--sky-pale); }
        .prod-img-empty { width: 100%; height: 170px; display: flex; align-items: center; justify-content: center; font-size: 42px; background: var(--sky-pale); }
        .prod-body { padding: 16px 18px 20px; }
        .prod-cat { font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--text-soft); }
        .prod-name { font-family: 'DM Serif Display', serif; font-size: 18px; margin: 4px 0 6px; }
        .prod-desc { font-size: 13.5px; color: var(--text-soft); line-height: 1.5; }
        .prod-price { margin-top: 10px; font-weight: 800; color: var(--coral-dark); }
        .cta { display:inline-block; margin-top:12px; padding:9px 16px; border-radius:10px; background:var(--coral); color:#fff; font-weight:800; font-size:13.5px; text-decoration:none; }
        .cta:hover { background:var(--coral-dark); }
        .cta.grande { padding:12px 22px; font-size:15px; margin:0 0 24px; }
        .vacio { color: var(--text-soft); padding: 8px 2px 24px; }
        .patron-detalle summary { cursor: pointer; font-weight: 700; color: var(--navy-light); margin-top: 10px; }
        .patron-detalle p { white-space: pre-line; font-size: 13.5px; color: var(--text-soft); margin-top: 8px; line-height: 1.55; }
    </style>
<body>

    <!-- NAVBAR -->
    <nav class="navbar">
        <a class="navbar-brand" href="#">
            <img src="../assets/img/logo.png" alt="CrochetLab">
            <span>Crochet<em>Lab</em></span>
        </a>

        <button class="menu-toggle" onclick="toggleDrawer()">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        <div class="navbar-right">
            <div class="user-pill">
                <div class="user-avatar"><?php echo strtoupper(substr($nombre, 0, 1)); ?></div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($nombre); ?></div>
                    <div class="user-role">👤 <?php echo htmlspecialchars($_SESSION['rol'] ?? 'Usuario'); ?></div>
                </div>
            </div>
            <a href="mi_cuenta.php" class="btn-logout" style="text-decoration:none;display:inline-block;margin-right:8px;">Mi cuenta</a>
            <form action="../controlador/validar_usuario.php" method="POST" style="margin:0;">
<?php echo campoCsrf(); ?>
                <input type="hidden" name="accion" value="logout">
                <button type="submit" class="btn-logout">Cerrar sesión</button>
            </form>
        </div>
    </nav>

    <!-- MOBILE DRAWER -->
    <div class="mobile-drawer" id="mobileDrawer">
        <div class="user-pill">
            <div class="user-avatar"><?php echo strtoupper(substr($nombre, 0, 1)); ?></div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($nombre); ?></div>
                <div class="user-role">👤 <?php echo htmlspecialchars($_SESSION['rol'] ?? 'Usuario'); ?></div>
            </div>
        </div>
        <a href="mi_cuenta.php" class="btn-logout" style="text-decoration:none;display:inline-block;margin-right:8px;">Mi cuenta</a>
            <form action="../controlador/validar_usuario.php" method="POST" style="margin:0;">
<?php echo campoCsrf(); ?>
            <input type="hidden" name="accion" value="logout">
            <button type="submit" class="btn-logout" style="width:100%;">Cerrar sesión</button>
        </form>
    </div>

    <div class="container">

        <div class="welcome-banner">
            <div class="welcome-text">
                <h1>¡Hola, <span><?php echo htmlspecialchars($nombre); ?></span>! 🧶</h1>
                <p>Explora los productos de CrochetLab y haz tus pedidos.</p>
            </div>
            <div class="welcome-badge">
                <div class="date">Hoy</div>
                <div class="date-val"><?php echo date('d M Y'); ?></div>
            </div>
        </div>

        <?php if ($vePedidos): ?>
            <a class="cta grande" href="mis_pedidos.php">🧶 Mis pedidos / Hacer un pedido</a>
        <?php endif; ?>

        <?php if ($veCatalogo): ?>
        <div class="section-title">Catálogo</div>
        <?php if (!$productos): ?>
            <p class="vacio">Aún no hay productos disponibles.</p>
        <?php else: ?>
        <div class="menu-grid">
            <?php foreach ($productos as $p): ?>
            <div class="prod-card">
                <?php if (!empty($p['imagen_ruta'])): ?>
                    <img class="prod-img" src="../<?php echo htmlspecialchars($p['imagen_ruta']); ?>" alt="">
                <?php else: ?>
                    <div class="prod-img-empty">🧶</div>
                <?php endif; ?>
                <div class="prod-body">
                    <div class="prod-cat"><?php echo htmlspecialchars($p['categoria'] ?? 'Sin categoría'); ?></div>
                    <div class="prod-name"><?php echo htmlspecialchars($p['nombre']); ?></div>
                    <div class="prod-desc"><?php echo htmlspecialchars($p['descripcion'] ?? ''); ?></div>
                    <?php if ($p['precio'] !== null): ?>
                        <div class="prod-price">$<?php echo number_format((float)$p['precio'], 2); ?></div>
                    <?php endif; ?>
                    <?php if ($vePedidos): ?>
                        <a class="cta" href="mis_pedidos.php?producto=<?php echo (int)$p['id']; ?>">Pedir</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>

    </div>

    <div class="footer">
        <div class="footer-dots">
            <span style="background:var(--coral)"></span>
            <span style="background:var(--yellow)"></span>
            <span style="background:var(--mint)"></span>
            <span style="background:var(--lavender)"></span>
            <span style="background:var(--sky)"></span>
        </div>
        &copy; <?php echo date('Y'); ?> CrochetLab
    </div>

    <script>
        function toggleDrawer() {
            document.getElementById('mobileDrawer').classList.toggle('active');
        }
        document.addEventListener('click', function(e) {
            const drawer = document.getElementById('mobileDrawer');
            const toggle = document.querySelector('.menu-toggle');
            if (!drawer.contains(e.target) && !toggle.contains(e.target)) {
                drawer.classList.remove('active');
            }
        });
    </script>
</body>
</html>
