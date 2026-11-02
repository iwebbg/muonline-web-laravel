#!/usr/bin/env bash

set -euo pipefail

: "${APP_DOMAIN:?Set APP_DOMAIN in docker/.env}"
: "${NGINX_SSL_EMAIL:?NGINX_SSL_EMAIL was not derived from APP_DOMAIN}"

if [[ ! "$APP_DOMAIN" =~ ^[a-zA-Z0-9.-]+$ ]] || [[ "$APP_DOMAIN" != *.* ]]; then
    echo "APP_DOMAIN must be a valid public domain name." >&2
    exit 1
fi

certificate_path="/etc/letsencrypt/live/${APP_DOMAIN}/fullchain.pem"

if [ ! -f "$certificate_path" ]; then
    echo "No certificate found for ${APP_DOMAIN}. Requesting one from Let's Encrypt."

    certbot certonly \
        --standalone \
        --non-interactive \
        --agree-tos \
        --email "$NGINX_SSL_EMAIL" \
        --domain "$APP_DOMAIN"
else
    echo "Existing certificate found for ${APP_DOMAIN}."
fi

envsubst '${APP_DOMAIN}' \
    < /etc/nginx/templates/default.conf.template \
    > /etc/nginx/conf.d/default.conf

nginx -t

service cron start

exec nginx -g "daemon off;"
