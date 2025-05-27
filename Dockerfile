FROM php:8.1-apache

RUN apt-get update && apt-get install -y nano
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Cài đặt Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY . /var/www/html
COPY php.ini /usr/local/etc/php/php.ini
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Cài đặt các thư viện PHP
RUN composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

RUN a2enmod rewrite && echo "ServerName localhost" >> /etc/apache2/apache2.conf