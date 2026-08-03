#!/usr/bin/env bash
set -Eeuo pipefail
DOMAIN={{DOMAIN}}
ACTION={{ACTION}}
RELATIVE_PATH={{PATH}}
DESTINATION={{DESTINATION}}
PAYLOAD={{PAYLOAD}}
MODE={{MODE}}
ROOT=$(realpath -e "/var/www/${DOMAIN}")

resolve_path() {
    local relative="${1#/}"
    local resolved
    resolved=$(realpath -m -- "$ROOT/$relative")
    case "$resolved" in "$ROOT"|"$ROOT"/*) printf '%s' "$resolved" ;; *) echo 'Path escapes the site root' >&2; exit 64 ;; esac
}

TARGET=$(resolve_path "$RELATIVE_PATH")
case "$ACTION" in
    list)
        [[ -d "$TARGET" ]] || { echo 'Directory not found' >&2; exit 66; }
        while IFS= read -r -d '' entry; do
            name=$(basename -- "$entry" | base64 -w0)
            [[ -d "$entry" ]] && type=directory || type=file
            printf '%s\t%s\t%s\t%s\t%s\t%s\n' "$name" "$type" "$(stat -c %s "$entry")" "$(stat -c %Y "$entry")" "$(stat -c %a "$entry")" "$(stat -c %U:%G "$entry")"
        done < <(find "$TARGET" -mindepth 1 -maxdepth 1 -print0)
        ;;
    read)
        [[ -f "$TARGET" ]] || { echo 'File not found' >&2; exit 66; }
        [[ $(stat -c %s "$TARGET") -le 1048576 ]] || { echo 'Editor limit is 1 MB' >&2; exit 65; }
        base64 -w0 "$TARGET"
        ;;
    download)
        [[ -f "$TARGET" ]] || { echo 'File not found' >&2; exit 66; }
        [[ $(stat -c %s "$TARGET") -le 10485760 ]] || { echo 'Download limit is 10 MB' >&2; exit 65; }
        base64 -w0 "$TARGET"
        ;;
    write|upload)
        mkdir -p "$(dirname "$TARGET")"
        printf '%s' "$PAYLOAD" | base64 --decode > "$TARGET"
        chown www-data:www-data "$TARGET"
        echo 'File written'
        ;;
    mkdir)
        mkdir -p "$TARGET"; chown www-data:www-data "$TARGET"; echo 'Directory created'
        ;;
    rename)
        DEST=$(resolve_path "$DESTINATION"); mkdir -p "$(dirname "$DEST")"; mv -- "$TARGET" "$DEST"; echo 'Path renamed'
        ;;
    delete)
        [[ "$TARGET" != "$ROOT" ]] || { echo 'Cannot delete the site root' >&2; exit 64; }
        rm -rf -- "$TARGET"; echo 'Path deleted'
        ;;
    chmod)
        chmod "$MODE" "$TARGET"; echo 'Permissions updated'
        ;;
    zip)
        DEST=$(resolve_path "$DESTINATION"); [[ -d "$TARGET" && "$DEST" == "$TARGET"/* ]] && { echo 'Archive destination cannot be inside its source' >&2; exit 64; }; mkdir -p "$(dirname "$DEST")"; (cd "$(dirname "$TARGET")" && zip -qr "$DEST" "$(basename "$TARGET")"); echo 'Archive created'
        ;;
    unzip)
        DEST=$(resolve_path "$DESTINATION"); mkdir -p "$DEST"
        unzip -Z1 "$TARGET" | grep -Eq '(^/|(^|/)\.\.(/|$))' && { echo 'Unsafe archive path' >&2; exit 64; } || true
        zipinfo -l "$TARGET" | awk '$1 ~ /^l/ {found=1} END {exit found ? 0 : 1}' && { echo 'Archive symlinks are not allowed' >&2; exit 64; } || true
        unzip -q "$TARGET" -d "$DEST"; chown -R www-data:www-data "$DEST"; echo 'Archive extracted'
        ;;
    *) echo 'Unsupported file operation' >&2; exit 64 ;;
esac
