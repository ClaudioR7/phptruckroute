FROM php:8.2-apache

RUN a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork \
    && docker-php-ext-install pdo pdo_mysql mysqli

COPY . /var/www/html/

EXPOSE 80