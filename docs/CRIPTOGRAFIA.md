# Criptografía en CrochetLab

La aplicación usa **dos mecanismos criptográficos**, ambos con la extensión **OpenSSL de PHP** (librería establecida; no se implementó ningún algoritmo a mano).

| # | Mecanismo | Algoritmo | Dónde se aplica | Código |
|---|---|---|---|---|
| 1 | Cifrado **simétrico** de datos | AES-256-GCM | Teléfono y dirección de empleados, contacto y dirección de entrega de pedidos, secreto del 2FA | `modelo/cifrado.php` |
| 2 | **Firma digital** (asimétrica) | RSA-2048 + SHA-256 | Comprobante de pedido: autenticidad e integridad | `modelo/firma.php` |

## Por qué OpenSSL
Es la librería criptográfica estándar de PHP: envuelve OpenSSL, que auditan y usan millones de sistemas. Ofrece AES en modo GCM (`openssl_encrypt`) y firmas RSA (`openssl_sign` / `openssl_verify`). Implementarlos a mano sería inseguro.

## 1. Cifrado simétrico (AES-256-GCM)
Flujo completo: el dato **entra en claro** en el formulario → `cifrar()` lo guarda como `enc:v1:BASE64(IV + etiqueta + cifrado)` → al leerlo, `descifrar()` lo devuelve en claro solo a quien tiene permiso.
- Cada valor usa un **IV aleatorio** de 12 bytes: el mismo texto cifra distinto cada vez.
- GCM **autentica**: si alguien modifica el valor en la base, el descifrado falla (no devuelve basura).
- El contexto `tabla.columna` se autentica como dato asociado: un valor no se puede copiar a otra columna.
- **Llave:** 32 bytes en base64, variable `CIFRADO_CLAVE` del archivo `.env`, que está **fuera de la carpeta pública y del repositorio**. Se genera con `php database/generar_clave.php`. Quien robe solo la base de datos no puede leer esos campos.

## 2. Firma digital RSA-2048 + SHA-256
Resuelve un caso real: **un comprobante de pedido que el cliente o un auditor puede verificar** sin confiar en la base de datos.
- Al pulsar **«Firmar comprobante»**, el sistema arma un JSON canónico con los datos del pedido (producto, descripción, cliente, fechas, envío), lo firma con la **llave privada** y guarda contenido + firma + huella de la llave pública.
- **Verificar** = recalcular con la **llave pública**: (a) ¿la firma corresponde exactamente al contenido? (autenticidad e integridad); (b) ¿el pedido sigue igual que cuando se firmó? Resultados: *válida*, *modificado después de firmar*, *NO válida*.
- El contacto y la dirección del cliente **no** entran al comprobante (son datos cifrados y privados).
- La verificación también se puede hacer **fuera del sistema** con OpenSSL (la pantalla del comprobante descarga los tres archivos):
  `openssl dgst -sha256 -verify clave_publica.pem -signature comprobante_2_v1.firma comprobante_2_v1.json` → `Verified OK`.
- Cada firma nueva es una **versión** (v1, v2…); el historial se conserva.

### Dónde y cómo se guardan las llaves
| Llave | Dónde | Protección |
|---|---|---|
| Privada de firma | Archivo PEM **fuera** del proyecto: `<carpeta padre>/claves/firma_privada.pem` (ruta en `FIRMA_CLAVE_PRIVADA`) | Cifrada con AES-256 y **frase de paso** en `FIRMA_CLAVE_FRASE` (`.env`). Permisos 600 en Linux. No se sube a GitHub |
| Pública de firma | `<carpeta padre>/claves/firma_publica.pem` | No es secreta; se puede entregar a cualquiera |
| Simétrica AES | `CIFRADO_CLAVE` en el `.env` | Fuera de la base de datos y del repositorio |

Generar el par: `php database/generar_par_claves.php` (no sobrescribe llaves existentes).

## Demostración (antes / después)
1. **Cifrado:** crear un pedido personalizado con contacto y dirección → en la aplicación se ven normales → en la base (`SELECT cliente_contacto, direccion_entrega FROM pedidos`) aparece `enc:v1:...`.
2. **Firma:** Pedidos → 🧾 Comprobante → *Firmar* → «Firma válida».
3. **Alteración:** cambiar un dato del pedido en la base → el comprobante dice «cambió después de firmarse» y muestra qué cambió. Cambiar el contenido firmado o la firma en `pedido_firmas` → «Firma NO válida».
4. **Verificación externa:** descargar los tres archivos y ejecutar el comando de `openssl`; cambiar una letra del `.json` → `Verification failure`.

## Limitaciones conocidas
- Una sola llave de firma para todo el sistema, sin rotación automática (la huella guardada en cada firma permite detectar que cambió la llave).
- El usuario del servidor web puede leer la llave privada y la frase (están en su `.env`); para producción convendría un módulo de seguridad (HSM) o un gestor de secretos.
- Sin sello de tiempo de una autoridad externa: la fecha de emisión la pone el servidor.
