<?php
// index.php - Página principal de acceso al sistema
require_once 'config.php';

// Si ya hay sesión activa, redirigir al panel que le corresponde según sus permisos
require_once __DIR__ . '/modelo/autorizacion.php';
if (usuarioAutenticado()) {
    $destino = rutaInicio();
    if ($destino !== null) {
        header('Location: ' . $destino);
        exit();
    }
    // Sesión sin permisos (cuenta desactivada o rol vacío): se cierra y se muestra el login
    cerrarSesionLocal();
    session_start();
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrochetLab - Acceso</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,400;0,600;0,700;0,800;1,400&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">

    <style>
        :root {
            /* CrochetLab Brand Palette — extraída del logo */
            --sky:        #7ABFCC;   /* azul pizarra del logo */
            --sky-light:  #aad8e2;
            --sky-pale:   #e8f5f8;
            --navy:       #2C3E6B;   /* texto oscuro del logo */
            --coral:      #F2907A;   /* gancho / acento cálido */
            --coral-dark: #d97060;
            --lavender:   #C9B8E8;
            --mint:       #8ECFC0;
            --cream:      #FFF9F4;
            --cream-card: #ffffff;
            --text-main:  #2C3E6B;
            --text-soft:  #6a7fa8;
            --border:     #daedf2;

            --success-bg: #edfaf7;
            --success-text: #1a6b53;
            --error-bg:   #fef0ee;
            --error-text: #b94030;
        }

        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Nunito', sans-serif;
            background-color: var(--sky-pale);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            /* Subtle yarn-cross pattern inspired by the logo border */
            background-image:
                radial-gradient(circle at 15% 25%, rgba(122,191,204,0.18) 0%, transparent 50%),
                radial-gradient(circle at 85% 75%, rgba(201,184,232,0.18) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(142,207,192,0.10) 0%, transparent 70%);
        }

        /* ── Decorative background stitches ── */
        body::before {
            content: '× × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × × ×';
            position: fixed;
            inset: 0;
            font-size: 18px;
            line-height: 2.2;
            letter-spacing: 18px;
            color: rgba(122,191,204,0.18);
            pointer-events: none;
            word-break: break-all;
            overflow: hidden;
            z-index: 0;
        }

        /* ── Card ── */
        .container {
            position: relative;
            z-index: 1;
            background: var(--cream-card);
            border-radius: 20px;
            box-shadow:
                0 4px 6px rgba(44,62,107,0.06),
                0 20px 50px rgba(44,62,107,0.12);
            max-width: 440px;
            width: 100%;
            overflow: hidden;
            border: 1px solid var(--border);
            animation: floatIn 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes floatIn {
            from { opacity: 0; transform: translateY(24px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* ── Top rainbow stripe (mimics logo border colors) ── */
        .stripe {
            height: 6px;
            background: linear-gradient(90deg,
                var(--coral)    0%,
                #F7CE7A         25%,
                var(--lavender) 50%,
                var(--mint)     75%,
                var(--sky)      100%
            );
        }

        /* ── Logo / Header ── */
        .header {
            padding: 32px 40px 16px;
            text-align: center;
        }

        .logo-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .logo-wrap img {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 4px 18px rgba(122,191,204,0.35);
            transition: transform 0.4s ease;
        }
        .logo-wrap img:hover {
            transform: rotate(-6deg) scale(1.05);
        }

        .header h1 {
            font-family: 'DM Serif Display', serif;
            font-size: 30px;
            color: var(--navy);
            letter-spacing: -0.02em;
            margin-bottom: 4px;
        }

        .header h1 span {
            color: var(--coral);
        }

        .header p {
            font-size: 13.5px;
            color: var(--text-soft);
            font-style: italic;
            font-weight: 400;
        }

        /* ── Tabs ── */
        .tabs {
            display: flex;
            margin: 0 40px;
            border-bottom: 2px solid var(--border);
            gap: 4px;
        }

        .tab {
            flex: 1;
            padding: 11px 10px;
            text-align: center;
            cursor: pointer;
            border: none;
            background: transparent;
            color: var(--text-soft);
            font-size: 14px;
            font-weight: 700;
            font-family: 'Nunito', sans-serif;
            transition: color 0.2s ease;
            position: relative;
        }

        .tab::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--coral);
            border-radius: 2px 2px 0 0;
            transform: scaleX(0);
            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .tab:hover { color: var(--navy); }
        .tab.active { color: var(--coral); }
        .tab.active::after { transform: scaleX(1); }

        /* ── Forms ── */
        .form-container {
            padding: 28px 40px 36px;
        }

        .form-content {
            display: none;
            animation: fadeSlide 0.3s ease both;
        }
        .form-content.active { display: block; }

        @keyframes fadeSlide {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            color: var(--navy);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        input {
            width: 100%;
            padding: 13px 16px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Nunito', sans-serif;
            background: var(--sky-pale);
            color: var(--text-main);
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }

        input::placeholder { color: #b0c4cc; }

        input:focus {
            outline: none;
            border-color: var(--sky);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(122,191,204,0.18);
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            background: none;
            border: none;
            color: var(--text-soft);
            padding: 0;
            display: flex;
            align-items: center;
            transition: color 0.2s;
        }
        .toggle-password:hover { color: var(--coral); }

        /* ── Button ── */
        .btn {
            width: 100%;
            padding: 14px;
            background: var(--navy);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 800;
            font-family: 'Nunito', sans-serif;
            cursor: pointer;
            letter-spacing: 0.04em;
            transition: background 0.2s, transform 0.12s, box-shadow 0.2s;
            box-shadow: 0 4px 14px rgba(44,62,107,0.22);
        }

        .btn:hover {
            background: var(--coral);
            box-shadow: 0 6px 18px rgba(242,144,122,0.30);
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
        }

        /* ── Social login ── */
        .social-login {
            margin-top: 24px;
        }

        .social-separator {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-soft);
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 14px;
        }

        .social-separator::before,
        .social-separator::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .social-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .social-btn {
            flex: 1;
            padding: 11px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            background: var(--sky-pale);
            color: var(--text-soft);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-family: 'Nunito', sans-serif;
        }

        .social-btn:hover {
            border-color: var(--sky);
            background: #d4edf3;
            color: var(--navy);
            transform: translateY(-2px);
        }

        /* ── Messages ── */
        .mensaje {
            padding: 13px 16px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 14px;
            font-weight: 600;
        }

        .mensaje.success {
            background: var(--success-bg);
            color: var(--success-text);
            border-left: 4px solid var(--mint);
        }

        .mensaje.error {
            background: var(--error-bg);
            color: var(--error-text);
            border-left: 4px solid var(--coral);
        }

        /* ── Footer badge ── */
        .footer-note {
            text-align: center;
            font-size: 11px;
            color: var(--text-soft);
            padding: 0 40px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .footer-note span {
            display: inline-block;
            width: 6px;
            height: 6px;
            background: var(--mint);
            border-radius: 50%;
        }

        /* ── Responsive ── */
        @media (max-width: 480px) {
            .header, .form-container, .tabs { padding-left: 24px; padding-right: 24px; }
            .tabs { margin: 0 24px; }
            .footer-note { padding-left: 24px; padding-right: 24px; }
        }
    </style>

        <!-- PWA -->
    <link rel="manifest" href="/proyecto_login/manifest.json">
    <meta name="theme-color" content="#2C3E6B">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="CrochetLab">
    <link rel="apple-touch-icon" href="/proyecto_login/assets/img/logo.png">
</head>

<body>
    <div class="container">
        <!-- Rainbow stripe -->
        <div class="stripe"></div>

        <!-- Header with logo -->
        <div class="header">
            <div class="logo-wrap">
                <img src="assets/img/logo.png" alt="CrochetLab logo">
            </div>
            <h1>Crochet<span>Lab</span></h1>
            <p>Tejido a mano, con amor y creatividad 🧶</p>
        </div>

        <!-- Form -->
        <div class="form-container">
            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="mensaje <?php echo $_SESSION['tipo_mensaje']; ?>">
                    <?php
                        echo htmlspecialchars($_SESSION['mensaje'], ENT_QUOTES, 'UTF-8');
                        unset($_SESSION['mensaje']);
                        unset($_SESSION['tipo_mensaje']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Login -->
            <form action="controlador/validar_usuario.php" method="POST">
<?php echo campoCsrf(); ?>
                    <input type="hidden" name="accion" value="login">

                    <div class="form-group">
                        <label for="usuario">Usuario o correo</label>
                        <input type="text" id="usuario" name="usuario" required autocomplete="username" placeholder="Tu usuario o correo">
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                            <button type="button" class="toggle-password" onclick="togglePassword('password', this)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn">Acceder al taller ✦</button>

                    <div style="text-align:center;margin-top:16px;font-size:14px;line-height:1.9;">
                        <a href="vista/recuperar.php" style="color:var(--coral-dark,#d97060);font-weight:700;text-decoration:none;">¿Olvidaste tu contraseña?</a><br>
                        <span style="color:var(--text-soft,#6a7fa8);">¿Aún no tienes cuenta?</span>
                        <a href="vista/registro.php" style="color:var(--coral-dark,#d97060);font-weight:700;text-decoration:none;">Regístrate</a>
                    </div>

                    <div class="social-login">
                        <div class="social-separator">Otros métodos</div>
                        <div class="social-buttons">
                            <button type="button" class="social-btn" title="Google">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17.788 5.108A9 9 0 1 0 21 12h-8"></path>
                                </svg>
                            </button>
                            <button type="button" class="social-btn" title="Facebook">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                                </svg>
                            </button>
                            <button type="button" class="social-btn" title="Instagram">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                                    <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                                    <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                                </svg>
                            </button>
                        </div>
                    </div>
                </form>
        </div>

        <!-- Footer -->
        <div class="footer-note">
            <span></span>
            Bolsos · Ropa · Amigurumis · Accesorios
            <span></span>
        </div>
    </div>

    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('svg');
            if (input.type === "password") {
                input.type = "text";
                icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            } else {
                input.type = "password";
                icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            }
        }

                // Registrar Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/proyecto_login/service-worker.js')
                    .then(reg => console.log('SW registrado:', reg.scope))
                    .catch(err => console.log('SW error:', err));
            });
        }
    </script>
</body>
</html>