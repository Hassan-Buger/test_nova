FROM php:8.2-apache

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Configure PHP environment variable parsing
RUN echo 'variables_order = "EGPCS"' > /usr/local/etc/php/conf.d/docker-php-vars.ini

# Install Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Enable Apache mod_rewrite and enforce mpm_prefork
RUN a2enmod rewrite \
    && a2dismod mpm_event mpm_worker 2>/dev/null || true \
    && a2enmod mpm_prefork 2>/dev/null || true \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Configure Apache DocumentRoot to point to /public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Configure directory permissions and .htaccess override
RUN echo '<Directory /var/www/html/public>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/trinova.conf \
    && a2enconf trinova

# Copy application source code
COPY . /var/www/html

# Ensure Composer dependencies and optimized autoloader
RUN cd /var/www/html && (composer install --no-dev --optimize-autoloader --no-interaction || composer dump-autoload --optimize --no-dev) 2>/dev/null || true

# Ensure storage directories exist and are writable
RUN mkdir -p /var/www/html/storage/uploads /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html/storage \
    && chmod -R 775 /var/www/html/storage

# Copy and prepare entrypoint script (stripping Windows CRLF if present)
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh

EXPOSE 80 8080 8000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]


