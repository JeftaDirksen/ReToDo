FROM php:8-apache
RUN mkdir /data
RUN chown -R www-data:www-data /data
COPY src /var/www
WORKDIR /var/www/html
VOLUME /data
