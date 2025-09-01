#!/usr/bin/env bash
set -euo pipefail

DATA_DIR="${MARIADB_DATA_DIR:-/var/lib/mysql}"
ROOT_PW="${MARIADB_ROOT_PASSWORD:-rootpassword}"
DB_NAME="${MARIADB_DATABASE:-appdb}"
DB_USER="${MARIADB_USER:-appuser}"
DB_PW="${MARIADB_PASSWORD:-apppassword}"

mkdir -p "$DATA_DIR"
chown -R mysql:mysql "$DATA_DIR"

if [ ! -d "$DATA_DIR/mysql" ]; then
  echo ">> Inicializando datadir de MariaDB..."
  mariadb-install-db --user=mysql --datadir="$DATA_DIR" >/dev/null

  INIT_SQL=$(mktemp)
  cat > "$INIT_SQL" <<SQL
FLUSH PRIVILEGES;
ALTER USER 'root'@'localhost' IDENTIFIED BY '${ROOT_PW}';
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PW}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
SQL

  mariadbd --datadir="$DATA_DIR" --user=mysql --skip-networking=1 --socket=/run/mariadb-bootstrap.sock --pid-file=/tmp/mariadb-bootstrap.pid --bootstrap < "$INIT_SQL"
  rm -f "$INIT_SQL"
fi

echo ">> Iniciando MariaDB..."
exec mariadbd --datadir="$DATA_DIR" --user=mysql --bind-address=0.0.0.0
