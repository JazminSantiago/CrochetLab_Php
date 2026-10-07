# Guía de despliegue en el laboratorio (VM + OPNsense)

Para quien monta la parte de **servidor y red** del proyecto CrochetLab.
Sigue los pasos en orden. Cada sección dice si se **probó** o **no se pudo probar** antes de escribirla.

| Parte | Estado |
|---|---|
| PostgreSQL con TLS obligatorio, usuario con permisos mínimos, la aplicación y los respaldos funcionando contra él | Probado (PHP 8.3 + PostgreSQL 16) |
| Comandos de instalación en Ubuntu, Apache con HTTPS, cron, `ufw` | **Sin probar aquí**: sigue la verificación de cada paso |
| Reglas de OPNsense | **Sin probar aquí**: los nombres de los menús pueden variar un poco según la versión |

---

## 0. Qué vas a montar

```
 Internet
    |
 [ OPNsense ]──────────────── LAN (clientes)            192.168.10.0/24
    |   \
    |    └──── Red intermedia "SERVIDORES"               192.168.20.0/24
    |                ├─ VM web   (Apache + PHP)          192.168.20.10
    |                └─ VM base  (PostgreSQL)            192.168.20.20
```

Las IPs son de ejemplo: cámbialas por las de tu laboratorio y úsalas igual en todos los pasos.

Lo que pide el proyecto:
- Servidor web y servidor de base de datos en la red intermedia.
- Los clientes **no pueden entrar a Facebook, Instagram ni X**.
- Los servidores tienen **internet libre**.
- Tráfico cifrado: **HTTPS** hacia la web y **TLS** entre la web y la base de datos.

Recomendación: **Ubuntu Server 24.04 LTS** en las dos VMs (trae PHP 8.3 y PostgreSQL 16, que son las versiones con las que se probó todo).

**Datos que necesitas decidir antes de empezar** (anótalos):

| Dato | Ejemplo |
|---|---|
| IP de la VM web | `192.168.20.10` |
| IP de la VM de base de datos | `192.168.20.20` |
| Contraseña del usuario de la base (`crochetlab_app`) | una larga y única |
| Nombre del servidor web | `crochetlab.lab` (o solo la IP) |

---

## 1. VM de base de datos (PostgreSQL)  ·  *probado*

### 1.1 Instalar y traer el proyecto
```bash
sudo apt update
sudo apt install -y postgresql git
git clone https://github.com/JazminSantiago/CrochetLab_Php.git ~/crochetlab_src
cd ~/crochetlab_src
```

### 1.2 Escuchar en la red y confirmar que TLS está activo
```bash
sudo nano /etc/postgresql/16/main/postgresql.conf
```
Busca `listen_addresses` y déjalo así (la IP de ESTA VM):
```
listen_addresses = 'localhost,192.168.20.20'
```
Ubuntu ya trae TLS activado con un certificado propio. Compruébalo:
```bash
sudo systemctl restart postgresql
sudo -u postgres psql -c "SHOW ssl;"        # debe decir: on
```

### 1.3 Crear la base, el esquema y las migraciones (en este orden)
```bash
sudo -u postgres createdb sistema_login
sudo -u postgres psql -d sistema_login -v ON_ERROR_STOP=1 < database/esquema_crochetlab.sql
for f in $(ls database/migraciones/migracion_*.sql | sort); do
  echo "== $f"; sudo -u postgres psql -q -d sistema_login -v ON_ERROR_STOP=1 < $f || break
done
```
Debe recorrer las migraciones 01 a 06 sin ningún `ERROR`.

### 1.4 Crear el usuario de la aplicación (permisos mínimos)
```bash
sudo -u postgres psql -d sistema_login -v clave='LaContraseñaLargaQueElegiste' < database/usuario_app.sql
```
Termina con «Listo: usuario crochetlab_app configurado».
(La aplicación **no** debe usar `postgres`: ese usuario puede borrar toda la base.)

