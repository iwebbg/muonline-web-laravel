FROM node:24-bookworm-slim AS frontend-build

WORKDIR /application

COPY package.json package-lock.json ./

RUN npm ci

COPY public ./public
COPY resources ./resources
COPY vite.config.js ./vite.config.js

RUN npm run build

FROM nginx:1.29-bookworm

RUN apt-get update \
    && apt-get install --yes --no-install-recommends \
        ca-certificates \
        certbot \
        cron \
        curl \
        gettext-base \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/conf.d/default.conf

COPY docker/nginx/prod/default.conf.template /etc/nginx/templates/default.conf.template
COPY docker/nginx/prod/startup.sh /usr/local/bin/muonline-nginx-startup
COPY docker/nginx/prod/certbot-renew.sh /usr/local/bin/muonline-certbot-renew
COPY public /var/www/html/public
COPY --from=frontend-build /application/public/build /var/www/html/public/build

RUN rm -f /var/www/html/public/storage \
    && ln -s ../storage/app/public /var/www/html/public/storage \
    && chmod +x \
        /usr/local/bin/muonline-nginx-startup \
        /usr/local/bin/muonline-certbot-renew \
    && printf '%s\n' \
        '17 3,15 * * * /usr/local/bin/muonline-certbot-renew >> /var/log/certbot-renew.log 2>&1' \
        > /etc/cron.d/certbot-renew \
    && chmod 0644 /etc/cron.d/certbot-renew \
    && crontab /etc/cron.d/certbot-renew

EXPOSE 80 443

CMD ["/usr/local/bin/muonline-nginx-startup"]
