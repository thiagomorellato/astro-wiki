FROM php:8.1-apache

# Instala extensões necessárias
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    unzip \
    git \
    mariadb-client \
    && docker-php-ext-install intl mysqli opcache pdo pdo_mysql xml mbstring gd

# Ativa mod_rewrite do Apache
RUN a2enmod rewrite

# Copia os arquivos da wiki para o container
COPY . /var/www/html/

# Define permissões
RUN chown -R www-data:www-data /var/www/html

# Expõe a porta padrão do Apache
EXPOSE 80