### 1.5 Aceptar SOLO a la VM web, y solo con TLS
```bash
sudo nano /etc/postgresql/16/main/pg_hba.conf
```
Agrega esta línea al final:
```
hostssl  sistema_login  crochetlab_app  192.168.20.10/32  scram-sha-256
```
Significa: solo el usuario `crochetlab_app`, solo desde la VM web, solo con TLS y contraseña.
```bash
sudo systemctl restart postgresql
```

### 1.6 Cortafuegos de la VM
```bash
sudo ufw allow OpenSSH
sudo ufw allow from 192.168.20.10 to any port 5432 proto tcp
sudo ufw enable
sudo ufw status
```

---

## 2. VM web (Apache + PHP)  ·  *sin probar aquí*

### 2.1 Instalar
```bash
sudo apt update
sudo apt install -y apache2 php libapache2-mod-php php-pgsql php-mbstring php-xml php-curl git unzip openssl postgresql-client
sudo a2enmod ssl rewrite headers
php -v          # debe ser 8.2 o superior
php -m | grep -E "pdo_pgsql|openssl|mbstring"     # deben salir las tres
```

### 2.2 Traer el proyecto
```bash
sudo git clone https://github.com/JazminSantiago/CrochetLab_Php.git /var/www/crochetlab
cd /var/www/crochetlab
sudo chown -R root:www-data /var/www/crochetlab
sudo chmod -R g+rX /var/www/crochetlab
# carpetas donde la aplicación debe poder escribir
sudo mkdir -p assets/uploads correos_salida
sudo chown www-data:www-data assets/uploads correos_salida
sudo chmod 770 assets/uploads correos_salida
```

### 2.3 Archivo `.env` (credenciales y clave de cifrado)
El `.env` va en la carpeta **padre** del proyecto (`/var/www/.env`): así queda fuera de lo que sirve Apache.
```bash
sudo cp /var/www/crochetlab/env.ejemplo /var/www/.env
KEY=$(php /var/www/crochetlab/database/generar_clave.php)
sudo sed -i "s|^CIFRADO_CLAVE=.*|$KEY|" /var/www/.env
sudo nano /var/www/.env
```
En el editor revisa y completa estas líneas (el resto puede quedar igual):
```
APP_URL=https://192.168.20.10
DB_HOST=192.168.20.20
DB_PORT=5432
DB_NAME=sistema_login
DB_USER=crochetlab_app
DB_PASS=LaContraseñaLargaQueElegiste
DB_SSLMODE=require
APP_DEBUG=false
ADMIN_REQUIERE_2FA=true
```
Protege el archivo:
```bash
sudo chown root:www-data /var/www/.env
sudo chmod 640 /var/www/.env
```
**Guarda una copia de `CIFRADO_CLAVE` en un lugar seguro** (fuera de las VMs). Sin ella no se pueden leer los datos cifrados ni los respaldos.

### 2.4 Comprobar la conexión con la base (antes de configurar Apache)
```bash
cd /var/www/crochetlab
sudo -u www-data php -r 'error_reporting(0); require "modelo/conexion.php"; $c=(new Conexion())->conectar(); echo "usuario=".$c->query("select current_user")->fetchColumn()." | TLS=".($c->query("select ssl from pg_stat_ssl where pid=pg_backend_pid()")->fetchColumn()?"si":"no")."\n";'
```
Debe imprimir `usuario=crochetlab_app | TLS=si`.
Si falla: revisa `DB_*` en el `.env`, la línea de `pg_hba.conf`, el `ufw` de la VM de base y la regla de OPNsense del paso 4. Para ver el detalle pon `APP_DEBUG=true` un momento.

### 2.5 Crear el primer administrador
```bash
cd /var/www/crochetlab
sudo -u www-data php database/crear_admin.php
```
Te pide usuario, nombre, correo y contraseña. **Anota el usuario y la contraseña** y pásaselos a Jazmín.

