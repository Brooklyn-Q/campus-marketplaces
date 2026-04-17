FROM php:8.2-apache

# Install PostgreSQL client and PHP extensions for Supabase
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pdo_mysql

# Enable Apache mod_rewrite for friendly URLs
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Set permissions for uploads (if any local fallback is used)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port (Render uses $PORT, but Apache defaults to 80)
EXPOSE 80

# The default Apache start command is fine for Render
