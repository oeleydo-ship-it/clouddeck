#!/usr/bin/env bash
# Major Ubuntu release upgrade (do-release-upgrade). Destructive; confirm from the panel first.
set -Eeuo pipefail
export DEBIAN_FRONTEND=noninteractive

if ! command -v do-release-upgrade >/dev/null 2>&1; then
    echo "do-release-upgrade is not installed; installing ubuntu-release-upgrader-core" >&2
    apt-get update -qq
    apt-get install -y ubuntu-release-upgrader-core
fi

echo "==> Starting noninteractive release upgrade"
# DistUpgradeViewNonInteractive avoids prompts; still may leave the host needing reboot.
do-release-upgrade -f DistUpgradeViewNonInteractive || {
    status=$?
    echo "do-release-upgrade exited with status ${status}" >&2
    exit "$status"
}

echo "CLOUDDECK_RELEASE_UPGRADE_OK=1"
echo "Release upgrade finished. Reboot the server before continuing production traffic."