### 2.6 Certificado HTTPS (autofirmado)
```bash
sudo mkdir -p /etc/ssl/crochetlab
sudo openssl req -x509 -nodes -newkey rsa:2048 -days 365 \
  -keyout /etc/ssl/crochetlab/crochetlab.key -out /etc/ssl/crochetlab/crochetlab.crt \
  -subj "/CN=crochetlab.lab" -addext "subjectAltName=IP:192.168.20.10,DNS:crochetlab.lab"
sudo chmod 600 /etc/ssl/crochetlab/crochetlab.key
```
(Cambia la IP y el nombre por los tuyos. Los navegadores avisarán que el certificado no es de confianza porque es autofirmado: es normal en el laboratorio; se acepta la excepción.)

### 2.7 Sitio de Apache con HTTPS y redirección
```bash
sudo nano /etc/apache2/sites-available/crochetlab.conf
```
Contenido:
```apache
<VirtualHost *:80>
    ServerName 192.168.20.10
    RewriteEngine On
    RewriteRule ^(.*)$ https://%{HTTP_HOST}$1 [R=301,L]
</VirtualHost>

<VirtualHost *:443>
    ServerName 192.168.20.10
    DocumentRoot /var/www/crochetlab

    SSLEngine on
    SSLCertificateFile    /etc/ssl/crochetlab/crochetlab.crt
    SSLCertificateKeyFile /etc/ssl/crochetlab/crochetlab.key
    SSLProtocol -all +TLSv1.2 +TLSv1.3

    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"

    <Directory /var/www/crochetlab>
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>

    # Carpetas internas y archivos que nunca deben descargarse desde el navegador
    <DirectoryMatch "^/var/www/crochetlab/(database|modelo|docs|correos_salida|\.git)(/|$)">
        Require all denied
    </DirectoryMatch>
    <FilesMatch "(\.(sql|md|bat|log|ejemplo)|^\.env)$">
        Require all denied
    </FilesMatch>

    ErrorLog  ${APACHE_LOG_DIR}/crochetlab_error.log
    CustomLog ${APACHE_LOG_DIR}/crochetlab_access.log combined
</VirtualHost>
```
Actívalo:
```bash
sudo a2dissite 000-default
sudo a2ensite crochetlab
sudo apachectl configtest         # debe decir: Syntax OK
sudo systemctl reload apache2
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```
Opcional: `echo "expose_php=Off" | sudo tee /etc/php/8.3/apache2/conf.d/99-crochetlab.ini && sudo systemctl reload apache2` (ajusta `8.3` a tu versión con `ls /etc/php`).

### 2.8 Verificar
Desde la propia VM:
```bash
curl -sI http://192.168.20.10 | head -3                 # 301 y Location: https://...
curl -skI https://192.168.20.10/index.php | head -3     # 200
curl -sk -o /dev/null -w "%{http_code}\n" https://192.168.20.10/database/crear_admin.php    # 403
curl -sk -o /dev/null -w "%{http_code}\n" https://192.168.20.10/.env                         # 403 o 404
```
Luego abre `https://192.168.20.10` en un navegador, acepta la advertencia del certificado e inicia sesión con el administrador del paso 2.5. Como es administrador, el sistema te pedirá activar la verificación en dos pasos en *Mi cuenta* (necesitas una app como Google Authenticator).

---

## 3. Respaldos automáticos cada 24 h (VM web)  ·  *el script está probado; cron y USB no*

1. Conecta el USB o disco a la VM (en VirtualBox/VMware: dispositivos USB → agregar el dispositivo) y móntalo:
   ```bash
   lsblk                                     # identifica el disco, por ejemplo sdb1
   sudo mkdir -p /mnt/usb
   sudo mount /dev/sdb1 /mnt/usb
   sudo mkdir -p /mnt/usb/respaldos_crochetlab
   ```
2. En `/var/www/.env` agrega (la carpeta debe estar **dentro del USB**; así, si el USB no está montado, el script avisa en vez de escribir en el disco de la VM):
   ```
   RESPALDO_DESTINO=/mnt/usb/respaldos_crochetlab
   RESPALDO_CONSERVAR=14
   ```
3. Prueba a mano:
   ```bash
   cd /var/www/crochetlab && sudo php database/respaldo.php
   ```
   Debe decir `OK: crochetlab_... (xx KB, verificado)` y el archivo debe verse con `ls /mnt/usb/respaldos_crochetlab`.
   (Necesita `pg_dump` de la versión 16 o más nueva: `postgresql-client` de Ubuntu 24.04 ya lo es.)
