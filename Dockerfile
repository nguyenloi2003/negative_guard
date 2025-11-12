FROM php:8.1-apache  

# Install system dependencies  
RUN apt-get update && apt-get install -y \  
    build-essential \  
    git \  
    unzip \  
    libzip-dev \  
    libpng-dev \  
    libjpeg-dev \  
    libfreetype6-dev \  
    && rm -rf /var/lib/apt/lists/*  

# Configure GD with JPEG and FreeType support  
RUN docker-php-ext-configure gd --with-freetype --with-jpeg  

# Install PHP extensions  
RUN docker-php-ext-install -j$(nproc) pdo pdo_mysql zip dom xml gd  

# Install Composer  
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer  

# Fix git ownership  
RUN git config --global --add safe.directory /var/www/html  

# Copy application code  
COPY . /var/www/html/  

# Install PHP dependencies  
RUN composer install --no-dev --optimize-autoloader --no-interaction  

# Set proper permissions  
RUN chown -R www-data:www-data /var/www/html  

# Enable Apache mod_rewrite (if needed)  
RUN a2enmod rewrite  

WORKDIR /var/www/html