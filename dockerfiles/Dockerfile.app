FROM php:8.2-fpm-alpine

# Argument untuk UID dan GID (agar cocok dengan host)
ARG UID
ARG GID

# Install package sistem & dev tools
RUN apk add --no-cache \
    bash \
    git \
    curl \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    icu-dev \
    libxml2-dev \
    autoconf \
    g++ \
    make \
    icu-data-full

# Konfigurasi dan install ekstensi PHP resmi
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mbstring \
    exif \
    bcmath \
    zip \
    gd \
    intl \
    opcache

# Install Composer secara global
RUN curl -sS https://getcomposer.org/installer \
    | php -- --install-dir=/usr/local/bin --filename=composer

# Tambahkan user non-root (sesuai UID/GID dari argumen)
RUN addgroup -g ${GID:-1001} -S appgroup \
 && adduser -u ${UID:-1001} -G appgroup -S appuser -s /bin/sh

# Tentukan direktori kerja
WORKDIR /var/www/html

# Beri hak akses ke user yang sesuai
RUN chown -R appuser:appgroup /var/www/html

# Beralih ke user non-root
USER appuser

# Jalankan PHP-FPM (default CMD)
CMD ["php-fpm"]
