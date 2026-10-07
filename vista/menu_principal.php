<?php
// vista/menu_principal.php
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../modelo/autorizacion.php';

// Solo quien tenga acceso al dashboard ve este panel
requierePermiso('dashboard', 'lectura');

$nombre  = $_SESSION['nombre'];
$usuario = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrochetLab — Panel Principal</title>
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
            --cream:      #FFF9F4;
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
                radial-gradient(circle at 0% 0%,   rgba(122,191,204,0.15) 0%, transparent 45%),
                radial-gradient(circle at 100% 100%, rgba(201,184,232,0.12) 0%, transparent 45%);
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

        /* Rainbow stripe at top of navbar */
        .navbar::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg,
                var(--coral)    0%,
                var(--yellow)   25%,
                var(--lavender) 50%,
                var(--mint)     75%,
                var(--sky)      100%
            );
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .navbar-brand img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }

        .navbar-brand span {
            font-family: 'DM Serif Display', serif;
            font-size: 22px;
            color: var(--navy);
        }

        .navbar-brand span em {
            color: var(--coral);
            font-style: normal;
        }

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
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--sky), var(--lavender));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
            color: var(--white);
            flex-shrink: 0;
        }

        .user-info { line-height: 1.2; }
        .user-name { font-size: 13px; font-weight: 800; color: var(--navy); }
        .user-role { font-size: 11px; color: var(--text-soft); }

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
            text-decoration: none;
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

        /* Mobile drawer */
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 36px 24px 60px;
        }

        /* Welcome banner */
        .welcome-banner {
            background: var(--navy);
            border-radius: 18px;
            padding: 40px 48px;
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
            content: '🧶';
            position: absolute;
            right: 40px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 100px;
            opacity: 0.08;
            pointer-events: none;
        }

        .welcome-banner::after {
            content: '';
            position: absolute;
            bottom: -30px; left: -30px;
            width: 160px; height: 160px;
            border-radius: 50%;
            background: rgba(122,191,204,0.12);
        }

        .welcome-text h1 {
            font-family: 'DM Serif Display', serif;
            font-size: 32px;
            color: white;
            margin-bottom: 6px;
        }

        .welcome-text h1 span { color: var(--yellow); }

        .welcome-text p {
            font-size: 15px;
            color: rgba(255,255,255,0.65);
            font-style: italic;
        }

        .welcome-badge {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 12px;
            padding: 14px 20px;
            text-align: center;
            flex-shrink: 0;
            backdrop-filter: blur(4px);
        }

        .welcome-badge .date {
            font-size: 11px;
            color: rgba(255,255,255,0.6);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 4px;
        }

        .welcome-badge .date-val {
            font-size: 15px;
            font-weight: 800;
            color: white;
        }

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

        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 32px rgba(44,62,107,0.12);
        }

        .menu-card:hover::before { opacity: 1; }

        /* Card accent colors */
        .card-dashboard::before  { background: linear-gradient(90deg, var(--sky), var(--mint)); }
        .card-catalogo::before   { background: linear-gradient(90deg, var(--lavender), var(--sky)); }
        .card-pedidos::before    { background: linear-gradient(90deg, var(--coral), var(--yellow)); }
        .card-empleados::before  { background: linear-gradient(90deg, var(--mint), var(--sky)); }
        .card-asignaciones::before { background: linear-gradient(90deg, var(--yellow), var(--coral)); }
        .card-reportes::before   { background: linear-gradient(90deg, var(--navy-light), var(--sky)); }
        .card-config::before     { background: linear-gradient(90deg, var(--border), var(--sky-light)); }

        .card-dashboard:hover  { border-color: var(--sky); }
        .card-catalogo:hover   { border-color: var(--lavender); }
        .card-pedidos:hover    { border-color: var(--coral); }
        .card-empleados:hover  { border-color: var(--mint); }
        .card-asignaciones:hover { border-color: var(--yellow); }
        .card-reportes:hover   { border-color: var(--navy-light); }
        .card-config:hover     { border-color: var(--sky-light); }

        .card-icon-wrap {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin-bottom: 16px;
        }

        .card-dashboard  .card-icon-wrap { background: rgba(122,191,204,0.15); }
        .card-catalogo   .card-icon-wrap { background: rgba(201,184,232,0.15); }
        .card-pedidos    .card-icon-wrap { background: rgba(242,144,122,0.15); }
        .card-empleados  .card-icon-wrap { background: rgba(142,207,192,0.15); }
        .card-asignaciones .card-icon-wrap { background: rgba(247,206,122,0.15); }
        .card-reportes   .card-icon-wrap { background: rgba(44,62,107,0.10); }
        .card-config     .card-icon-wrap { background: rgba(170,216,226,0.15); }

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
        }

        /* "Crítico" badge for pedidos */
        .badge-critical {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(242,144,122,0.15);
            color: var(--coral-dark);
            border: 1px solid rgba(242,144,122,0.35);
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.06em;
            padding: 3px 9px;
            margin-top: 10px;
            text-transform: uppercase;
        }

        /* ── FOOTER ── */
        .footer {
            text-align: center;
            padding: 28px 20px;
            color: var(--text-soft);
            font-size: 13px;
            border-top: 1px solid var(--border);
            margin-top: 20px;
        }

        .footer-dots {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-bottom: 10px;
        }

        .footer-dots span {
            width: 6px; height: 6px;
            border-radius: 50%;
        }

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
            .welcome-badge { align-self: flex-start; }
            .welcome-text h1 { font-size: 24px; }

            .menu-grid { grid-template-columns: 1fr 1fr; gap: 14px; }
            .container { padding: 24px 16px 48px; }
        }

        @media (max-width: 480px) {
            .menu-grid { grid-template-columns: 1fr; }
            .welcome-text h1 { font-size: 20px; }
        }
    </style>
