#!/bin/sh
set -e

# Create logs directory if it doesn't exist
mkdir -p /var/www/logs
chmod 755 /var/www/logs

# Check DEBUG_MODE environment variable
if [ "$DEBUG_MODE" = "true" ] || [ "$DEBUG_MODE" = "1" ]; then
    echo "🐛 DEBUG MODE ENABLED"
    
    # Enable error display in debug mode
    echo "display_errors = On" >> /usr/local/etc/php/php.ini
    echo "display_startup_errors = On" >> /usr/local/etc/php/php.ini
    echo "error_reporting = E_ALL" >> /usr/local/etc/php/php.ini
    echo "log_errors = On" >> /usr/local/etc/php/php.ini
    
    # Disable OpCache validation timestamps for development
    echo "opcache.validate_timestamps = 1" >> /usr/local/etc/php/php.ini
    echo "opcache.revalidate_freq = 1" >> /usr/local/etc/php/php.ini
    
    # Disable JIT for easier debugging
    echo "opcache.jit = off" >> /usr/local/etc/php/php.ini
    
    # Lower PHP limits for development
    echo "max_execution_time = 600" >> /usr/local/etc/php/php.ini
else
    echo "✓ PRODUCTION MODE"
fi

# Ensure proper permissions
chown -R www-data:www-data /var/www/logs

# Execute PHP-FPM
exec "$@"
