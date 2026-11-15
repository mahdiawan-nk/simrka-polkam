FROM php:8.3-fpm

ARG APP_UID=1000
ARG APP_GID=1000

# Install system packages
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    curl \
    gnupg \
    ca-certificates \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libpq-dev \
    zip \
    && rm -rf /var/lib/apt/lists/*

# PHP Extensions
RUN docker-php-ext-configure zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        zip \
        intl \
        pdo_mysql \
        gd \
        opcache

# Composer
RUN curl -sS https://getcomposer.org/installer \
    | php -- --install-dir=/usr/local/bin --filename=composer

# Create user
RUN groupadd -g ${APP_GID} appgroup \
    && useradd -u ${APP_UID} -g appgroup -m appuser

WORKDIR /var/www/html

RUN chown -R appuser:appgroup /var/www

RUN echo "upload_max_filesize=15M" >> /usr/local/etc/php/conf.d/uploads.ini \
 && echo "post_max_size=50M" >> /usr/local/etc/php/conf.d/uploads.ini \
 && echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/uploads.ini \
 && echo "max_execution_time=300" >> /usr/local/etc/php/conf.d/uploads.ini

USER appuser

EXPOSE 9000

CMD ["php-fpm"]