FROM php:8.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libonig-dev \
        curl \
    && docker-php-ext-install mysqli pdo pdo_mysql mbstring opcache \
    && rm -rf /var/lib/apt/lists/*

COPY docker/opcache.ini $PHP_INI_DIR/conf.d/opcache.ini

RUN a2enmod rewrite headers

RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/ibl5.conf \
    && a2enconf ibl5

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
