FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       libzip-dev \
       zip \
       unzip \
       curl \
    && docker-php-ext-install pdo pdo_mysql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
