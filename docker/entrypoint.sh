#!/bin/sh
set -e

DATA_DIR="${BATONS_DATA_DIR:-/data}"
mkdir -p "$DATA_DIR"
chown -R nobody:nobody "$DATA_DIR"

echo "Bâtons : données dans $DATA_DIR, authentification « ${BATONS_AUTH:-none} », port 8080"

php-fpm -D
exec nginx -g 'daemon off;'
