FROM php:8.3-cli-alpine

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    unzip \
    libzip-dev \
    zip \
    && docker-php-ext-install pdo_mysql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer config files first
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-scripts --no-autoloader --no-dev || composer install --no-scripts --no-autoloader

# Copy application files
COPY . .

# Generate Composer autoloader
RUN composer dump-autoload --optimize

# Ensure storage and bootstrap/cache directories are writable
RUN chmod -R 777 storage bootstrap/cache

# Expose port 8003
EXPOSE 8003

# Start Laravel built-in development server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8003"]
