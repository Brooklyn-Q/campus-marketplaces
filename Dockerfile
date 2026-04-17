FROM php:8.2-apache

# Install PostgreSQL client and PHP extensions for Supabase
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pdo_mysql

# Configure Apache to listen on port 10000 instead of 80 (Render Default)
RUN sed -i 's/80/10000/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Enable Apache mod_rewrite for friendly URLs
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Set permissions for uploads (if any local fallback is used)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port (Render Default)
EXPOSE 10000

# The default Apache start command is fine for Render
