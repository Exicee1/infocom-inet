FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libonig-dev \
    libfreetype6-dev \
    libjpeg-dev \
    libpng-dev \
    libwebp-dev \
    git \
    curl \
    unzip \
    libxml2-dev \
    libsqlite3-dev \
    libzip-dev \
    libcurl4-openssl-dev \
    zlib1g-dev \
    libc6-dev \
    libsqlcipher-dev \
    pkg-config \
    && apt-get clean

# 2. Включаем флаг шифрования для компилятора C
ENV CFLAGS="-DSQLITE_HAS_CODEC"

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    gd \
    mbstring \
    zip \
    curl \
    json \
    pdo_sqlite \
    sqlite3

RUN pecl install xmlrpc-1.0.0 || true

RUN curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/bin

COPY conf/php.ini      /usr/local/etc/php/php.ini
COPY conf/php-fpm.conf /usr/local/etc/php-fpm.conf
COPY conf/www.conf     /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html
EXPOSE 9000
CMD ["php-fpm"]
