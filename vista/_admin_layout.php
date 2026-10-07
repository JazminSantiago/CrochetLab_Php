<?php
// vista/_admin_layout.php
// Estructura común de las pantallas del panel de administración (usuarios, roles).

require_once __DIR__ . '/../modelo/seguridad.php';

if (!function_exists('h')) {
    function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

function adminInicio(string $titulo, string $subtitulo = ''): void
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
    <title>CrochetLab — <?php echo h($titulo); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <nav class="navbar">
        <a href="menu_principal.php" class="back-btn">← Menú</a>
        <a class="navbar-brand" href="menu_principal.php">Crochet<em>Lab</em></a>
    </nav>
    <div class="container">
        <h1><?php echo h($titulo); ?></h1>
        <?php if ($subtitulo !== ''): ?><p class="sub"><?php echo h($subtitulo); ?></p><?php endif; ?>
        <?php if ($mensaje): ?><div class="mensaje <?php echo $tipo; ?>"><?php echo h($mensaje); ?></div><?php endif; ?>
<?php
}

function adminFin(): void
{
    echo "    </div>\n</body>\n</html>\n";
}
