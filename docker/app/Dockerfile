FROM php:8.4.3-fpm-bookworm AS prod
LABEL maintainer="Kevin Smith <kevin@kevinsmith.io>"
ARG DEV_DEPS

#
# This image is meant to be built with the build context of the
# app root directory.
#

# Install required packages
RUN apt-get update && apt-get install -y --no-install-recommends \
    gnupg \
    zip \
    unzip \
    xz-utils \
    # Cleanup
    && rm -rf /var/lib/apt/lists/*

#
# s6 overlay init and process supervisor
#

ARG S6_OVERLAY_VERSION=3.2.0.2
ENV S6_BEHAVIOUR_IF_STAGE2_FAILS=2

ADD https://github.com/just-containers/s6-overlay/releases/download/v${S6_OVERLAY_VERSION}/s6-overlay-noarch.tar.xz /tmp
RUN tar -C / -Jxpf /tmp/s6-overlay-noarch.tar.xz
ADD https://github.com/just-containers/s6-overlay/releases/download/v${S6_OVERLAY_VERSION}/s6-overlay-x86_64.tar.xz /tmp
RUN tar -C / -Jxpf /tmp/s6-overlay-x86_64.tar.xz

#
# Additional PHP extensions and configuration
#

RUN apt-get update && apt-get install -y --no-install-recommends \
    libfreetype-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    zlib1g-dev \
    libicu-dev \
    gcc \
    make \
    autoconf \
    # Install GD
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install "-j$(nproc)" gd \
    # Install Xdebug
    && pecl install -o -f xdebug-3.4.0 \
    # Install intl PHP extension
    && docker-php-ext-configure intl && docker-php-ext-install intl \
    # Install Zip
    && docker-php-ext-install "-j$(nproc)" zip \
    # Install other extensions
    && docker-php-ext-install "-j$(nproc)" \
        bcmath \
        opcache \
        pcntl \
        pdo_mysql \
        sockets \
        sysvsem \
    # Cleanup
    && apt-get purge -y \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
        zlib1g-dev \
    && rm -rf /var/lib/apt/lists/*

ENV XDEBUG_CLIENT_HOST=host.docker.internal

#
# Install and configure nginx
#

# Find at http://nginx.org/packages/mainline/debian/pool/nginx/n/nginx/
# and http://nginx.org/packages/mainline/debian/pool/nginx/n/nginx-module-njs/
ARG NGINX_VERSION=1.27.3
ARG NJS_VERSION=0.8.8
ARG NJS_RELEASE=1~bookworm
ARG PKG_RELEASE=1~bookworm

RUN apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys 573BFD6B3D8FBC641079A6ABABF5BD827BD9BF62 \
    && echo "deb http://nginx.org/packages/mainline/debian/ bookworm nginx" >> /etc/apt/sources.list \
    && apt-get update && apt-get install -y --no-install-recommends --no-install-suggests \
        ca-certificates \
        nginx=${NGINX_VERSION}-${PKG_RELEASE} \
        nginx-module-xslt=${NGINX_VERSION}-${PKG_RELEASE} \
        nginx-module-geoip=${NGINX_VERSION}-${PKG_RELEASE} \
        nginx-module-image-filter=${NGINX_VERSION}-${PKG_RELEASE} \
        nginx-module-perl=${NGINX_VERSION}-${PKG_RELEASE} \
        nginx-module-njs=${NGINX_VERSION}+${NJS_VERSION}-${NJS_RELEASE} \
        gettext-base \
    # Cleanup
    && rm -rf /var/lib/apt/lists/*

# Send logs to docker log collector
RUN ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

EXPOSE 80

# Standard production config
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Custom php config
COPY docker/app/php/php-fpm.conf /usr/local/etc/php-fpm.d/zzz-custom.conf
COPY docker/app/php/php.ini "$PHP_INI_DIR/conf.d/"
COPY docker/app/php/xdebug.ini "$PHP_INI_DIR/disabled/"
COPY --chmod=755 docker/app/php/enable-xdebug /usr/local/bin/enable-xdebug
COPY --chmod=755 docker/app/php/disable-xdebug /usr/local/bin/disable-xdebug

# Custom nginx config
COPY docker/app/nginx /etc/nginx/imported
RUN cp /etc/nginx/imported/nginx.conf /etc/nginx/nginx.conf \
    && cp /etc/nginx/imported/default.conf /etc/nginx/conf.d/default.conf

WORKDIR /var/www

# Install Composer
COPY --from=composer:2.8.4 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1
COPY app/composer.json app/composer.lock ./

RUN if [ -z $DEV_DEPS ] || [ $DEV_DEPS = "0" ]; then \
      composer install --no-dev --optimize-autoloader --no-interaction --no-ansi --no-scripts; \
    else \
      composer install --no-interaction --no-ansi --no-scripts; \
    fi \
    && composer clear-cache

COPY app/. .

COPY docker/app/cont-init.d /etc/cont-init.d/
RUN chmod -R +x /etc/cont-init.d/

COPY docker/app/services.d /etc/services.d/
RUN chmod -R +x /etc/services.d/

ENTRYPOINT ["/init"]

FROM prod AS php_codesniffer

ENV PATH="/root/.composer/vendor/bin:${PATH}"

# Coding standards must be installed with relative paths to work from inside the container and for IDEs
RUN composer global config --no-plugins allow-plugins.dealerdirect/phpcodesniffer-composer-installer false \
    && composer global require doctrine/coding-standard:^12.0 \
    && phpcs --config-set installed_paths ../../doctrine/coding-standard/lib,../../slevomat/coding-standard

ENTRYPOINT ["phpcs"]

FROM prod AS phpunit

ENV PATH="/root/.composer/vendor/bin:${PATH}"

RUN composer global require phpunit/phpunit:^11.5

ENTRYPOINT ["phpunit"]
