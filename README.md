# CrochetLab

Sistema web (PHP + PostgreSQL) para gestionar un taller de crochet: catálogo, pedidos, empleados y asignaciones,
con **gestión de usuarios, roles y permisos dinámicos, auditoría, verificación en dos pasos, cifrado de datos
sensibles y respaldos automáticos**. Proyecto de la materia *Seguridad en Cómputo* (UNACH), tema: Confidencialidad.

## Qué incluye

| Área | Detalle |
|---|---|
| Cuentas | Registro, login, cambio de contraseña con la sesión abierta, recuperación por correo con token de un solo uso (15 min) |
| Verificación en dos pasos | TOTP (app autenticadora, RFC 6238) + códigos de respaldo. Obligatoria para roles que administran usuarios |
| Roles | Administrador, Editor y Usuario Regular (cliente) por defecto; el admin puede crear más |
| Permisos dinámicos | Matriz por área × lectura / escritura / eliminación, aplicada **en el servidor** en cada petición |
| Panel de administración | Usuarios (cambiar rol, activar/desactivar, desbloquear), Roles y Permisos, Auditoría y accesos |
| Auditoría | Historial de accesos (exitosos y fallidos) y bitácora de acciones sensibles |
| Cifrado en la base | AES-256-GCM para teléfono, direcciones, contacto de pedidos y secreto TOTP (ver `docs/LEEME_PASO5.txt`) |
| Respaldos | Copia cifrada cada 24 h a USB / disco externo, con verificación y restauración (ver `docs/RESPALDOS.txt`) |
| Protecciones | Contraseñas con bcrypt, política de contraseñas, bloqueo por intentos fallidos (cuenta e IP), tokens CSRF, consultas preparadas |

## Requisitos

- PHP 8.2 o superior con las extensiones `pdo_pgsql`, `openssl` y `mbstring` (en Windows basta **XAMPP**; activa `extension=pdo_pgsql` y `extension=pgsql` en `php.ini`).
- PostgreSQL 14 o superior.
- Un navegador moderno.

## Instalación (PowerShell, desde la carpeta del proyecto)

> En Windows, si `psql` o `php` no se reconocen, agrégalos al PATH de esa terminal:
> `$env:Path += ";C:\Program Files\PostgreSQL\18\bin;C:\xampp\php"`

**1. Crea la base de datos**
```
createdb -U postgres sistema_login
```

**2. Aplica el esquema y las migraciones, en este orden**
```
psql -U postgres -d sistema_login -v ON_ERROR_STOP=1 -f database\esquema_crochetlab.sql
Get-ChildItem database\migraciones\migracion_*.sql | Sort-Object Name | ForEach-Object { psql -U postgres -d sistema_login -v ON_ERROR_STOP=1 -f $_.FullName }
```
Debe terminar sin errores (cada migración imprime `COMMIT`).

**3. Crea tu archivo `.env`** en la carpeta **padre** del proyecto (queda fuera de la carpeta pública y fuera de git)
```
Copy-Item env.ejemplo ..\.env
php database\generar_clave.php | Add-Content ..\.env
notepad ..\.env
```
En el Bloc de notas completa `DB_PASS` (tu contraseña de PostgreSQL) y revisa el resto de valores.
Cada instalación necesita **su propia** `CIFRADO_CLAVE`. Nunca subas el `.env` a git.

**4. Crea el primer administrador** (una base nueva no trae usuarios)
```
php database\crear_admin.php
```

**5. Inicia el servidor y entra**
```
php -S localhost:8000
```
Abre <http://localhost:8000> e inicia sesión con el administrador. Como el rol Administrador exige verificación en
dos pasos, el sistema te llevará a *Mi cuenta* para activarla la primera vez (necesitas una app como Google Authenticator).

## Despliegue en el laboratorio (VM + OPNsense)

Para montar el proyecto en dos servidores (web y base de datos) dentro de la red intermedia de OPNsense, con HTTPS, TLS hacia la base de datos, respaldos y las reglas de red, sigue la guía **[docs/GUIA_LAB.md](docs/GUIA_LAB.md)**.

## Roles por defecto

| Rol | Puede |
|---|---|
| Administrador | Todo: usuarios, roles, permisos, catálogo, pedidos, empleados, reportes, auditoría |
| Editor | Su espacio de trabajo: sus asignaciones, patrones aprobados y su progreso |
| Usuario Regular | Ver el catálogo y crear / cancelar **sus propios** pedidos |

Los registros nuevos siempre entran como *Usuario Regular*; solo un administrador puede cambiar el rol.

## Estructura

```
index.php            Acceso (login)
controlador/         Lógica y formularios (validación, permisos, consultas)
modelo/              Conexión, autorización (RBAC), seguridad, cifrado, correo, respaldos
vista/               Pantallas (cuenta, administración, módulos)
assets/              CSS e imágenes
database/            Esquema, migraciones y scripts de mantenimiento
docs/                Documentación de cada etapa
```

## Scripts de mantenimiento (`database/`)

| Script | Para qué |
|---|---|
| `crear_admin.php` | Crea el primer superadministrador |
| `generar_clave.php` | Genera una clave AES-256 para el `.env` |
| `cifrar_datos_existentes.php` | Cifra datos que estaban en texto plano (se puede repetir) |
| `respaldo.php` | Respaldo cifrado a USB / disco externo (programar cada 24 h) |
| `restaurar_respaldo.php` | Restaura un respaldo en una base nueva (con una cuenta que pueda crear bases; ver `docs/GUIA_LAB.md`) |
| `usuario_app.sql` | Crea el usuario `crochetlab_app` con permisos mínimos (la app no debe usar `postgres`) |

## Seguridad: buenas prácticas para desplegar

- `config.php` no contiene credenciales: todo sale del `.env`. Con `APP_DEBUG=false` (por defecto) los errores no se muestran en pantalla.
- En un servidor con la base en otra máquina usa `DB_SSLMODE=require` en el `.env` (conexión cifrada con TLS).
- Conecta la aplicación con `crochetlab_app`, no con `postgres`:
  `psql -U postgres -d sistema_login -v clave=UnaContraseñaLarga -f database\usuario_app.sql` y luego `DB_USER` / `DB_PASS` en el `.env`.
  Vuelve a ejecutarlo después de cada migración que cree tablas.
- Sirve la aplicación por **HTTPS** y deja `database/`, `modelo/` y `controlador/` sin acceso directo desde el navegador.
- En producción pon `display_errors` en `0` y `APP_DEBUG=false`.
- Guarda `CIFRADO_CLAVE` en un lugar seguro y separado de los respaldos.
- Cambia las contraseñas de prueba antes de la entrega.

## Problemas frecuentes

| Síntoma | Causa y solución |
|---|---|
| `psql` / `php` no se reconocen | Agrega sus carpetas al PATH (ver arriba) |
| `Falta la clave de cifrado (CIFRADO_CLAVE ...)` | El `.env` no está en la carpeta padre o no tiene la línea `CIFRADO_CLAVE=` |
| `value too long for type character varying` al guardar un empleado | Falta aplicar `migracion_06_columnas_cifradas.sql` |
| Letras raras (`configuraciÃ³n`) en las descripciones de roles | Aplica `migracion_05_corregir_codificacion.sql` |
| `No se pudo conectar a la base de datos` | Revisa `DB_*` en el `.env`; con `APP_DEBUG=true` verás el detalle |
| `403` al abrir una pantalla | Tu rol no tiene ese permiso; un administrador lo ajusta en *Roles y permisos* |
