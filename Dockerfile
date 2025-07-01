# Use official PHP 8.2 FPM image with Alpine 3.18 (more stable)
FROM php:8.2-fpm-alpine3.18

# Set working directory
WORKDIR /var/www/html

# Update package repositories and install system dependencies in stages
RUN apk update && apk upgrade && \
    # Install basic packages first
    apk add --no-cache \
        nginx \
        supervisor \
        git \
        curl \
        bash && \
    # Install development packages
    apk add --no-cache \
        sqlite \
        sqlite-dev \
        zlib-dev \
        libzip-dev \
        libpng-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        oniguruma-dev \
        bzip2-dev \
        xz-dev \
        pkgconfig && \
    # Install Node.js and npm
    apk add --no-cache \
        nodejs \
        npm \
        zip \
        unzip

# Configure and install ALL PHP extensions Laravel needs
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install \
        pdo_sqlite \
        zip \
        mbstring \
        gd \
        bcmath \
        pcntl \
        fileinfo \
        tokenizer

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . /var/www/html/

# Fix git ownership issues and set proper permissions
RUN git config --global --add safe.directory /var/www/html && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/storage && \
    chmod -R 755 /var/www/html/bootstrap/cache

# Install PHP dependencies with platform requirements check disabled for Docker
RUN composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-bcmath --ignore-platform-req=ext-fileinfo --ignore-platform-req=ext-tokenizer

# Install Node.js dependencies and build assets
RUN npm install && npm run build

# Copy configuration files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh

# Make entrypoint executable
RUN chmod +x /entrypoint.sh

# Create necessary directories
RUN mkdir -p /var/log/supervisor \
    && mkdir -p /run/nginx

# Expose port 80
EXPOSE 80

# Start supervisor
ENTRYPOINT ["/entrypoint.sh"]
