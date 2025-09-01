# Stack de Desarrollo LEMP con CentOS Stream 9

Este proyecto configura un stack completo LEMP (Linux, Nginx, MariaDB, PHP) usando contenedores Docker basados en CentOS Stream 9. La configuración incluye SSL/TLS, HTTP/2 y está optimizada para desarrollo web moderno.

## 1. Preparación del Entorno

Primero necesitamos crear la estructura de directorios que organizará todos los archivos de configuración de nuestros servicios:

```bash
mkdir -p ./centos-stack/{nginx,php,mariadb,app,certs,nginx/conf.d}
```

Esta estructura organiza los componentes de la siguiente manera:
- `nginx/`: Configuraciones y Dockerfile del servidor web
- `nginx/conf.d/`: Configuraciones específicas de sitios virtuales
- `php/`: Dockerfile y configuraciones del intérprete PHP-FPM
- `mariadb/`: Dockerfile y scripts de inicialización de la base de datos
- `app/`: Código fuente de la aplicación web
- `certs/`: Certificados SSL (generados automáticamente)
## 2. Configuración de Servicios Docker

### 2.1 Servidor Web Nginx

#### 2.1.1 Dockerfile de Nginx

El servidor web Nginx actúa como proxy reverso y maneja todas las peticiones HTTP/HTTPS. Utilizamos CentOS Stream 9 como base por su estabilidad empresarial y soporte a largo plazo.

```dockerfile
FROM quay.io/centos/centos:stream9
ENV TERM=xterm LC_ALL=en_US.UTF-8 LANG=en_US.UTF-8
RUN dnf -y update && dnf -y install nginx openssl && dnf clean all
RUN mkdir -p /var/www/html /etc/nginx/conf.d /etc/ssl/private /etc/ssl/certs
COPY nginx.conf /etc/nginx/nginx.conf
COPY conf.d/site.conf /etc/nginx/conf.d/site.conf
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
EXPOSE 80 443
STOPSIGNAL SIGQUIT
CMD ["/entrypoint.sh"]
```

**Explicación detallada:**
- Utilizamos la imagen oficial de Red Hat CentOS Stream 9 desde Quay.io
- Las variables de entorno configuran la localización en inglés para evitar conflictos de codificación
- Instalamos Nginx para servir contenido web y OpenSSL para generar certificados SSL
- Creamos directorios esenciales: documentos web, configuraciones y certificados SSL
- Copiamos archivos de configuración personalizados que definiremos después
- Exponemos puertos 80 (HTTP) y 443 (HTTPS) para tráfico web
- SIGQUIT permite paradas elegantes del servidor
#### 2.1.2 Configuración Principal de Nginx

El archivo `nginx.conf` establece la configuración global del servidor. Esta configuración optimiza el rendimiento y habilita funciones s:

```nginx
user nginx;
worker_processes auto;

events { worker_connections 1024; }

http {
    include       /etc/nginx/mime.types;
    default_type  application/octet-stream;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';
    access_log  /var/log/nginx/access.log main;
    error_log   /var/log/nginx/error.log warn;

    sendfile on;
    tcp_nopush on;
    keepalive_timeout 65;
    gzip on;

    include /etc/nginx/conf.d/*.conf;
}
```

**Funcionalidades implementadas:**
- `worker_processes auto`: Nginx detecta automáticamente el número de núcleos CPU disponibles
- `worker_connections 1024`: Cada proceso puede manejar hasta 1024 conexiones simultáneas  
- `sendfile on`: Optimización que permite transferir archivos directamente desde el kernel
- `tcp_nopush on`: Mejora el rendimiento agrupando datos en menos paquetes TCP
- `gzip on`: Compresión automática de respuestas para reducir el ancho de banda
- Formato de logs detallado que incluye IP, usuario, petición y headers importantes
#### 2.1.3 Configuración del Sitio Web con SSL y PHP-FPM

El archivo `site.conf` implementa una configuración de seguridad  que fuerza HTTPS y habilita HTTP/2. También configura la integración con PHP-FPM:

