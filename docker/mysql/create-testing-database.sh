#!/usr/bin/env bash

set -e

mysql --user=root --password="$MYSQL_ROOT_PASSWORD" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`lineup_testing\`;
EOSQL

if [ -n "$MYSQL_USER" ]; then
mysql --user=root --password="$MYSQL_ROOT_PASSWORD" <<-EOSQL
    GRANT ALL PRIVILEGES ON \`lineup_testing\`.* TO '$MYSQL_USER'@'%';
EOSQL
fi