</head>
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
                    <div class="user-role">@<?php echo htmlspecialchars($usuario); ?></div>
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
                <div class="user-role">@<?php echo htmlspecialchars($usuario); ?></div>
            </div>
        </div>
        <a href="mi_cuenta.php" class="btn-logout" style="text-decoration:none;display:inline-block;margin-right:8px;">Mi cuenta</a>
            <form action="../controlador/validar_usuario.php" method="POST" style="margin:0;">
<?php echo campoCsrf(); ?>
            <input type="hidden" name="accion" value="logout">
            <button type="submit" class="btn-logout" style="width:100%;">Cerrar sesión</button>
        </form>
    </div>

    <!-- MAIN -->
    <div class="container">

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="welcome-text">
                <h1>¡Hola, <span><?php echo htmlspecialchars($nombre); ?></span>! 👋</h1>
                <p>Bienvenido al panel de gestión interna de CrochetLab</p>
            </div>
            <div class="welcome-badge">
                <div class="date">Hoy</div>
                <div class="date-val"><?php echo date('d M Y'); ?></div>
            </div>
        </div>

        <!-- Menu -->
        <div class="section-title">Módulos del sistema</div>

        <div class="menu-grid">

            <!-- Dashboard -->
            <a href="dashboard.php" class="menu-card card-dashboard">
                <div class="card-icon-wrap">📊</div>
                <div class="card-title">Dashboard</div>
                <div class="card-desc">Resumen general de productividad, pedidos activos y alertas del día.</div>
            </a>

            <!-- Catálogo -->
            <a href="catalogo/index_catalogo.php" class="menu-card card-catalogo">
                <div class="card-icon-wrap">🧶</div>
                <div class="card-title">Catálogo</div>
                <div class="card-desc">Productos fijos de CrochetLab: bolsos, ropa, amigurumis y accesorios. Control de stock mínimo.</div>
            </a>

            <!-- Pedidos -->
            <a href="pedidos/index_pedidos.php" class="menu-card card-pedidos">
                <div class="card-icon-wrap">📦</div>
                <div class="card-title">Pedidos</div>
                <div class="card-desc">Gestión de pedidos estándar y personalizados. Seguimiento de fechas límite de entrega.</div>
                <div class="badge-critical">⚠ Seguimiento crítico</div>
            </a>

            <!-- Empleados -->
            <a href="empleados/index_empleados.php" class="menu-card card-empleados">
                <div class="card-icon-wrap">👷</div>
                <div class="card-title">Empleados</div>
                <div class="card-desc">Registro del equipo, historial de trabajo y seguimiento de productividad individual.</div>
            </a>

            <!-- Asignaciones -->
            <a href="asignaciones/index_asignaciones.php" class="menu-card card-asignaciones">
                <div class="card-icon-wrap">📋</div>
                <div class="card-title">Asignaciones</div>
                <div class="card-desc">Asigna pedidos y tareas a empleados. Controla quién hace qué y en qué plazo.</div>
            </a>

            <!-- Reportes -->
            <a href="reportes/index_reportes.php" class="menu-card card-reportes">
                <div class="card-icon-wrap">📈</div>
                <div class="card-title">Reportes</div>
                <div class="card-desc">Reportes de productividad por empleado, pedidos completados, retrasados y tendencias.</div>
            </a>

            <a href="patrones/index_patrones.php" class="menu-card card-patrones">
                <div class="card-icon-wrap">📐</div>
                <div class="card-title">Patrones</div>
                <div class="card-desc">Gestiona los patrones del catálogo y revisa contribuciones de tejedores.</div>
            </a>

            <!-- Configuración -->
            <a href="permisos_acceso.php" class="menu-card card-config">
                <div class="card-icon-wrap">🔐</div>
                <div class="card-title">Permisos y Acceso</div>
                <div class="card-desc">Cambia tu contraseña y gestiona los roles de administrador del sistema.</div>
            </a>

            <?php if (tienePermiso('usuarios', 'lectura')): ?>
            <!-- Usuarios -->
            <a href="usuarios.php" class="menu-card card-config">
                <div class="card-icon-wrap">👥</div>
                <div class="card-title">Usuarios</div>
                <div class="card-desc">Todas las cuentas registradas: asigna o revoca roles, activa, desactiva y desbloquea.</div>
            </a>
            <?php endif; ?>

            <?php if (tienePermiso('roles', 'lectura')): ?>
            <!-- Roles y permisos -->
            <a href="roles.php" class="menu-card card-config">
                <div class="card-icon-wrap">🛡️</div>
                <div class="card-title">Roles y Permisos</div>
                <div class="card-desc">Crea roles y define qué puede leer, escribir o eliminar cada uno en cada área.</div>
            </a>
            <?php endif; ?>

            <?php if (tienePermiso('auditoria', 'lectura')): ?>
            <!-- Auditoría y Accesos -->
            <a href="auditoria.php" class="menu-card card-config">
                <div class="card-icon-wrap">🧾</div>
                <div class="card-title">Auditoría y Accesos</div>
                <div class="card-desc">Historial de inicios de sesión (con IP y fallos) y registro de las acciones de los usuarios.</div>
            </a>
            <?php endif; ?>

        </div>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        <div class="footer-dots">
            <span style="background:var(--coral)"></span>
            <span style="background:var(--yellow)"></span>
            <span style="background:var(--mint)"></span>
            <span style="background:var(--lavender)"></span>
            <span style="background:var(--sky)"></span>
        </div>
        &copy; <?php echo date('Y'); ?> CrochetLab — Sistema de Gestión Interna
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