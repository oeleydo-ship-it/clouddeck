#!/usr/bin/env bash
set -Eeuo pipefail

ACTION={{ACTION}}
TYPE={{TYPE}}
PROTOCOL={{PROTOCOL}}
PORT={{PORT}}
FROM_IP={{FROM_IP}}
COMMENT={{COMMENT}}

if ! command -v ufw >/dev/null 2>&1; then
    echo "UFW_NOT_INSTALLED"
    exit 0
fi

remove_by_comment() {
    local guard=0
    while [ "${guard}" -lt 50 ]; do
        local line num
        line="$(ufw status numbered 2>/dev/null | grep -F "${COMMENT}" | head -n 1 || true)"
        [ -z "${line}" ] && break
        num="$(printf '%s\n' "${line}" | sed -n 's/^\[\s*\([0-9][0-9]*\)\].*/\1/p')"
        [ -z "${num}" ] && break
        yes | ufw delete "${num}" >/dev/null
        guard=$((guard + 1))
    done
}

remove_by_comment

if [ "${ACTION}" = "remove" ]; then
    echo "REMOVED"
    exit 0
fi

if [ -z "${PORT}" ] && [ -z "${FROM_IP}" ]; then
    echo "EMPTY_RULE"
    exit 0
fi

ARGS=("${TYPE}")

if [ -n "${FROM_IP}" ]; then
    ARGS+=("from" "${FROM_IP}")
fi

if [ -n "${PORT}" ]; then
    if [[ "${PORT}" =~ ^[0-9]+(:[0-9]+)?$ ]]; then
        if [ -n "${FROM_IP}" ]; then
            ARGS+=("to" "any" "port" "${PORT}")
            if [ "${PROTOCOL}" != "any" ]; then
                ARGS+=("proto" "${PROTOCOL}")
            fi
        elif [ "${PROTOCOL}" = "any" ]; then
            ARGS+=("${PORT}")
        else
            ARGS+=("${PORT}/${PROTOCOL}")
        fi
    else
        if [ -n "${FROM_IP}" ]; then
            echo "NAMED_PORT_WITH_FROM_UNSUPPORTED"
            exit 0
        fi
        ARGS+=("${PORT}")
    fi
fi

ARGS+=("comment" "${COMMENT}")
ufw "${ARGS[@]}"
echo "APPLIED"