4. Programa el respaldo diario a las 2:00:
   ```bash
   sudo crontab -e
   ```
   y agrega:
   ```
   0 2 * * * cd /var/www/crochetlab && /usr/bin/php database/respaldo.php >> /var/log/crochetlab_respaldo.log 2>&1
   ```
5. **Prueba de restauración** (es la evidencia de que el respaldo sirve). La aplicación usa un usuario sin permiso para crear bases, así que para restaurar se crea un rol temporal que **solo puede crear bases** y se borra al terminar. *(Este procedimiento se probó con TLS y contraseña.)*

   En la **VM de base de datos**:
   ```bash
   sudo -u postgres psql -c "CREATE ROLE crochetlab_restaura LOGIN CREATEDB PASSWORD 'OtraContraseñaTemporal'"
   echo "hostssl all crochetlab_restaura 192.168.20.10/32 scram-sha-256" | sudo tee -a /etc/postgresql/16/main/pg_hba.conf
   sudo systemctl reload postgresql
   ```
   En la **VM web** (la contraseña va solo en este comando; no se guarda en el `.env`):
   ```bash
   cd /var/www/crochetlab
   sudo DB_USER=crochetlab_restaura DB_PASS='OtraContraseñaTemporal' php database/restaurar_respaldo.php /mnt/usb/respaldos_crochetlab/crochetlab_XXXX.dump.enc --db=sistema_login_prueba
   ```
   Debe decir «Respaldo restaurado en la base "sistema_login_prueba"». Compárala con la original (**captura de pantalla**):
   ```bash
   # en la VM de base de datos
   sudo -u postgres psql -d sistema_login_prueba -c "SELECT count(*) FROM usuarios;"
   ```
   Al terminar, limpia la prueba y el rol temporal (en la VM de base de datos):
   ```bash
   sudo -u postgres dropdb sistema_login_prueba
   sudo -u postgres psql -c "DROP ROLE crochetlab_restaura"
   sudo sed -i '/crochetlab_restaura/d' /etc/postgresql/16/main/pg_hba.conf && sudo systemctl reload postgresql
   ```

---

## 4. Red: OPNsense  ·  *sin probar aquí*

### 4.1 Interfaz de la red intermedia
- *Interfaces → Assignments*: agrega la tarjeta de red de la red intermedia (por ejemplo `OPT1`) y renómbrala **SERVIDORES**.
- Actívala (*Enable*), IPv4 *Static*, `192.168.20.1/24`.
- Conecta las dos VMs a esa red; en cada una pon IP fija (`.10` web, `.20` base), *gateway* `192.168.20.1` y DNS `192.168.20.1`.

### 4.2 Alias (*Firewall → Aliases*)
| Nombre | Tipo | Contenido |
|---|---|---|
| `SERVIDOR_WEB` | Host(s) | `192.168.20.10` |
| `SERVIDOR_BD` | Host(s) | `192.168.20.20` |
| `RED_SERVIDORES` | Network(s) | `192.168.20.0/24` |
| `PUERTOS_WEB` | Port(s) | `80`, `443` |
| `REDES_SOCIALES` | BGP ASN | `AS32934` (Meta: Facebook e Instagram) y `AS13414` (X) |

Nota: el ASN de Meta también incluye WhatsApp, así que se bloquea con ellos. Si eso es un problema, deja solo el bloqueo por DNS del paso 4.4.

### 4.3 Reglas (*Firewall → Rules*). **El orden importa: gana la primera que coincide.**

**Interfaz LAN (clientes)**
| # | Acción | Origen | Destino | Puerto |
|---|---|---|---|---|
| 1 | Pass | `LAN net` | `SERVIDOR_WEB` | `PUERTOS_WEB` |
| 2 | Block (log) | `LAN net` | `RED_SERVIDORES` | cualquiera |
| 3 | Block (log) | `LAN net` | `REDES_SOCIALES` | cualquiera |
| 4 | Pass | `LAN net` | cualquiera | cualquiera |

