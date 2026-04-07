<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// vista/empleados/index_empleados.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../controlador/empleados_ctrl.php';

// Solo admins
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../../index.php');
    exit();
}

$ctrl      = new EmpleadosCtrl();
$empleados = $ctrl->listar();

$especialidades = ['Amigurumis','Bolsos','Ropa','Accesorios','General'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrochetLab — Empleados</title>
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

        .page-title {
            font-family:'DM Serif Display',serif;
            font-size:20px;
            color:var(--navy);
        }

        .navbar-brand {
            display:flex; align-items:center; gap:10px;
            text-decoration:none;
        }
        .navbar-brand img { width:32px;height:32px;border-radius:50%;object-fit:cover; }
        .navbar-brand span { font-family:'DM Serif Display',serif;font-size:20px;color:var(--navy); }
        .navbar-brand span em { color:var(--coral);font-style:normal; }

        /* ── CONTAINER ── */
        .container { max-width:1100px; margin:0 auto; padding:32px 24px 60px; }

        /* ── TOOLBAR ── */
        .toolbar {
            display:flex;
            align-items:center;
            justify-content:space-between;
            margin-bottom:24px;
            gap:12px;
            flex-wrap:wrap;
        }

        .toolbar-left { display:flex; align-items:center; gap:10px; }

        .search-input {
            padding:10px 16px;
            border:1.5px solid var(--border);
            border-radius:10px;
            font-size:14px;
            font-family:'Nunito',sans-serif;
            background:var(--white);
            color:var(--text-main);
            width:240px;
            transition:border-color 0.2s, box-shadow 0.2s;
        }
        .search-input:focus {
            outline:none;
            border-color:var(--sky);
            box-shadow:0 0 0 3px rgba(122,191,204,0.18);
        }

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
            background:var(--navy);
            color:white;
            border:none;
            border-radius:10px;
            padding:10px 20px;
            font-size:14px;
            font-weight:800;
            font-family:'Nunito',sans-serif;
            cursor:pointer;
            transition:background 0.2s, transform 0.1s;
            text-decoration:none;
        }
        .btn-primary:hover { background:var(--coral); transform:translateY(-1px); }

        /* ── MENSAJE ── */
        .mensaje {
            padding:13px 16px;
            border-radius:10px;
            margin-bottom:20px;
            font-size:14px;
            font-weight:600;
        }
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
            padding:14px 16px;
            text-align:left;
            font-size:11px;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:0.07em;
            color:var(--text-soft);
            border-bottom:1px solid var(--border);
            white-space:nowrap;
        }

        td {
            padding:14px 16px;
            font-size:14px;
            border-bottom:1px solid var(--border);
            vertical-align:middle;
        }

        tr:last-child td { border-bottom:none; }
        tbody tr { transition:background 0.15s; }
        tbody tr:hover { background:var(--sky-pale); }

        /* Avatar inicial */
        .emp-avatar {
            width:36px; height:36px;
            border-radius:50%;
            background:linear-gradient(135deg,var(--sky),var(--lavender));
            display:inline-flex;
            align-items:center;
            justify-content:center;
            font-weight:800;
            font-size:14px;
            color:var(--navy);
            flex-shrink:0;
        }

        .emp-info { display:flex; align-items:center; gap:10px; }
        .emp-name { font-weight:700; color:var(--navy); }
        .emp-user { font-size:12px; color:var(--text-soft); }

        /* Badges */
        .badge {
            display:inline-flex; align-items:center; gap:4px;
            border-radius:20px;
            font-size:11px;
            font-weight:700;
            padding:4px 10px;
        }
        .badge-activo    { background:rgba(142,207,192,0.2); color:#1a6b53; }
        .badge-inactivo  { background:rgba(242,144,122,0.2); color:#b94030; }
        .badge-esp {
            background:rgba(122,191,204,0.15);
            color:var(--navy);
            border:1px solid rgba(122,191,204,0.4);
        }

        /* Acciones */
        .actions { display:flex; gap:6px; }

        .btn-action {
            padding:6px 12px;
            border-radius:8px;
            font-size:12px;
            font-weight:700;
            font-family:'Nunito',sans-serif;
            cursor:pointer;
            border:1.5px solid;
            transition:all 0.2s;
        }

        .btn-edit {
            background:var(--sky-pale);
            color:var(--navy);
            border-color:var(--border);
        }
        .btn-edit:hover { background:var(--sky); color:white; border-color:var(--sky); }

        .btn-deactivate {
            background:rgba(242,144,122,0.1);
            color:var(--coral-dark);
            border-color:rgba(242,144,122,0.4);
        }
        .btn-deactivate:hover { background:var(--coral); color:white; border-color:var(--coral); }

        .btn-activate {
            background:rgba(142,207,192,0.1);
            color:#1a6b53;
            border-color:rgba(142,207,192,0.4);
        }
        .btn-activate:hover { background:var(--mint); color:white; border-color:var(--mint); }

        .empty-state {
            text-align:center;
            padding:60px 20px;
            color:var(--text-soft);
        }
        .empty-state .icon { font-size:48px; margin-bottom:12px; }

        /* ── MODAL ── */
        .modal-overlay {
            display:none;
            position:fixed;
            inset:0;
            background:rgba(44,62,107,0.45);
            z-index:500;
            align-items:center;
            justify-content:center;
            padding:20px;
            backdrop-filter:blur(3px);
        }
        .modal-overlay.active { display:flex; }

        .modal {
            background:var(--white);
            border-radius:20px;
            width:100%;
            max-width:560px;
            max-height:90vh;
            overflow-y:auto;
            box-shadow:0 20px 60px rgba(44,62,107,0.2);
            animation:modalIn 0.3s cubic-bezier(0.22,1,0.36,1) both;
        }

        @keyframes modalIn {
            from { opacity:0; transform:translateY(20px) scale(0.97); }
            to   { opacity:1; transform:translateY(0) scale(1); }
        }

        .modal-header {
            padding:24px 28px 16px;
            border-bottom:1px solid var(--border);
            display:flex;
            align-items:center;
            justify-content:space-between;
        }

        .modal-title {
            font-family:'DM Serif Display',serif;
            font-size:20px;
            color:var(--navy);
        }

        .modal-close {
            background:none; border:none;
            font-size:22px; cursor:pointer;
            color:var(--text-soft);
            width:32px; height:32px;
            display:flex; align-items:center; justify-content:center;
            border-radius:8px;
            transition:background 0.2s;
        }
        .modal-close:hover { background:var(--sky-pale); color:var(--navy); }

        .modal-body { padding:24px 28px; }

        .form-row {
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:16px;
        }

        .form-group { margin-bottom:16px; }
        .form-group.full { grid-column:1/-1; }

        label {
            display:block;
            margin-bottom:6px;
            font-size:11px;
            font-weight:800;
            letter-spacing:0.07em;
            text-transform:uppercase;
            color:var(--navy);
        }

        .required { color:var(--coral); margin-left:2px; }

        input, select, textarea {
            width:100%;
            padding:11px 14px;
            border:1.5px solid var(--border);
            border-radius:10px;
            font-size:14px;
            font-family:'Nunito',sans-serif;
            background:var(--sky-pale);
            color:var(--text-main);
            transition:border-color 0.2s, box-shadow 0.2s;
        }

        input:focus, select:focus, textarea:focus {
            outline:none;
            border-color:var(--sky);
            background:white;
            box-shadow:0 0 0 3px rgba(122,191,204,0.18);
        }

        textarea { resize:vertical; min-height:72px; }

        .modal-footer {
            padding:16px 28px 24px;
            display:flex;
            justify-content:flex-end;
            gap:10px;
            border-top:1px solid var(--border);
        }

        .btn-cancel {
            padding:10px 20px;
            border-radius:10px;
            font-size:14px;
            font-weight:700;
            font-family:'Nunito',sans-serif;
            cursor:pointer;
            background:var(--sky-pale);
            color:var(--text-soft);
            border:1.5px solid var(--border);
            transition:all 0.2s;
        }
        .btn-cancel:hover { background:var(--border); color:var(--navy); }

        .section-divider {
            font-size:11px;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:0.07em;
            color:var(--text-soft);
            margin:8px 0 16px;
            display:flex;
            align-items:center;
            gap:8px;
        }
        .section-divider::after { content:''; flex:1; height:1px; background:var(--border); }

        @keyframes fadeUp {
            from { opacity:0; transform:translateY(12px); }
            to   { opacity:1; transform:translateY(0); }
        }

        /* Responsive */
        @media(max-width:768px) {
            .navbar { padding:0 16px; }
            .container { padding:20px 16px 48px; }
            .form-row { grid-template-columns:1fr; }
            .search-input { width:180px; }
            td, th { padding:10px 12px; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-left">
        <a href="../menu_principal.php" class="back-btn">
            ← Menú
        </a>
        <span class="page-title">👷 Empleados</span>
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
            <input type="text" class="search-input" id="searchInput" placeholder="🔍 Buscar empleado...">
            <select class="filter-select" id="filterEstado" onchange="filtrar()">
                <option value="">Todos</option>
                <option value="activo">Activos</option>
                <option value="inactivo">Inactivos</option>
            </select>
        </div>
        <button class="btn-primary" onclick="abrirModalCrear()">
            + Nuevo Empleado
        </button>
    </div>

    <!-- Tabla -->
    <div class="table-wrap">
        <table id="tablaEmpleados">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Especialidad</th>
                    <th>Teléfono</th>
                    <th>Fecha Ingreso</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($empleados)): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <div class="icon">🧶</div>
                            <p>No hay empleados registrados aún.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($empleados as $e): ?>
                <tr data-estado="<?php echo $e['activo'] ? 'activo' : 'inactivo'; ?>">
                    <td>
                        <div class="emp-info">
                            <div class="emp-avatar"><?php echo strtoupper(substr($e['nombre'],0,1)); ?></div>
                            <div>
                                <div class="emp-name"><?php echo htmlspecialchars($e['nombre']); ?></div>
                                <div class="emp-user">@<?php echo htmlspecialchars($e['usuario']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($e['especialidad']): ?>
                            <span class="badge badge-esp"><?php echo htmlspecialchars($e['especialidad']); ?></span>
                        <?php else: ?>
                            <span style="color:var(--text-soft);font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($e['telefono'] ?? '—'); ?></td>
                    <td><?php echo $e['fecha_ingreso'] ? date('d/m/Y', strtotime($e['fecha_ingreso'])) : '—'; ?></td>
                    <td>
                        <?php if ($e['activo']): ?>
                            <span class="badge badge-activo">● Activo</span>
                        <?php else: ?>
                            <span class="badge badge-inactivo">● Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="actions">
                            <button class="btn-action btn-edit"
                                onclick="abrirModalEditar(<?php echo htmlspecialchars(json_encode($e)); ?>)">
                                ✏️ Editar
                            </button>
                            <form action="../../controlador/empleados_ctrl.php" method="POST" style="margin:0;">
                                <input type="hidden" name="accion" value="toggle_activo">
                                <input type="hidden" name="empleado_id" value="<?php echo $e['id']; ?>">
                                <button type="submit" class="btn-action <?php echo $e['activo'] ? 'btn-deactivate' : 'btn-activate'; ?>"
                                    onclick="return confirm('<?php echo $e['activo'] ? '¿Desactivar a ' : '¿Activar a '; echo htmlspecialchars($e['nombre']); ?>?')">
                                    <?php echo $e['activo'] ? '🔒 Desactivar' : '🔓 Activar'; ?>
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

<!-- MODAL CREAR / EDITAR -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalTitle">Nuevo Empleado</span>
            <button class="modal-close" onclick="cerrarModal()">✕</button>
        </div>
        <form action="../../controlador/empleados_ctrl.php" method="POST">
            <input type="hidden" name="accion" id="formAccion" value="crear">
            <input type="hidden" name="empleado_id" id="formEmpleadoId" value="">

            <div class="modal-body">
                <!-- Datos de acceso -->
                <div class="section-divider">Datos de acceso</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Usuario <span class="required">*</span></label>
                        <input type="text" name="usuario" id="f_usuario" placeholder="nombre_usuario">
                    </div>
                    <div class="form-group">
                        <label>Contraseña <span class="required" id="passRequired">*</span></label>
                        <input type="password" name="password" id="f_password" placeholder="Mín. 6 caracteres">
                    </div>
                </div>

                <!-- Datos personales -->
                <div class="section-divider">Datos personales</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre completo <span class="required">*</span></label>
                        <input type="text" name="nombre" id="f_nombre" placeholder="Nombre y apellidos">
                    </div>
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" id="f_email" placeholder="correo@ejemplo.com">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" id="f_telefono" placeholder="442 000 0000">
                    </div>
                    <div class="form-group">
                        <label>Fecha de ingreso</label>
                        <input type="date" name="fecha_ingreso" id="f_fecha_ingreso">
                    </div>
                </div>

                <!-- Datos laborales -->
                <div class="section-divider">Datos laborales</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Especialidad <span class="required">*</span></label>
                        <select name="especialidad" id="f_especialidad">
                            <option value="">Selecciona...</option>
                            <?php foreach ($especialidades as $esp): ?>
                            <option value="<?php echo $esp; ?>"><?php echo $esp; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Dirección</label>
                    <textarea name="direccion" id="f_direccion" placeholder="Calle, colonia, ciudad..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn-primary" id="btnSubmit">Registrar empleado</button>
            </div>
        </form>
    </div>
</div>

<script>
    // ── Modal ──
    function abrirModalCrear() {
        document.getElementById('modalTitle').textContent  = 'Nuevo Empleado';
        document.getElementById('formAccion').value        = 'crear';
        document.getElementById('formEmpleadoId').value    = '';
        document.getElementById('btnSubmit').textContent   = 'Registrar empleado';
        document.getElementById('passRequired').style.display = 'inline';
        document.getElementById('f_usuario').disabled     = false;

        // Limpiar campos
        ['f_usuario','f_password','f_nombre','f_email','f_telefono','f_fecha_ingreso','f_direccion']
            .forEach(id => document.getElementById(id).value = '');
        document.getElementById('f_especialidad').value = '';

        document.getElementById('modalOverlay').classList.add('active');
    }

    function abrirModalEditar(emp) {
        document.getElementById('modalTitle').textContent  = 'Editar Empleado';
        document.getElementById('formAccion').value        = 'editar';
        document.getElementById('formEmpleadoId').value   = emp.id;
        document.getElementById('btnSubmit').textContent   = 'Guardar cambios';
        document.getElementById('passRequired').style.display = 'none';

        // Usuario no se puede cambiar
        document.getElementById('f_usuario').value    = emp.usuario;
        document.getElementById('f_usuario').disabled = true;
        document.getElementById('f_password').value   = '';
        document.getElementById('f_nombre').value     = emp.nombre;
        document.getElementById('f_email').value      = emp.email;
        document.getElementById('f_telefono').value   = emp.telefono || '';
        document.getElementById('f_fecha_ingreso').value = emp.fecha_ingreso || '';
        document.getElementById('f_especialidad').value  = emp.especialidad || '';
        document.getElementById('f_direccion').value     = emp.direccion || '';

        document.getElementById('modalOverlay').classList.add('active');
    }

    function cerrarModal() {
        document.getElementById('modalOverlay').classList.remove('active');
    }

    // Cerrar al hacer click fuera del modal
    document.getElementById('modalOverlay').addEventListener('click', function(e) {
        if (e.target === this) cerrarModal();
    });

    // ── Búsqueda y filtro ──
    document.getElementById('searchInput').addEventListener('input', filtrar);

    function filtrar() {
        const busqueda = document.getElementById('searchInput').value.toLowerCase();
        const estado   = document.getElementById('filterEstado').value;
        const filas    = document.querySelectorAll('#tablaEmpleados tbody tr[data-estado]');

        filas.forEach(fila => {
            const texto      = fila.textContent.toLowerCase();
            const estadoFila = fila.dataset.estado;

            const coincideTexto  = texto.includes(busqueda);
            const coincideEstado = !estado || estadoFila === estado;

            fila.style.display = (coincideTexto && coincideEstado) ? '' : 'none';
        });
    }
</script>

</body>
</html>