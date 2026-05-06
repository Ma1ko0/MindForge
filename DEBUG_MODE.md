#!/bin/bash

# MindForge Debug Mode Setup

This project supports two modes: **Production** and **Debug**.

## Quick Start

### Production Mode (Default)
```bash
docker-compose up -d
```

### Debug Mode
```bash
DEBUG_MODE=true docker-compose up -d
```

Or set it in `.env`:
```
DEBUG_MODE=true
```

---

## What's Different in Debug Mode?

### PHP Debugging
- ✓ Error display enabled (shows on response)
- ✓ Full error reporting (E_ALL)
- ✓ OpCache validation enabled (changes reflected immediately)
- ✓ OpCache JIT disabled (easier step-through debugging)
- ✓ Longer execution timeouts (600s vs 300s)
- ✓ Full error logs to `/backend/logs/php-error.log`

### Nginx Debugging
- ✓ Debug logging level (verbose)
- ✓ No asset caching (always fresh)
- ✓ Increased rate limits (30req/s vs 10req/s)
- ✓ Response headers: `X-Upstream-Server`, `X-Response-Time`
- ✓ Debug header: `X-Debug-Mode: enabled`
- ✓ Longer timeouts (120s vs 60s)

### Extra Debug Endpoints
```
GET /fpm-ping    - PHP-FPM ping status
GET /fpm-status  - PHP-FPM process status
```

---

## Switching Configurations

### Automatic (via environment variable)
The `DEBUG_MODE` environment variable controls everything automatically.

### Manual nginx.conf Switching
If you want to manually use debug nginx config:
```bash
# Use debug config
cp frontend/nginx.conf.debug frontend/nginx.conf

# Use production config
cp frontend/nginx.conf.prod frontend/nginx.conf  # (if created)
```

Then restart:
```bash
docker-compose restart frontend
```

---

## View Logs

### PHP Errors
```bash
docker-compose logs api
# or
tail -f backend/logs/php-error.log
```

### Nginx Access/Error Logs
```bash
docker-compose logs frontend
```

### Inside Containers
```bash
# PHP container
docker exec api cat logs/php-error.log

# Nginx container (debug mode)
docker exec frontend cat /var/log/nginx/error_debug.log
```

---

## Performance Tips

### Development (Debug Mode)
- Use `DEBUG_MODE=true` for fastest development iteration
- Watch PHP error logs for issues
- Test API endpoints directly via `/api/endpoint`

### Production (Default)
- Errors logged to files, not displayed
- OpCache optimized for performance
- Rate limiting enabled
- Longer asset caching

---

## Troubleshooting

### 502 Bad Gateway
- Check if API container is running: `docker-compose ps`
- Check PHP-FPM health: `docker-compose logs api`
- Verify PHP-FPM is listening on port 9000

### Errors not showing
- Set `DEBUG_MODE=true` and rebuild: `docker-compose up -d --build`
- Check logs in `/backend/logs/php-error.log`

### Changes not taking effect
- In debug mode, OpCache is bypassed
- In production, rebuild to clear cache: `docker-compose build --no-cache api`

---

## Configuration Files

- `backend/php.ini` - PHP settings (base)
- `backend/php-fpm.conf` - PHP-FPM process configuration
- `backend/docker-entrypoint.sh` - Entry point that sets debug mode
- `frontend/nginx.conf` - Production nginx config
- `frontend/nginx.conf.debug` - Debug nginx config (verbose logging)
