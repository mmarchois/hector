FROM php:8.4-fpm

WORKDIR /var/www/hector

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        zip \
        libicu-dev \
        libpq-dev \
        libzip-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_pgsql zip opcache intl
RUN pecl install apcu && docker-php-ext-enable apcu

# Xdebug uniquement à la demande (couverture de tests) : --build-arg INSTALL_XDEBUG=true
ARG INSTALL_XDEBUG=false
RUN if [ "$INSTALL_XDEBUG" = "true" ]; then pecl install xdebug && docker-php-ext-enable xdebug; fi

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Utilisateur non root
RUN groupadd -g 1000 appuser && useradd -u 1000 -g appuser -m appuser
USER appuser
RUN git config --global --add safe.directory /var/www/hector

CMD ["php-fpm"]
