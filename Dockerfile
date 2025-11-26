FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    postgresql-client \
    libpq-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd zip opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies (no dev dependencies for production)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy application files
COPY . .

# Run composer scripts (cache:clear, assets:install)
RUN composer run-script post-install-cmd || true

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/public/uploads/documents \
    && mkdir -p /var/www/html/public/uploads/reclamations \
    && chown -R www-data:www-data /var/www/html/public/uploads

# Configure Apache
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Configure PHP OPcache for better performance
COPY docker/php-opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]

