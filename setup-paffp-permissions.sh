#!/usr/bin/env bash
set -euo pipefail

PAFFP_ROOT="${1:-$(pwd)}"
WEB_GROUP="${PAFFP_WEB_GROUP:-www-data}"

if [ ! -f "$PAFFP_ROOT/sync-data.php" ] || [ ! -d "$PAFFP_ROOT/data" ]; then
    echo "ERROR: '$PAFFP_ROOT' does not look like the PAFFP web root." >&2
    echo "Usage: sudo bash setup-paffp-permissions.sh /var/www/html/paffp" >&2
    exit 1
fi

if ! getent group "$WEB_GROUP" >/dev/null 2>&1; then
    echo "ERROR: Web-server group '$WEB_GROUP' does not exist." >&2
    exit 1
fi

# Mutable PAFFP locations.
mkdir -p \
    "$PAFFP_ROOT/data/_sync" \
    "$PAFFP_ROOT/data/_chat" \
    "$PAFFP_ROOT/data/backups/full" \
    "$PAFFP_ROOT/uploadID"

# data/ must allow Apache to create/update JSON, sync metadata and backups.
chown root:"$WEB_GROUP" "$PAFFP_ROOT/data"
chmod 2775 "$PAFFP_ROOT/data"

# Existing JSON files remain owned by root but writable by Apache's group.
find "$PAFFP_ROOT/data" -maxdepth 1 -type f -name '*.json' \
    -exec chown root:"$WEB_GROUP" {} + \
    -exec chmod 0664 {} +

# Internal writable runtime directories.
for dir in \
    "$PAFFP_ROOT/data/_sync" \
    "$PAFFP_ROOT/data/_chat" \
    "$PAFFP_ROOT/data/backups" \
    "$PAFFP_ROOT/data/backups/full" \
    "$PAFFP_ROOT/uploadID"
do
    chown -R root:"$WEB_GROUP" "$dir"
    find "$dir" -type d -exec chmod 2775 {} +
    find "$dir" -type f -exec chmod 0664 {} +
done

# Apache only needs to read these protection files.
if [ -f "$PAFFP_ROOT/data/.htaccess" ]; then
    chown root:"$WEB_GROUP" "$PAFFP_ROOT/data/.htaccess"
    chmod 0644 "$PAFFP_ROOT/data/.htaccess"
fi

if [ -f "$PAFFP_ROOT/data/_sync/.htaccess" ]; then
    chown root:"$WEB_GROUP" "$PAFFP_ROOT/data/_sync/.htaccess"
    chmod 0644 "$PAFFP_ROOT/data/_sync/.htaccess"
fi

if [ -f "$PAFFP_ROOT/data/_chat/.htaccess" ]; then
    chown root:"$WEB_GROUP" "$PAFFP_ROOT/data/_chat/.htaccess"
    chmod 0644 "$PAFFP_ROOT/data/_chat/.htaccess"
fi

if [ -f "$PAFFP_ROOT/uploadID/.htaccess" ]; then
    chown root:"$WEB_GROUP" "$PAFFP_ROOT/uploadID/.htaccess"
    chmod 0644 "$PAFFP_ROOT/uploadID/.htaccess"
fi

# Verify the Apache account/group can write where sync/update need it.
CHECK_FILE="$PAFFP_ROOT/data/_sync/.paffp-write-test-$$"
if runuser -u "$WEB_GROUP" -- sh -c "printf test > '$CHECK_FILE'" 2>/dev/null; then
    rm -f "$CHECK_FILE"
    echo "OK: Apache ($WEB_GROUP) can write to data/_sync."
else
    echo "WARNING: Could not verify write access using user '$WEB_GROUP'." >&2
    echo "Check your Apache service user with: ps aux | grep apache2" >&2
fi

echo "PAFFP permissions configured successfully: $PAFFP_ROOT"