```nginx
# Servidor HTTP - Redirección obligatoria a HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name _;

    # Excepción para Let's Encrypt (renovación automática de certificados)
    location ^~ /.well-known/acme-challenge/ {
        root /var/www/html;
    }

    # Redirigir todo el tráfico HTTP a HTTPS
    location / {
        return 301 https://$host$request_uri;
    }
}

# Servidor HTTPS principal con HTTP/2 y PHP
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name _;

    root /var/www/html;
    index index.php index.html;

    # Configuración SSL 
    ssl_certificate     /etc/ssl/certs/server.crt;
    ssl_certificate_key /etc/ssl/private/server.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;

    # Headers de seguridad HTTP
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options SAMEORIGIN;
    add_header X-XSS-Protection "1; mode=block";

    # Procesamiento de archivos PHP via FastCGI
    location ~ \.php$ {
        include /etc/nginx/fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass php:9000;  # Conecta con el contenedor PHP-FPM
    }

    # Manejo de URLs amigables
    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    access_log /var/log/nginx/site_access.log;
    error_log  /var/log/nginx/site_error.log warn;
}
```

**Características de seguridad implementadas:**
- **Redirección forzada a HTTPS**: Todo el tráfico HTTP se redirige automáticamente
- **HTTP/2**: Protocolo moderno que mejora significativamente el rendimiento
- **HSTS**: Fuerza a los navegadores a usar HTTPS durante un año
- **Protección XSS**: Previene ataques de scripts maliciosos
- **Protección contra clickjacking**: Evita que el sitio sea embebido en iframes maliciosos
- **PHP-FPM**: Comunicación eficiente con PHP a través del puerto 9000

#### 2.1.4 Script de Inicialización de Nginx

El script `entrypoint.sh` automatiza la generación de certificados SSL y el inicio del servidor. Este enfoque garantiza que el contenedor funcione correctamente desde el primer arranque:

```bash
#!/usr/bin/env bash
set -e
CRT="/etc/ssl/certs/server.crt"
KEY="/etc/ssl/private/server.key"
CN="${SERVER_NAME:-localhost}"

mkdir -p /etc/ssl/certs /etc/ssl/private

# Genera certificado auto-firmado si no existe
if [ ! -f "$CRT" ] || [ ! -f "$KEY" ]; then
  openssl req -x509 -nodes -newkey rsa:2048 -days 365 \
    -keyout "$KEY" -out "$CRT" \
    -subj "/CN=$CN" \
    -addext "subjectAltName=DNS:$CN,DNS:localhost,IP:127.0.0.1" >/dev/null 2>&1
  chmod 600 "$KEY"
fi
exec nginx -g 'daemon off;'
```

**Proceso de inicialización:**
1. **Verificación de certificados**: Comprueba si ya existen certificados SSL válidos
2. **Generación automática**: Si no existen, crea un certificado auto-firmado de 2048 bits válido por 365 días
3. **SAN (Subject Alternative Names)**: El certificado incluye localhost e IP local para máxima compatibilidad
4. **Seguridad de archivos**: La clave privada se protege con permisos restrictivos (600)
5. **Inicio en primer plano**: Nginx se ejecuta sin daemon para mantener el contenedor activo

Este certificado auto-firmado es perfecto para desarrollo, pero en producción debería reemplazarse con uno de una autoridad certificadora confiable.
### 2.2 Intérprete PHP-FPM

#### 2.2.1 Dockerfile de PHP

PHP-FPM (FastCGI Process Manager) ofrece un rendimiento superior al módulo tradicional de Apache. Utilizamos PHP 8.2 del repositorio Remi, que proporciona versiones actualizadas y optimizadas:

