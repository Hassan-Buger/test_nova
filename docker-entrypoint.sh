#!/bin/bash
set -e

# Suppress ServerName warning globally
echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf
a2enconf servername >/dev/null 2>&1 || true
grep -q "ServerName localhost" /etc/apache2/apache2.conf || echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Ensure only prefork MPM is enabled
a2dismod mpm_event mpm_worker >/dev/null 2>&1 || true
a2enmod mpm_prefork >/dev/null 2>&1 || true
rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_event.conf /etc/apache2/mods-enabled/mpm_worker.conf 2>/dev/null || true

# Configure ports: listen on standard web ports (80, 8080, 8000) and dynamic Railway PORT
cat << 'EOF' > /etc/apache2/ports.conf
Listen 80
Listen 8080
Listen 8000
EOF

if [ -n "$PORT" ] && [ "$PORT" != "80" ] && [ "$PORT" != "8080" ] && [ "$PORT" != "8000" ]; then
    echo "Listen $PORT" >> /etc/apache2/ports.conf
fi

# Configure default VirtualHost to accept traffic on any configured port
cat << 'EOF' > /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80 *:8080 *:8000 *:${PORT:-80}>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/public

    <Directory /var/www/html/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Ensure sites and modules are enabled
a2enmod rewrite 2>/dev/null || true
a2ensite 000-default 2>/dev/null || true

# Ensure storage directories exist and are writable
mkdir -p /var/www/html/storage/uploads
if [ -d /var/www/html/storage/seeds ]; then
    cp -n /var/www/html/storage/seeds/* /var/www/html/storage/uploads/ 2>/dev/null || true
fi
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage

# Run automated database migration & seed check
php /var/www/html/scripts/auto_migrate.php || true

# Start Apache in the foreground
exec apache2-foreground

