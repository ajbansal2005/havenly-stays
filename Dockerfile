# Dockerfile for Render.com Deployment
FROM php:8.2-apache

# Install system dependencies and PHP extensions for MySQL, SQLite, and Fileinfo
RUN apt-get update && apt-get install -y \
    ca-certificates \
    libsqlite3-dev \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libzip-dev \
    zip \
    unzip \
    && update-ca-certificates \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    pdo_sqlite \
    fileinfo \
    gd \
    zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache modules: rewrite (for routing & .htaccess) and headers (for security headers)
RUN a2enmod rewrite headers

# Copy application source code
COPY . /var/www/html/

# Create required directories and set proper ownership for Apache
RUN mkdir -p /var/www/html/storage/sessions \
    /var/www/html/storage/logs \
    /var/www/html/uploads/listings \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/uploads

# Configure Apache to listen on Render's dynamic PORT environment variable (defaults to 80)
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Set environment defaults
ENV PORT=80
ENV RENDER=1

EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]