```dockerfile
FROM quay.io/centos/centos:stream9
ENV TERM=xterm LC_ALL=en_US.UTF-8 LANG=en_US.UTF-8

# Repositorio Remi para PHP 8.2 moderno
RUN dnf -y update && dnf -y install dnf-plugins-core curl && \
    dnf -y install https://rpms.remirepo.net/enterprise/remi-release-9.rpm && \
    dnf -y module reset php && dnf -y module enable php:remi-8.2 && \
    dnf -y install php-fpm php-cli php-common php-mysqlnd php-opcache php-gd php-xml php-mbstring && \
    dnf clean all

# Configuración PHP-FPM para contenedores Docker
RUN sed -ri 's|^;?listen\s*=.*|listen = 0.0.0.0:9000|' /etc/php-fpm.d/www.conf && \
    sed -ri 's|^;?clear_env\s*=.*|clear_env = no|' /etc/php-fpm.d/www.conf

WORKDIR /var/www/html
EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=5s --retries=5 CMD php-fpm -t
CMD ["php-fpm","-F"]
```

**Componentes instalados y su propósito:**
- **php-fpm**: El proceso manager principal que maneja peticiones FastCGI
- **php-mysqlnd**: Driver nativo optimizado para conectar con MySQL/MariaDB
- **php-opcache**: Acelerador que cachea bytecode compilado para mejor rendimiento
- **php-gd**: Biblioteca para manipulación de imágenes (PNG, JPEG, GIF)
- **php-xml**: Procesamiento de documentos XML y configuraciones
- **php-mbstring**: Soporte completo para caracteres multibyte (UTF-8, etc.)

**Configuraciones específicas para Docker:**
- **Puerto 0.0.0.0:9000**: PHP-FPM acepta conexiones desde cualquier IP del contenedor
- **clear_env = no**: Permite que variables de entorno del contenedor sean accesibles en PHP
- **Healthcheck**: Verifica cada 30 segundos que PHP-FPM responde correctamente
- **Modo foreground**: Evita que el proceso se ejecute como daemon para mantener el contenedor activo

### 2.3 Base de Datos MariaDB

#### 2.3.1 Dockerfile de MariaDB

MariaDB es un fork de MySQL que ofrece mejor rendimiento y características avanzadas. La configuración incluye monitoreo de salud y inicialización automática:

```dockerfile
FROM quay.io/centos/centos:stream9
ENV TERM=xterm LC_ALL=en_US.UTF-8 LANG=en_US.UTF-8 MARIADB_DATA_DIR=/var/lib/mysql

RUN dnf -y update && dnf -y install mariadb-server procps-ng which && dnf clean all

# Compatibilidad entre versiones (mariadbd vs mysqld)
RUN command -v mariadbd >/dev/null 2>&1 || (ln -s "$(command -v mysqld)" /usr/sbin/mariadbd)

COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

VOLUME ["/var/lib/mysql"]
EXPOSE 3306

HEALTHCHECK --interval=30s --timeout=5s --retries=5 CMD mysqladmin ping -h 127.0.0.1 -uroot -p"$MARIADB_ROOT_PASSWORD" || exit 1
CMD ["/docker-entrypoint.sh"]
```

**Herramientas adicionales instaladas:**
- **procps-ng**: Proporciona herramientas de monitoreo de procesos (`ps`, `top`, `kill`)
- **which**: Comando para localizar ejecutables en el PATH del sistema

**Características del contenedor:**
- **Volumen persistente**: `/var/lib/mysql` mantiene los datos entre reinicios
- **Puerto estándar**: 3306 es el puerto predeterminado para MySQL/MariaDB
- **Healthcheck inteligente**: Usa `mysqladmin ping` para verificar que la base esté respondiendo
- **Script personalizado**: `docker-entrypoint.sh` maneja la inicialización completa
#### 2.3.2 Script de Inicialización de MariaDB

El script de entrypoint automatiza completamente la configuración inicial de la base de datos, creando usuarios, estableciendo contraseñas y configurando permisos:

