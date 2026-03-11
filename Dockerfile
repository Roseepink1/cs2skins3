FROM php:8.2-cli

WORKDIR /var/www/html

RUN docker-php-ext-install mysqli pdo pdo_mysql

COPY . .

EXPOSE 8080

CMD php -S 0.0.0.0:8080 index.php
