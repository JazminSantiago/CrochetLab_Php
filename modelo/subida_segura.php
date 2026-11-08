<?php
// modelo/subida_segura.php
// Valida una imagen subida por su CONTENIDO real (no por lo que dice el navegador)
// y devuelve una extension segura elegida por el servidor, o null si no es valida.
function extensionImagenSegura(string $tmp): ?string
{
    if ($tmp === '' || !is_uploaded_file($tmp)) return null;
    $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime  = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!isset($tipos[$mime])) return null;
    if (@getimagesize($tmp) === false) return null;
    return $tipos[$mime];
}
