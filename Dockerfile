FROM php:8.2-apache

# Install system dependencies & libraries required for extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    ca-certificates \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) mysqli pdo_mysql gd zip \
    && a2enmod rewrite headers \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure PHP settings (Output Buffering prevents any 'headers already sent' warnings)
RUN echo "output_buffering = 4096" > /usr/local/etc/php/conf.d/custom-php.ini && \
    echo "upload_max_filesize = 25M" >> /usr/local/etc/php/conf.d/custom-php.ini && \
    echo "post_max_size = 30M" >> /usr/local/etc/php/conf.d/custom-php.ini && \
    echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/custom-php.ini

# Enable AllowOverride All in Apache main configuration
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html/

# Copy startup script, strip Windows carriage returns and make executable
COPY start.sh /usr/local/bin/start.sh
RUN sed -i 's/\r$//' /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

# Ensure upload directory exists with proper write permissions
RUN mkdir -p /var/www/html/assets/uploads && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 775 /var/www/html/assets/uploads

# Expose container port (Render dynamically maps $PORT)
EXPOSE 10000

# Start via start.sh
CMD ["/usr/local/bin/start.sh"]