Los clientes llegan a la web por 80/443, no pueden tocar la base de datos ni el resto de los servidores, no llegan a las redes sociales y el resto de internet es libre.

**Interfaz SERVIDORES**
| # | Acción | Origen | Destino | Puerto |
|---|---|---|---|---|
| 1 | Pass | `SERVIDOR_WEB` | `SERVIDOR_BD` | TCP `5432` |
| 2 | Block (log) | `RED_SERVIDORES` | `LAN net` | cualquiera |
| 3 | Pass | `RED_SERVIDORES` | cualquiera | cualquiera |

Los servidores tienen internet libre (actualizaciones, `apt`), la web habla con la base por 5432 y los servidores no inician conexiones hacia los clientes.

Aplica los cambios (*Apply changes*) después de cada regla nueva.
Revisa que *Firewall → NAT → Outbound* esté en modo automático, para que las dos redes salgan a internet.

### 4.4 Refuerzo por DNS (opcional)
*Services → Unbound DNS → Blocklist*: activa la lista y agrega a los dominios bloqueados (con subdominios):
`facebook.com`, `fb.com`, `fbcdn.net`, `instagram.com`, `cdninstagram.com`, `x.com`, `twitter.com`, `twimg.com`, `t.co`.
(Según la versión, los campos se llaman *Blocklist Domains* y *Wildcard Domains*. Si tu versión permite limitar la lista a una red de origen, elige solo la LAN.)

### 4.5 Verificar desde un cliente (red LAN)
```powershell
# Windows (PowerShell)
Test-NetConnection 192.168.20.10 -Port 443     # debe dar TcpTestSucceeded : True
Test-NetConnection 192.168.20.20 -Port 5432    # debe dar False (la base no es alcanzable)
curl.exe -I --max-time 8 https://www.facebook.com    # debe fallar / agotar tiempo
curl.exe -I --max-time 8 https://www.instagram.com   # igual
curl.exe -I --max-time 8 https://x.com               # igual
curl.exe -I --max-time 8 https://www.wikipedia.org   # debe responder 200 (internet libre)
```
Y desde la VM web: `curl -I https://www.wikipedia.org` debe responder (internet libre del servidor) y `ping 192.168.10.x` a un cliente no debe responder.
En *Firewall → Log Files → Live View* verás los bloqueos de las reglas con «(log)».

---

## 5. Evidencia del cifrado en red (Wireshark)  ·  *sin probar aquí*

### 5.1 HTTPS (web)
1. En la VM web: `sudo tcpdump -i any -w /tmp/https.pcap port 443` (déjalo corriendo), entra al sitio desde un cliente e inicia sesión.
2. Corta con `Ctrl+C`, copia `/tmp/https.pcap` a tu equipo y ábrelo en Wireshark.
3. Filtro `tls.handshake.type == 1` (Client Hello) y luego mira «Application Data»: el contenido es ilegible. **Captura de pantalla.**

### 5.2 HTTP (para el contraste)
Para mostrar qué se vería **sin** cifrado, crea un sitio temporal solo para esta prueba (usa un usuario de prueba, nunca uno real):
```bash
echo "Listen 8080" | sudo tee /etc/apache2/ports-demo.conf >/dev/null
sudo bash -c 'cat > /etc/apache2/sites-available/demo-http.conf' <<'EOF'
<VirtualHost *:8080>
    DocumentRoot /var/www/crochetlab
    <Directory /var/www/crochetlab>
        Require all granted
    </Directory>
</VirtualHost>
EOF
sudo bash -c 'grep -q ports-demo /etc/apache2/apache2.conf || echo "IncludeOptional ports-demo.conf" >> /etc/apache2/apache2.conf'
sudo a2ensite demo-http && sudo ufw allow 8080/tcp && sudo systemctl restart apache2
```
Captura `sudo tcpdump -i any -w /tmp/http.pcap port 8080`, entra a `http://192.168.20.10:8080` y envía el formulario de inicio de sesión (el sistema lo rechazará por seguridad de la cookie, pero **la petición ya viajó en claro**). En Wireshark: filtro `http.request.method == "POST"` → clic derecho → *Follow → TCP Stream*: se ven el usuario y la contraseña. **Captura de pantalla.**
**Al terminar, borra el sitio temporal:**
```bash
sudo a2dissite demo-http && sudo rm /etc/apache2/sites-available/demo-http.conf /etc/apache2/ports-demo.conf
sudo sed -i '/ports-demo.conf/d' /etc/apache2/apache2.conf
sudo ufw delete allow 8080/tcp && sudo systemctl restart apache2
```

