FROM php:8.2-apache

RUN rm -f /etc/apache2/mods-enabled/mpm_event.load \
    /etc/apache2/mods-enabled/mpm_event.conf \
    /etc/apache2/mods-enabled/mpm_worker.load \
    /etc/apache2/mods-enabled/mpm_worker.conf \
    && a2enmod mpm_prefork rewrite \
    && docker-php-ext-install pdo pdo_mysql mysqli

COPY . /var/www/html/

EXPOSE 80