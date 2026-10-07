<?php
// vista/_cuenta_layout.php
// Estructura común de las pantallas de cuenta (registro, recuperación, 2FA, mi cuenta).

require_once __DIR__ . '/../modelo/seguridad.php';

function cuentaInicio(string $titulo, string $subtitulo = '', string $clase = ''): void
{
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');

    $mensaje = $_SESSION['mensaje'] ?? null;
    $tipo    = $_SESSION['tipo_mensaje'] ?? 'error';
    unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);
    if (!in_array($tipo, ['error', 'success', 'aviso'], true)) {
        $tipo = 'error';
    }
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>CrochetLab — <?php echo htmlspecialchars($titulo); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/cuenta.css">
</head>
<body>
<div class="pagina"><div class="caja <?php echo htmlspecialchars($clase); ?>">
    <div class="cabecera">
        <img class="logo" src="../assets/img/logo.png" alt="CrochetLab">
        <h1>Crochet<span>Lab</span></h1>
    </div>
    <h2><?php echo htmlspecialchars($titulo); ?></h2>
    <?php if ($subtitulo !== ''): ?><p class="sub"><?php echo htmlspecialchars($subtitulo); ?></p><?php endif; ?>
    <?php if ($mensaje): ?>
        <div class="mensaje <?php echo $tipo; ?>"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
<?php
}

function cuentaFin(): void
{
    echo "</div></div>\n</body>\n</html>\n";
}
