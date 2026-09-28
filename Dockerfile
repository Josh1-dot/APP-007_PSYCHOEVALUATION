FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libxml2-dev libzip-dev libsqlite3-dev unzip \
    && docker-php-ext-install pdo_mysql pdo_sqlite mbstring dom xml xmlwriter zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && printf '<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/psycho.conf \
    && a2enconf psycho
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize \
    && chown -R www-data:www-data storage bootstrap/cache
COPY docker/php.ini /usr/local/etc/php/conf.d/psycho.ini
COPY docker/entrypoint.sh /usr/local/bin/psycho-entrypoint
RUN chmod +x /usr/local/bin/psycho-entrypoint
EXPOSE 80
ENTRYPOINT ["psycho-entrypoint"]
CMD ["apache2-foreground"]
