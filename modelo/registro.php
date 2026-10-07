<?php
// modelo/registro.php
// Historial de accesos y auditoría de acciones.
// Si el registro falla (BD caída, tabla ausente) NUNCA interrumpe la operación del usuario:
// el error se envía al log de PHP.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/conexion.php';

function ipCliente(): string
{
    // Solo REMOTE_ADDR: las cabeceras X-Forwarded-For las puede falsificar el cliente.
    // Si luego hay un proxy inverso de confianza (Apache/Nginx), se ajusta aquí.
    return substr($_SERVER['REMOTE_ADDR'] ?? 'desconocida', 0, 45);
}

function agenteCliente(): string
{
    return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
}

function dbRegistro(): PDO
{
    static $db = null;
    if ($db === null) {
        $db = (new Conexion())->conectar();
    }
    return $db;
}

// evento: 'login_ok' | 'login_fallido' | 'logout'
function registrarAcceso($usuarioId, ?string $usuarioIntentado, string $evento, ?string $motivo = null): void
{
    try {
        dbRegistro()->prepare(
            "INSERT INTO historial_accesos (usuario_id, usuario_intentado, evento, motivo, ip, user_agent)
             VALUES (:uid, :intentado, :evento, :motivo, :ip, :ua)"
        )->execute([
            ':uid'       => $usuarioId !== null ? (int)$usuarioId : null,
            ':intentado' => $usuarioIntentado !== null ? substr($usuarioIntentado, 0, 50) : null,
            ':evento'    => $evento,
            ':motivo'    => $motivo,
            ':ip'        => ipCliente(),
            ':ua'        => agenteCliente(),
        ]);
    } catch (Throwable $e) {
        error_log('registrarAcceso: ' . $e->getMessage());
    }
}

// Registra una acción del usuario actual (o de $usuarioNombre si aún no hay sesión).
function auditar(string $accion, string $area, $registroId = null, ?string $detalle = null, ?string $usuarioNombre = null): void
{
    try {
        $uid    = $_SESSION['usuario_id'] ?? null;
        $nombre = $usuarioNombre ?? ($_SESSION['usuario'] ?? null);

        dbRegistro()->prepare(
            "INSERT INTO auditoria (usuario_id, usuario_nombre, accion, area, registro_id, detalle, ip)
             VALUES (:uid, :nombre, :accion, :area, :rid, :detalle, :ip)"
        )->execute([
            ':uid'     => $uid !== null ? (int)$uid : null,
            ':nombre'  => $nombre !== null ? substr($nombre, 0, 50) : null,
            ':accion'  => substr($accion, 0, 30),
            ':area'    => substr($area, 0, 50),
            ':rid'     => is_numeric($registroId) ? (int)$registroId : null,
            ':detalle' => $detalle !== null ? substr($detalle, 0, 500) : null,
            ':ip'      => ipCliente(),
        ]);
    } catch (Throwable $e) {
        error_log('auditar: ' . $e->getMessage());
    }
}
