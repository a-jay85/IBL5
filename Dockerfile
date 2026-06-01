# ── Engine builder stage ────────────────────────────────────────────────────
# Compile the native Go sim binary used by the PR8 shadow loader. Pinned to the
# engine/go.mod toolchain so the build matches CI. The binary is copied to a path
# OUTSIDE the ibl5 bind-mount; the entrypoint materializes it into ibl5/bin at
# container start (the bind mount would otherwise shadow an image-built path).
FROM golang:1.26.3 AS engine-builder
WORKDIR /src/engine
COPY engine/ /src/engine
RUN CGO_ENABLED=0 go build -o /opt/jsbsim ./cmd/jsbsim

FROM php:8.5-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        unzip \
        default-mysql-client \
        libzip-dev \
    && docker-php-ext-install mysqli pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY docker/opcache.ini $PHP_INI_DIR/conf.d/opcache.ini
COPY docker/error-reporting.ini $PHP_INI_DIR/conf.d/error-reporting.ini

# Native engine binary, staged outside the ibl5 bind-mount. entrypoint.sh copies
# it into ibl5/bin/jsbsim (which IS bind-mounted) before Apache starts.
COPY --from=engine-builder /opt/jsbsim /opt/ibl5-bin/jsbsim

RUN a2enmod rewrite headers

RUN printf '<Directory /var/www/html>\n\
    AllowOverride None\n\
    Require all granted\n\
    RewriteEngine On\n\
    RewriteCond %%{REQUEST_URI} ^/?$\n\
    RewriteRule ^$ /ibl5/index.php [R=301,L]\n\
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
