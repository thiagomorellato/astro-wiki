FROM php:8.1-apache

# Instala dependências do sistema
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    zip \
    unzip \
    git \
    mariadb-client \
    wget \
    nano

# Instala extensões PHP
RUN docker-php-ext-install intl pdo pdo_mysql mysqli

# Ativa mod_rewrite do Apache
RUN a2enmod rewrite

# Baixa o MediaWiki (versão estável)
WORKDIR /var/www/html
RUN rm -rf ./*
RUN wget https://releases.wikimedia.org/mediawiki/1.40/mediawiki-1.40.0.tar.gz
RUN tar -xzf mediawiki-1.40.0.tar.gz --strip-components=1
RUN rm mediawiki-1.40.0.tar.gz

# Permissões para o apache
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]