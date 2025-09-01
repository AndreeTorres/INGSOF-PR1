#!/usr/bin/env bash
set -e
CRT="/etc/ssl/certs/server.crt"
KEY="/etc/ssl/private/server.key"
CN="${SERVER_NAME:-localhost}"

mkdir -p /etc/ssl/certs /etc/ssl/private

# Genera certificado auto-firmado si no existe
if [ ! -f "$CRT" ] || [ ! -f "$KEY" ]; then
  echo ">> Generando certificado auto-firmado para CN=$CN ..."
  openssl req -x509 -nodes -newkey rsa:2048 -days 365 \
    -keyout "$KEY" -out "$CRT" \
    -subj "/CN=$CN" \
    -addext "subjectAltName=DNS:$CN,DNS:localhost,IP:127.0.0.1" >/dev/null 2>&1
  chmod 600 "$KEY"
fi

echo ">> Iniciando Nginx (HTTP/2 + TLS)"
exec nginx -g 'daemon off;'
