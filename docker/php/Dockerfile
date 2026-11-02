FROM php:8.5-fpm-bookworm AS php-base

ARG SQLSRV_VERSION=5.13.3

ENV DEBIAN_FRONTEND=noninteractive
ENV ACCEPT_EULA=Y

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install --yes --no-install-recommends \
        ca-certificates \
        curl \
        git \
        gnupg \
        libfcgi-bin \
        libicu-dev \
        libonig-dev \
        libzip-dev \
        unzip \
    && curl --fail --show-error --silent --location \
        https://packages.microsoft.com/config/debian/12/packages-microsoft-prod.deb \
        --output /tmp/packages-microsoft-prod.deb \
    && dpkg --install /tmp/packages-microsoft-prod.deb \
    && rm /tmp/packages-microsoft-prod.deb \
    && apt-get update \
    && apt-get install --yes --no-install-recommends \
        msodbcsql18 \
        unixodbc-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        pcntl \
        zip \
    && pecl install \
        "sqlsrv-${SQLSRV_VERSION}" \
        "pdo_sqlsrv-${SQLSRV_VERSION}" \
        redis \
    && docker-php-ext-enable \
        sqlsrv \
        pdo_sqlsrv \
        redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-muonline.ini
COPY docker/php/entrypoint.sh /usr/local/bin/muonline-entrypoint

RUN chmod +x /usr/local/bin/muonline-entrypoint

ENTRYPOINT ["muonline-entrypoint"]
CMD ["php-fpm"]

FROM php-base AS development

ENV APP_ENV=local

FROM node:24-bookworm-slim AS frontend-build

WORKDIR /application

COPY package.json package-lock.json ./

RUN npm ci

COPY public ./public
COPY resources ./resources
COPY vite.config.js ./vite.config.js

RUN npm run build

FROM php-base AS production

ENV APP_ENV=production
ENV APP_DEBUG=false

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY --chown=www-data:www-data . .
COPY --from=frontend-build --chown=www-data:www-data /application/public/build ./public/build

RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-interaction

USER www-data
