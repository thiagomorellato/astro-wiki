FROM php:8.1-apache

# Instala extensões e ferramentas necessárias
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    unzip \
    git \
    libpq-dev \
    && docker-php-ext-install intl opcache pdo pdo_pgsql pgsql xml mbstring gd

# Ativa mod_rewrite do Apache
RUN a2enmod rewrite

# Copia os arquivos da wiki para o container
COPY . /var/www/html/

# Copia o opcache.ini para o local onde o PHP carrega configs
COPY opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Define permissões
RUN chown -R www-data:www-data /var/www/html

# Expõe a porta padrão do Apache
EXPOSE 80
