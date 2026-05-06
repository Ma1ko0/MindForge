#!/bin/sh
set -e

# Check DEBUG_MODE environment variable
if [ "$DEBUG_MODE" = "true" ] || [ "$DEBUG_MODE" = "1" ]; then
    echo "🐛 DEBUG MODE: Using debug nginx configuration"
    cp /etc/nginx/conf.d/default.conf.debug /etc/nginx/conf.d/default.conf.prod 2>/dev/null || true
    rm /etc/nginx/conf.d/default.conf
    cp /etc/nginx/conf.d/default.conf.debug /etc/nginx/conf.d/default.conf
else
    echo "✓ PRODUCTION MODE: Using production nginx configuration"
fi

# Execute original nginx entrypoint
exec /docker-entrypoint.sh "$@"
