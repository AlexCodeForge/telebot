# Use official PHP 8.2 FPM with Alpine (simplified approach)
FROM php:8.2-fpm-alpine

# Set working directory
WORKDIR /var/www/html

# Install essential system packages
RUN apk update && apk add --no-cache \
    nginx \
    supervisor \
    nodejs \
    npm \
    git \
    curl \
    bash \
    sqlite \
    sqlite-dev \
    libpng-dev \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    zlib-dev

# Install only the essential PHP extensions that Laravel absolutely needs
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo_sqlite zip gd bcmath

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . /var/www/html/

# Fix git ownership and set permissions
RUN git config --global --add safe.directory /var/www/html && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/storage && \
    chmod -R 755 /var/www/html/bootstrap/cache

# Install PHP dependencies - ignore platform requirements
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Install Node.js dependencies and build assets
RUN npm install && npm run build

# Copy configuration files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh

# Make entrypoint executable
RUN chmod +x /entrypoint.sh

# Create necessary directories
RUN mkdir -p /var/log/supervisor && mkdir -p /run/nginx

# Expose port 80
EXPOSE 80

# Start supervisor
ENTRYPOINT ["/entrypoint.sh"]
