<?php
// modelo/env.php
// Lectura de variables desde un archivo .env.
// Se busca primero FUERA de la carpeta pública (la carpeta padre del proyecto) y,
// si no existe, en la raíz del proyecto.

function cargarEnv(): array
{
    static $vars = null;
    if ($vars !== null) {
        return $vars;
    }
    $vars = [];

    $raiz = dirname(__DIR__);
    foreach ([dirname($raiz) . '/.env', $raiz . '/.env'] as $ruta) {
        if (!is_readable($ruta)) {
            continue;
        }
        foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            $linea = trim(ltrim($linea, "\xEF\xBB\xBF"));          // quita BOM y espacios
            if ($linea === '' || $linea[0] === '#' || strpos($linea, '=') === false) {
                continue;
            }
            [$clave, $valor] = explode('=', $linea, 2);
            $clave = trim($clave);
            $valor = trim($valor);
            if (strlen($valor) >= 2 && ($valor[0] === '"' || $valor[0] === "'") && substr($valor, -1) === $valor[0]) {
                $valor = substr($valor, 1, -1);
            }
            $vars[$clave] = $valor;
        }
        break;
    }
    return $vars;
}

function env(string $clave, $defecto = null)
{
    $vars = cargarEnv();
    return array_key_exists($clave, $vars) ? $vars[$clave] : $defecto;
}
