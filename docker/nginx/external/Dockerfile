FROM node:24-bookworm-slim AS frontend-build

WORKDIR /application

COPY package.json package-lock.json ./

RUN npm ci

COPY public ./public
COPY resources ./resources
COPY vite.config.js ./vite.config.js

RUN npm run build

FROM nginx:1.29-alpine

RUN rm -f /etc/nginx/conf.d/default.conf

COPY docker/nginx/external/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
COPY --from=frontend-build /application/public/build /var/www/html/public/build

RUN rm -f /var/www/html/public/storage \
    && ln -s ../storage/app/public /var/www/html/public/storage

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
