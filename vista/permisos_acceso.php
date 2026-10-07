<?php
// vista/permisos_acceso.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../controlador/permisos_ctrl.php';

require_once __DIR__ . '/../modelo/autorizacion.php';
requierePermiso('usuarios', 'lectura');

$ctrl        = new PermisosCtrl();
$uid         = $_SESSION['usuario_id'];
$nombre      = $_SESSION['nombre'];
$esSuperadmin = $ctrl->esSuperadmin($uid);
$admins      = $ctrl->listarAdmins();
$tejedores   = $ctrl->listarTejedores();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrochetLab — Permisos y Acceso</title>
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

        .section-title {
            font-family:'DM Serif Display',serif; font-size:18px; color:var(--navy);
            margin-bottom:16px; display:flex; align-items:center; gap:10px;
        }
        .section-title::after { content:''; flex:1; height:1px; background:var(--border); }

        /* GRID DOS COLUMNAS */
        .two-col { display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:32px; }

        /* CARDS */
        .card {
            background:var(--white); border-radius:16px; border:1.5px solid var(--border);
            padding:24px 26px; box-shadow:0 4px 16px rgba(44,62,107,0.05);
            animation: fadeUp 0.4s ease both;
        }
        .card-header { display:flex; align-items:center; gap:12px; margin-bottom:20px; }
        .card-icon { font-size:24px; width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; }
        .card-icon-password { background:rgba(122,191,204,0.15); }
        .card-icon-roles    { background:rgba(242,144,122,0.15); }
        .card-title-sm { font-family:'DM Serif Display',serif; font-size:17px; color:var(--navy); }
        .card-subtitle { font-size:12px; color:var(--text-soft); margin-top:2px; }

        /* FORM */
        .form-group { margin-bottom:14px; }
        label { display:block; margin-bottom:5px; font-size:11px; font-weight:800; letter-spacing:0.07em; text-transform:uppercase; color:var(--navy); }
        .required { color:var(--coral); margin-left:2px; }
        input[type=password], input[type=text] {
            width:100%; padding:10px 13px; border:1.5px solid var(--border); border-radius:9px;
            font-size:14px; font-family:'Nunito',sans-serif; background:var(--sky-pale); color:var(--text-main);
            transition:border-color 0.2s;
        }
        input:focus { outline:none; border-color:var(--sky); background:white; box-shadow:0 0 0 3px rgba(122,191,204,0.18); }
        .btn-primary {
            width:100%; padding:11px; border-radius:10px; border:none;
            background:var(--navy); color:white; font-size:14px; font-weight:800;
            font-family:'Nunito',sans-serif; cursor:pointer; transition:background 0.2s; margin-top:4px;
        }
        .btn-primary:hover { background:var(--coral); }

        /* AVISO SUPERADMIN */
        .superadmin-badge {
            display:inline-flex; align-items:center; gap:6px;
            background:rgba(247,206,122,0.2); border:1px solid rgba(247,206,122,0.6);
            color:#9a7000; border-radius:20px; font-size:11px; font-weight:800;
            padding:4px 12px; margin-bottom:16px;
        }
        .no-superadmin-msg {
            background:rgba(44,62,107,0.05); border-radius:10px; padding:16px;
            font-size:13px; color:var(--text-soft); text-align:center; line-height:1.6;
        }

        /* TABLA ADMINS */
        .table-wrap {
            background:var(--white); border-radius:16px; border:1.5px solid var(--border);
            overflow:hidden; box-shadow:0 4px 16px rgba(44,62,107,0.05); margin-bottom:32px;
            animation: fadeUp 0.4s ease 0.1s both;
        }
        table { width:100%; border-collapse:collapse; }
        thead { background:var(--sky-pale); }
        th { padding:12px 16px; text-align:left; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.07em; color:var(--text-soft); border-bottom:1px solid var(--border); }
        td { padding:13px 16px; font-size:13px; border-bottom:1px solid var(--border); vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tbody tr:hover { background:var(--sky-pale); }

        .user-info { display:flex; align-items:center; gap:10px; }
        .avatar {
            width:34px; height:34px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            font-weight:800; font-size:13px; color:white; flex-shrink:0;
        }
        .avatar-admin  { background:linear-gradient(135deg,var(--navy),#3d5491); }
        .avatar-super  { background:linear-gradient(135deg,var(--yellow),var(--coral)); color:var(--navy); }
        .avatar-tej    { background:linear-gradient(135deg,var(--mint),var(--sky)); color:var(--navy); }
        .u-name { font-weight:700; color:var(--navy); font-size:13px; }
        .u-user { font-size:11px; color:var(--text-soft); }

        .badge { display:inline-flex; align-items:center; gap:4px; border-radius:20px; font-size:11px; font-weight:700; padding:3px 10px; }
        .badge-super  { background:rgba(247,206,122,0.25); color:#9a7000; border:1px solid rgba(247,206,122,0.5); }
        .badge-admin  { background:rgba(44,62,107,0.1);    color:var(--navy); }
        .badge-yo     { background:rgba(122,191,204,0.2);  color:#1a6b7a; }

        .btn-action {
            padding:5px 12px; border-radius:8px; font-size:12px; font-weight:700;
            font-family:'Nunito',sans-serif; cursor:pointer; border:1.5px solid; transition:all 0.2s;
        }
        .btn-degrade { background:rgba(242,144,122,0.1); color:#b94030; border-color:rgba(242,144,122,0.4); }
        .btn-degrade:hover { background:var(--coral); color:white; border-color:var(--coral); }
        .btn-promote { background:rgba(142,207,192,0.1); color:#1a6b53; border-color:rgba(142,207,192,0.4); }
        .btn-promote:hover { background:var(--mint); color:white; border-color:var(--mint); }

        /* MODAL CONFIRMACIÓN */
        .modal-overlay {
            display:none; position:fixed; inset:0; background:rgba(44,62,107,0.45);
            z-index:500; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(3px);
        }
        .modal-overlay.active { display:flex; }
        .modal {
            background:var(--white); border-radius:20px; width:100%; max-width:420px;
            box-shadow:0 20px 60px rgba(44,62,107,0.2);
            animation:modalIn 0.3s cubic-bezier(0.22,1,0.36,1) both;
        }
        @keyframes modalIn {
            from { opacity:0; transform:translateY(16px) scale(0.97); }
            to   { opacity:1; transform:translateY(0) scale(1); }
        }
        .modal-header { padding:22px 26px 14px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
        .modal-title { font-family:'DM Serif Display',serif; font-size:18px; color:var(--navy); }
        .modal-close { background:none; border:none; font-size:20px; cursor:pointer; color:var(--text-soft); width:30px; height:30px; display:flex; align-items:center; justify-content:center; border-radius:8px; }
        .modal-close:hover { background:var(--sky-pale); }
        .modal-body { padding:20px 26px; }
        .modal-body p { font-size:14px; color:var(--text-soft); margin-bottom:14px; line-height:1.6; }
        .modal-footer { padding:14px 26px 20px; display:flex; gap:10px; justify-content:flex-end; border-top:1px solid var(--border); }
        .btn-cancel { padding:9px 18px; border-radius:9px; font-size:13px; font-weight:700; font-family:'Nunito',sans-serif; cursor:pointer; background:var(--sky-pale); color:var(--text-soft); border:1.5px solid var(--border); }

        .warning-box {
            background:rgba(242,144,122,0.1); border:1px solid rgba(242,144,122,0.4);
            border-radius:10px; padding:12px 14px; margin-bottom:14px;
            font-size:13px; color:#b94030; line-height:1.5;
        }
        .warning-box strong { display:block; margin-bottom:3px; }

        @keyframes fadeUp { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
        @media(max-width:768px) {
            .navbar { padding:0 16px; }
            .container { padding:20px 14px 48px; }
            .two-col { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="navbar-left">
        <a href="menu_principal.php" class="back-btn">← Menú</a>
        <span class="page-title">🔐 Permisos y Acceso</span>
    </div>
    <a class="navbar-brand" href="menu_principal.php">
        <img src="../assets/img/logo.png" alt="CrochetLab">
        <span>Crochet<em>Lab</em></span>
    </a>
</nav>

<div class="container">

    <?php if (isset($_SESSION['mensaje'])): ?>
        <div class="mensaje <?php echo $_SESSION['tipo_mensaje']; ?>">
            <?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); ?>
        </div>
    <?php endif; ?>

    <div class="two-col">

        <!-- CAMBIAR CONTRASEÑA -->
        <div class="card">
            <div class="card-header">
                <div class="card-icon card-icon-password">🔑</div>
                <div>
                    <div class="card-title-sm">Cambiar contraseña</div>
                    <div class="card-subtitle">Actualiza tu contraseña de acceso</div>
                </div>
            </div>
            <form action="../controlador/permisos_ctrl.php" method="POST">
<?php echo campoCsrf(); ?>
                <input type="hidden" name="accion" value="cambiar_password">
                <div class="form-group">
                    <label>Contraseña actual <span class="required">*</span></label>
                    <input type="password" name="actual" placeholder="Tu contraseña actual">
                </div>
                <div class="form-group">
                    <label>Nueva contraseña <span class="required">*</span></label>
                    <input type="password" name="nueva" placeholder="Mínimo 6 caracteres">
                </div>
                <div class="form-group">
                    <label>Confirmar nueva contraseña <span class="required">*</span></label>
                    <input type="password" name="confirmar" placeholder="Repite la nueva contraseña">
                </div>
                <button type="submit" class="btn-primary">Actualizar contraseña</button>
            </form>
        </div>

        <!-- GESTIÓN DE ROLES -->
        <div class="card">
            <div class="card-header">
                <div class="card-icon card-icon-roles">👑</div>
                <div>
                    <div class="card-title-sm">Gestión de roles</div>
                    <div class="card-subtitle">Promover o degradar administradores</div>
                </div>
            </div>

            <?php if ($esSuperadmin): ?>
                <div class="superadmin-badge">⭐ Eres el Superadmin — tienes control total</div>
                <p style="font-size:13px;color:var(--text-soft);line-height:1.6;margin-bottom:16px;">
                    Puedes promover tejedores a admin o degradar admins a tejedor.
                    Cada acción requiere confirmar tu contraseña.
                </p>

                <button class="btn-primary" style="background:var(--mint);margin-bottom:10px;"
                        onclick="abrirModalPromover()">
                    ⬆️ Promover tejedor a Admin
                </button>
                <button class="btn-primary" style="background:rgba(242,144,122,0.8);"
                        onclick="abrirModalDegradary()">
                    ⬇️ Degradar Admin a tejedor
                </button>
            <?php else: ?>
                <div class="no-superadmin-msg">
                    🔒 Solo el <strong>Superadmin</strong> puede gestionar roles.<br>
                    Si necesitas cambiar permisos, contacta al administrador principal.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TABLA DE ADMINS ACTUALES -->
    <div class="section-title">Administradores del sistema</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Email</th>
                    <th>Último acceso</th>
                    <th>Rol</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $a): ?>
                <tr>
                    <td>
                        <div class="user-info">
                            <div class="avatar <?php echo $a['es_superadmin'] ? 'avatar-super' : 'avatar-admin'; ?>">
                                <?php echo strtoupper(substr($a['nombre'],0,1)); ?>
                            </div>
                            <div>
                                <div class="u-name"><?php echo htmlspecialchars($a['nombre']); ?></div>
                                <div class="u-user">@<?php echo htmlspecialchars($a['usuario']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:12px;color:var(--text-soft)"><?php echo htmlspecialchars($a['email']); ?></td>
                    <td style="font-size:12px;color:var(--text-soft)">
                        <?php echo $a['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($a['ultimo_acceso'])) : '—'; ?>
                    </td>
                    <td>
                        <?php if ($a['es_superadmin']): ?>
                            <span class="badge badge-super">⭐ Superadmin</span>
                        <?php elseif ($a['id'] == $uid): ?>
                            <span class="badge badge-yo">👤 Admin (tú)</span>
                        <?php else: ?>
                            <span class="badge badge-admin">🛡 Admin</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- MODAL PROMOVER -->
<div class="modal-overlay" id="modalPromover">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">⬆️ Promover a Admin</span>
            <button class="modal-close" onclick="cerrar('modalPromover')">✕</button>
        </div>
        <form action="../controlador/permisos_ctrl.php" method="POST">
<?php echo campoCsrf(); ?>
            <input type="hidden" name="accion" value="promover">
            <div class="modal-body">
                <div class="form-group">
                    <label>Tejedor a promover <span class="required">*</span></label>
                    <select name="target_id" style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:9px;font-size:14px;font-family:'Nunito',sans-serif;background:var(--sky-pale);color:var(--text-main);">
                        <option value="">Selecciona un tejedor...</option>
                        <?php foreach ($tejedores as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nombre']); ?> (@<?php echo htmlspecialchars($t['usuario']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Confirma tu contraseña <span class="required">*</span></label>
                    <input type="password" name="password_confirm" placeholder="Tu contraseña de superadmin">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="cerrar('modalPromover')">Cancelar</button>
                <button type="submit" class="btn-primary" style="width:auto;padding:9px 20px;">Confirmar promoción</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DEGRADAR -->
<div class="modal-overlay" id="modalDegradary">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">⬇️ Degradar Admin</span>
            <button class="modal-close" onclick="cerrar('modalDegradary')">✕</button>
        </div>
        <form action="../controlador/permisos_ctrl.php" method="POST">
<?php echo campoCsrf(); ?>
            <input type="hidden" name="accion" value="degradar">
            <div class="modal-body">
                <div class="warning-box">
                    <strong>⚠️ Acción irreversible desde aquí</strong>
                    El admin perderá acceso al panel de administración inmediatamente.
                </div>
                <div class="form-group">
                    <label>Admin a degradar <span class="required">*</span></label>
                    <select name="target_id" style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:9px;font-size:14px;font-family:'Nunito',sans-serif;background:var(--sky-pale);color:var(--text-main);">
                        <option value="">Selecciona un admin...</option>
                        <?php foreach ($admins as $a):
                            if ($a['es_superadmin'] || $a['id'] == $uid) continue; ?>
                        <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['nombre']); ?> (@<?php echo htmlspecialchars($a['usuario']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Confirma tu contraseña <span class="required">*</span></label>
                    <input type="password" name="password_confirm" placeholder="Tu contraseña de superadmin">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="cerrar('modalDegradary')">Cancelar</button>
                <button type="submit" class="btn-primary" style="width:auto;padding:9px 20px;background:var(--coral);">Confirmar degradación</button>
            </div>
        </form>
    </div>
</div>

<script>
    function abrirModalPromover()  { document.getElementById('modalPromover').classList.add('active'); }
    function abrirModalDegradary() { document.getElementById('modalDegradary').classList.add('active'); }
    function cerrar(id) { document.getElementById(id).classList.remove('active'); }
    document.querySelectorAll('.modal-overlay').forEach(el => {
        el.addEventListener('click', e => { if (e.target === el) el.classList.remove('active'); });
    });
</script>
</body>
</html>