### 5.3 TLS entre la web y la base de datos
En la VM de base de datos: `sudo tcpdump -i any -w /tmp/bd.pcap port 5432`; inicia sesión en el sitio desde un cliente y corta. En Wireshark filtro `tcp.port == 5432`: tras el saludo inicial todo es TLS, sin texto legible. Además, desde la VM web:
```bash
sudo -u www-data php -r 'error_reporting(0); require "/var/www/crochetlab/modelo/conexion.php"; $c=(new Conexion())->conectar(); print_r($c->query("select ssl, version, cipher from pg_stat_ssl where pid=pg_backend_pid()")->fetch(PDO::FETCH_ASSOC));'
```
Debe mostrar `ssl = 1` y la versión TLS. **Captura de pantalla.**

---

## 6. Actualizar el proyecto más adelante
```bash
cd /var/www/crochetlab && sudo git pull
```
- Si el cambio trae **migraciones nuevas**: en la VM de base de datos aplícalas (`git pull` allí también) con `sudo -u postgres psql -d sistema_login -v ON_ERROR_STOP=1 < database/migraciones/migracion_0X_....sql` y vuelve a ejecutar `usuario_app.sql` (el paso 1.4) para que el usuario de la aplicación reciba permisos sobre las tablas nuevas.
- Recarga Apache solo si cambiaste su configuración.

---

## 7. Lista de evidencias para el documento

| Requisito | Evidencia |
|---|---|
| Servidor web y de base de datos en la red intermedia | Diagrama de red + captura de las VMs con sus IPs + captura de la interfaz SERVIDORES en OPNsense |
| Clientes sin Facebook, Instagram y X | Captura de las reglas y alias, y de `curl` fallando desde un cliente, más el log de bloqueo |
| Servidores con internet libre | Captura de `curl https://www.wikipedia.org` desde la VM web |
| Cifrado en red (HTTPS) | Capturas de Wireshark del 5.1 y del 5.2; captura del candado / certificado en el navegador |
| Cifrado entre web y base de datos | Captura del 5.3 |
| Base de datos protegida | Captura de `pg_hba.conf`, del `ufw` y de `Test-NetConnection ... 5432` fallando desde un cliente |
| Respaldos cada 24 h | Captura del `crontab -l`, de un respaldo en el USB y de la restauración exitosa |

## 8. Problemas frecuentes

| Síntoma | Qué revisar |
|---|---|
| `No se pudo conectar a la base de datos` | `DB_*` en `/var/www/.env`; `pg_hba.conf`; `ufw` de la VM de base; regla 1 de SERVIDORES en OPNsense; con `APP_DEBUG=true` se ve el detalle |
| `pg_hba.conf rejects connection ... no encryption` | Falta `DB_SSLMODE=require` en el `.env` |
| `password authentication failed` | La contraseña de `DB_PASS` no es la que pusiste en el paso 1.4 (se puede repetir ese paso con otra) |
| `Falta la clave de cifrado (CIFRADO_CLAVE ...)` | El `.env` no está en `/var/www/` o su línea `CIFRADO_CLAVE=` está vacía |
| Página en blanco o error 500 | `sudo tail /var/log/apache2/crochetlab_error.log` |
| 403 en una pantalla del sistema | Es el control de permisos: tu rol no tiene acceso; el administrador lo ajusta en *Roles y permisos* |
| Los respaldos dicen «destino no disponible» | El USB no está montado: `lsblk` y `sudo mount ...` |
