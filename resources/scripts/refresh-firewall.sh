#!/usr/bin/env bash
set -Eeuo pipefail

if ! command -v ufw >/dev/null 2>&1; then
    echo "UFW_NOT_INSTALLED"
    exit 0
fi

ufw status verbose
