# Production image for UIDesired / Docker deployments.
# Rebuild and push: docker build -f docker/Dockerfile -t jhay555/uidesired:uplary .

FROM php:8.3-fpm-bookworm

ARG NODE_VERSION=22

ENV APP_ROOT=/var/www/html \
    APP_PORT=8080 \
    APP_ENV=production \
    APP_DEBUG=false

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl ca-certificates supervisor \
        nginx \
        libzip-dev libpng-dev libonig-dev libxml2-dev libicu-dev \
        libpq-dev default-mysql-client \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath ctype curl dom fileinfo intl mbstring pdo_mysql pdo_pgsql zip opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - \
    && apt-get install -y nodejs \
    && curl -fsSL https://dl.cloudflare.com/cloudflare-client/current/cloudflare-main.gpg -o /usr/share/keyrings/cloudflare-main.gpg || true \
    && curl -1sLf 'https://dl.cloudflare.com/cloudflare-client/current/caddy/stable/linux/amd64/deb/caddy-stable.list' -o /etc/apt/sources.list.d/caddy-stable.list 2>/dev/null || true \
    && (apt-get update && apt-get install -y caddy) || (curl -fsSL "https://github.com/caddyserver/caddy/releases/download/v2.8.4/caddy_2.8.4_linux_amd64.tar.gz" | tar -xz -C /usr/bin caddy) \
    && mkdir -p /etc/caddy /var/log/caddy /data/caddy /config/caddy /run/php \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR ${APP_ROOT}

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist

COPY package.json package-lock.json* ./
RUN if [ -f package-lock.json ]; then npm ci; elif [ -f package.json ]; then npm install; fi

COPY . .
RUN composer dump-autoload --optimize \
    && if [ -f package.json ]; then npm run build; fi \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

COPY docker/supervisord.conf /etc/supervisor/conf.d/uplary.conf
COPY docker/uplary-entrypoint.sh /usr/local/bin/uplary-entrypoint.sh
RUN chmod +x /usr/local/bin/uplary-entrypoint.sh

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS "http://127.0.0.1:${APP_PORT}/up" || curl -fsS "http://127.0.0.1:${APP_PORT}/" || exit 1

ENTRYPOINT ["/usr/local/bin/uplary-entrypoint.sh"]
