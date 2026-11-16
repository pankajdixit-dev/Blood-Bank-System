#!/bin/bash
set -e

# Default to 10000 if PORT is not set by Render
PORT="${PORT:-10000}"

echo "=================================================="
echo "Starting Blood Bank Management System on Render"
echo "Listening Port: $PORT"
echo "=================================================="

# Update Apache listening port dynamically
sed -i "s/Listen [0-9]*/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost \*:$PORT>/g" /etc/apache2/sites-available/000-default.conf

# Render Reverse Proxy & Port 10000 Fixes:
# Prevents Apache from appending internal container port (e.g. :10000) during redirects
cat << 'EOF' > /etc/apache2/conf-available/render-reverse-proxy.conf
ServerName localhost
UseCanonicalName Off
UseCanonicalPhysicalPort Off

<IfModule mod_headers.c>
    # Respect forwarded headers from Render edge proxy
    Header unset X-Forwarded-Port
</IfModule>
EOF

a2enconf render-reverse-proxy

echo "Executing apache2-foreground..."
exec apache2-foreground
