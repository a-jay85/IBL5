FROM php:8.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libonig-dev \
        curl \
        unzip \
        default-mysql-client \
    && docker-php-ext-install mysqli pdo pdo_mysql mbstring opcache \
    && rm -rf /var/lib/apt/lists/*

COPY docker/opcache.ini $PHP_INI_DIR/conf.d/opcache.ini
COPY docker/error-reporting.ini $PHP_INI_DIR/conf.d/error-reporting.ini

RUN a2enmod rewrite headers

RUN printf '<Directory /var/www/html>\n\
    AllowOverride None\n\
    Require all granted\n\
</Directory>\n\
\n\
<Directory /var/www/html/ibl5>\n\
    RewriteEngine On\n\
    RewriteRule ^api/v1/(.*)$ api.php?route=$1 [QSA,L]\n\
    DirectoryIndex index.php\n\
</Directory>\n' > /etc/apache2/conf-available/ibl5.conf \
    && a2enconf ibl5

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
