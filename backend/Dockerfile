FROM php:8.2-apache

# Instalacja rozszerzeń MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Włączenie mod_rewrite
RUN a2enmod rewrite

# Kopiowanie kodu
COPY . /var/www/html/

# Ustawienie permissions
RUN chown -R www-data:www-data /var/www/html