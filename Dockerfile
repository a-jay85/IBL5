FROM php:8.5-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        unzip \
        default-mysql-client \
    && docker-php-ext-install mysqli pdo_mysql \
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
</Directory>\n\
\n\
ErrorDocument 403 /ibl5/error-pages/403.html\n\
ErrorDocument 404 /ibl5/error-pages/404.html\n\
ErrorDocument 500 /ibl5/error-pages/500.html\n\
ErrorDocument 503 /ibl5/error-pages/503.html\n' > /etc/apache2/conf-available/ibl5.conf \
    && a2enconf ibl5

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
