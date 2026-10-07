<?php
// database/cifrar_datos_existentes.php
// Cifra los datos sensibles que ya estaban guardados en texto plano.
//
//   php database/cifrar_datos_existentes.php
//
// Requisitos: migración 06 aplicada y CIFRADO_CLAVE en el .env.
// Es seguro repetirlo: solo cifra los valores que todavía no empiezan con "enc:v1:".
// Todo ocurre en una transacción: si algo falla, no se cambia nada.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/cifrado.php';

// tabla => columnas sensibles
const COLUMNAS_SENSIBLES = [
    'empleados' => ['telefono', 'direccion'],
    'pedidos'   => ['cliente_contacto', 'direccion_entrega'],
    'usuarios'  => ['totp_secreto'],
];

try {
    cifradoClave(); // falla pronto y con mensaje claro si no hay clave
    $db = (new Conexion())->conectar();
    $db->beginTransaction();

    $total = 0;
    foreach (COLUMNAS_SENSIBLES as $tabla => $columnas) {
        foreach ($columnas as $col) {
            // Los nombres salen de la constante de arriba, no de entrada del usuario
            $sel = $db->query(
                "SELECT id, $col AS valor FROM $tabla
                 WHERE $col IS NOT NULL AND $col <> '' AND $col NOT LIKE '" . CIFRADO_PREFIJO . "%'"
            );
            $upd = $db->prepare("UPDATE $tabla SET $col = :v WHERE id = :id");
            $n = 0;
            foreach ($sel->fetchAll() as $fila) {
                $upd->execute([':v' => cifrar($fila['valor'], "$tabla.$col"), ':id' => $fila['id']]);
                $n++;
            }
            printf("  %-10s %-18s %d valor(es) cifrado(s)\n", $tabla, $col, $n);
            $total += $n;
        }
    }

    $db->commit();
    echo "Listo. Total cifrado en esta ejecución: $total\n";
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\nNo se modificó ningún dato.\n");
    exit(1);
}
