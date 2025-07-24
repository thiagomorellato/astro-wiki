FROM php:8.1-apache

# Instala dependências do PHP necessárias
RUN apt-get update && apt-get install -y \
    libzip-dev unzip wget libpng-dev \
    && docker-php-ext-install zip gd

# Copia os arquivos do DokuWiki
COPY . /var/www/html/

# Permissões e ativação de módulos
RUN chown -R www-data:www-data /var/www/html \
    && a2enmod rewrite

EXPOSE 80