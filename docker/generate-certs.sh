#!/bin/sh
# Generate self-signed certificate for Docker/Local development

CERT_DIR="./docker/nginx/certs"
DOMAIN="localhost"

mkdir -p $CERT_DIR

if [ ! -f "$CERT_DIR/server.key" ] || [ ! -f "$CERT_DIR/server.crt" ]; then
    echo "Génération du certificat SSL auto-signé pour $DOMAIN..."
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout "$CERT_DIR/server.key" \
        -out "$CERT_DIR/server.crt" \
        -subj "/C=FR/ST=France/L=Paris/O=BastivanConsulting/OU=Dev/CN=$DOMAIN"
    echo "Certificats créés dans $CERT_DIR"
else
    echo "Les certificats existent déjà."
fi
