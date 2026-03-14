FROM php:8.3-fpm-alpine

# Install required extensions
RUN apk add --no-cache \
        libpng-dev \
        libwebp-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        ffmpeg \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files and install dependencies
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy application code
COPY . .

# Ensure storage directories exist and are writable
RUN mkdir -p storage/uploads storage/thumbs data \
    && chown -R www-data:www-data storage data \
    && chmod -R 775 storage data

EXPOSE 9000
CMD ["php-fpm"]
