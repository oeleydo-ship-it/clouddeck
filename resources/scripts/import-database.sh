#!/usr/bin/env bash
set -Eeuo pipefail
ENGINE={{ENGINE}}
DATABASE={{DATABASE}}
SQL_BASE64={{SQL_BASE64}}
TEMP="$(mktemp)"
trap 'rm -f "${TEMP}"' EXIT
printf '%s' "${SQL_BASE64}" | base64 -d > "${TEMP}"
if [ "${ENGINE}" = "postgresql" ]; then sudo -u postgres psql -v ON_ERROR_STOP=1 "${DATABASE}" < "${TEMP}"; else mysql --protocol=socket "${DATABASE}" < "${TEMP}"; fi
