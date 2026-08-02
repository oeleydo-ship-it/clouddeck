#!/usr/bin/env bash
set -Eeuo pipefail
ACTION={{ACTION}}
ENGINE={{ENGINE}}
DATABASE={{DATABASE}}
USERNAME={{USERNAME}}
PASSWORD={{PASSWORD}}

if [ "${ENGINE}" = "mysql" ]; then
    if [ "${ACTION}" = "create" ]; then
        mysql --protocol=socket <<SQL
CREATE DATABASE IF NOT EXISTS \`${DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${USERNAME}'@'localhost' IDENTIFIED BY '${PASSWORD}';
ALTER USER '${USERNAME}'@'localhost' IDENTIFIED BY '${PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DATABASE}\`.* TO '${USERNAME}'@'localhost';
FLUSH PRIVILEGES;
SQL
    else
        mysql --protocol=socket <<SQL
DROP DATABASE IF EXISTS \`${DATABASE}\`;
DROP USER IF EXISTS '${USERNAME}'@'localhost';
FLUSH PRIVILEGES;
SQL
    fi
elif [ "${ENGINE}" = "postgresql" ]; then
    if [ "${ACTION}" = "create" ]; then
        sudo -u postgres psql -v ON_ERROR_STOP=1 <<SQL
SELECT 'CREATE ROLE "${USERNAME}" LOGIN PASSWORD ''${PASSWORD}''' WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname='${USERNAME}')\gexec
SELECT 'CREATE DATABASE "${DATABASE}" OWNER "${USERNAME}"' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname='${DATABASE}')\gexec
SQL
    else
        sudo -u postgres psql -v ON_ERROR_STOP=1 <<SQL
SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='${DATABASE}' AND pid <> pg_backend_pid();
DROP DATABASE IF EXISTS "${DATABASE}";
DROP ROLE IF EXISTS "${USERNAME}";
SQL
    fi
else
    echo "Unsupported database engine" >&2; exit 2
fi
