<?php
// modelo/correo.php
// Envío de correos. MAIL_MODO=smtp usa PHPMailer (carpeta lib/PHPMailer/src);
// MAIL_MODO=archivo guarda el correo como .html en la carpeta correos_salida
// (junto al .env, fuera de la carpeta pública) para desarrollar sin internet.

require_once __DIR__ . '/env.php';

function enviarCorreo(string $para, string $nombrePara, string $asunto, string $html, string $texto = ''): bool
{
    if (strtolower((string)env('MAIL_MODO', 'archivo')) !== 'smtp') {
        return guardarCorreoEnArchivo($para, $asunto, $html);
    }

    $base = dirname(__DIR__) . '/lib/PHPMailer/src/';
    foreach (['Exception.php', 'PHPMailer.php', 'SMTP.php'] as $archivo) {
        if (!is_readable($base . $archivo)) {
            error_log('enviarCorreo: falta ' . $base . $archivo . ' (descarga PHPMailer, ver LEEME_PASO3).');
            return false;
        }
        require_once $base . $archivo;
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = (string)env('MAIL_HOST', 'smtp.gmail.com');
        $mail->Port       = (int)env('MAIL_PORT', 587);
        $mail->SMTPAuth   = true;
        $mail->Username   = (string)env('MAIL_USER', '');
        $mail->Password   = (string)env('MAIL_PASS', '');
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 15;

        $mail->setFrom((string)env('MAIL_USER', ''), (string)env('MAIL_FROM_NAME', 'CrochetLab'));
        $mail->addAddress($para, $nombrePara);
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $html;
        $mail->AltBody = $texto !== '' ? $texto : trim(strip_tags($html));
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('enviarCorreo: ' . $e->getMessage());
        return false;
    }
}

function guardarCorreoEnArchivo(string $para, string $asunto, string $html): bool
{
    $dir = dirname(__DIR__, 2) . '/correos_salida';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        error_log('enviarCorreo: no se pudo crear ' . $dir);
        return false;
    }
    $nombre = date('Ymd_His') . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $para) . '.html';
    $cuerpo = '<!-- Para: ' . htmlspecialchars($para) . ' | Asunto: ' . htmlspecialchars($asunto) . " -->\n" . $html;
    return file_put_contents($dir . '/' . $nombre, $cuerpo) !== false;
}

// Plantilla sencilla para los correos del sistema.
function plantillaCorreo(string $titulo, string $parrafoHtml, string $textoBoton, string $url, string $pie): string
{
    $u = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    return '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;padding:24px;color:#2C3E6B;">'
         . '<h2 style="margin:0 0 12px;">Crochet<span style="color:#F2907A;">Lab</span></h2>'
         . '<h3 style="margin:0 0 12px;">' . htmlspecialchars($titulo) . '</h3>'
         . '<p style="line-height:1.5;">' . $parrafoHtml . '</p>'
         . '<p style="margin:24px 0;"><a href="' . $u . '" style="background:#F2907A;color:#fff;text-decoration:none;'
         . 'padding:12px 22px;border-radius:8px;font-weight:bold;">' . htmlspecialchars($textoBoton) . '</a></p>'
         . '<p style="font-size:13px;color:#6a7fa8;">Si el botón no funciona, copia este enlace en tu navegador:<br>' . $u . '</p>'
         . '<hr style="border:0;border-top:1px solid #daedf2;margin:20px 0;">'
         . '<p style="font-size:12px;color:#6a7fa8;">' . $pie . '</p></div>';
}