```bash
#!/usr/bin/env bash
set -euo pipefail

# Variables de configuración con valores por defecto
DATA_DIR="${MARIADB_DATA_DIR:-/var/lib/mysql}"
ROOT_PW="${MARIADB_ROOT_PASSWORD:-rootpassword}"
DB_NAME="${MARIADB_DATABASE:-appdb}"
DB_USER="${MARIADB_USER:-appuser}"
DB_PW="${MARIADB_PASSWORD:-apppassword}"

# Preparación del directorio de datos
mkdir -p "$DATA_DIR"
chown -R mysql:mysql "$DATA_DIR"

# Inicialización solo si es la primera ejecución
if [ ! -d "$DATA_DIR/mysql" ]; then
  mariadb-install-db --user=mysql --datadir="$DATA_DIR" >/dev/null

  # Script SQL temporal para configuración inicial
  INIT_SQL=$(mktemp)
  cat > "$INIT_SQL" <<SQL
FLUSH PRIVILEGES;
ALTER USER 'root'@'localhost' IDENTIFIED BY '${ROOT_PW}';
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PW}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
SQL

  # Ejecución del script en modo bootstrap (sin red)
  mariadbd --datadir="$DATA_DIR" --user=mysql --skip-networking=1 --socket=/run/mariadb-bootstrap.sock --pid-file=/tmp/mariadb-bootstrap.pid --bootstrap < "$INIT_SQL"
  rm -f "$INIT_SQL"
fi

exec mariadbd --datadir="$DATA_DIR" --user=mysql --bind-address=0.0.0.0
```

**Proceso de inicialización detallado:**

1. **Validación de variables**: El script utiliza variables de entorno con valores predeterminados seguros
2. **Verificación de primera ejecución**: Comprueba si existe el directorio `mysql` (indicador de base inicializada)
3. **Inicialización de estructura**: `mariadb-install-db` crea las tablas del sistema necesarias
4. **Configuración de seguridad**:
   - Establece contraseña para el usuario root
   - Crea una base de datos para la aplicación con codificación UTF-8 
   - Crea un usuario específico con acceso completo solo a esa base
   - Aplica el principio de menor privilegio (el usuario de app no es root)
5. **Modo bootstrap**: La configuración inicial se ejecuta sin red para mayor seguridad
6. **Inicio del servidor**: MariaDB se inicia aceptando conexiones desde cualquier IP del contenedor

La configuración UTF-8 (`utf8mb4_unicode_ci`) es crucial para aplicaciones s, ya que soporta emojis y caracteres especiales internacionales.
## 3. Uso del Stack

### 3.1 Estructura Final del Proyecto

Al completar todos los pasos anteriores, tendrás la siguiente estructura:

```
centos-stack/
├── app/                   
├── certs/          # Certificados SSL (generados automáticamente)
├── nginx/
│   ├── Dockerfile
│   ├── nginx.conf
│   ├── entrypoint.sh
│   └── conf.d/
│       └── site.conf
├── php/
│   └── Dockerfile
└── mariadb/
    ├── Dockerfile
    └── docker-entrypoint.sh
```

### 3.2 Docker Compose

Para orquestar todos estos servicios, se necesitará un archivo `docker-compose.yml`:

```yaml
version: '3.8'
services:
  nginx:
    build: ./nginx
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./app:/var/www/html
      - ./certs:/etc/ssl/certs
    depends_on:
      - php
    networks:
      - lemp-network

  php:
    build: ./php
    volumes:
      - ./app:/var/www/html
    depends_on:
      - mariadb
    environment:
      - DB_HOST=mariadb
      - DB_NAME=appdb
      - DB_USER=appuser
      - DB_PASSWORD=apppassword
    networks:
      - lemp-network

  mariadb:
    build: ./mariadb
    volumes:
      - mariadb_data:/var/lib/mysql
    environment:
      - MARIADB_ROOT_PASSWORD=rootpassword
      - MARIADB_DATABASE=appdb
      - MARIADB_USER=appuser
      - MARIADB_PASSWORD=apppassword
    networks:
      - lemp-network

volumes:
  mariadb_data:

networks:
  lemp-network:
    driver: bridge
```

### 3.3 Comandos de Uso

```bash
# Construir y levantar todos los servicios
docker-compose up -d --build

# Ver logs de todos los servicios
docker-compose logs -f

# Parar todos los servicios
docker-compose down

# Parar y eliminar volúmenes y base de datos
docker-compose down -v
```

