#!/usr/bin/env bash

set -euo pipefail

certbot renew \
    --webroot \
    --webroot-path /var/www/certbot \
    --quiet \
    --deploy-hook "nginx -s reload"
