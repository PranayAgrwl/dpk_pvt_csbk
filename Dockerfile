# Use an official PHP image with Apache on PHP 8.2
FROM php:8.2-apache

# Install the PDO MySQL extension required for your app's database connection
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite for your custom .htaccess routing
RUN a2enmod rewrite

# Update Apache configuration to allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Set the working directory inside the container
WORKDIR /var/www/html

# Copy all application files to the container
COPY . /var/www/html/

# Set the correct permissions so Apache can access the files and write to logs
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 80 internally
EXPOSE 80
