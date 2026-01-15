FROM php:8.4-apache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Install dependencies (Added libavif-dev)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    libavif-dev \
    libgd-dev \
    unzip \
    git \
    sudo \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions (Added --with-avif)
RUN docker-php-ext-configure gd \
    --with-jpeg \
    --with-webp \
    --with-freetype \
    --with-avif \
    && docker-php-ext-install gd pdo pdo_mysql mysqli

RUN a2enmod rewrite

# Create vscode user if it doesn't exist
RUN id -u vscode >/dev/null 2>&1 || useradd -m -s /bin/bash -u 1000 vscode \
    && echo "vscode ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers

# Set working directory ownership
RUN chown -R vscode:vscode /var/www/html

RUN echo "output_buffering = 4096" >> /usr/local/etc/php/conf.d/docker-php-output-buffering.ini

# This ensures that 'drush' points to your project's local drush
ENV PATH="/workspaces/FederalGovernment/vendor/bin:${PATH}"
