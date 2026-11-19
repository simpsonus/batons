# Bâtons — image Docker générique (nginx + php-fpm sur Alpine).
# Contexte de build : la racine du dépôt.   docker build -f docker/Dockerfile -t batons .
FROM alpine:3.21

ARG TARGETARCH
ARG BATONS_VERSION=dev

RUN apk add --no-cache \
        nginx \
        php83 php83-fpm php83-opcache php83-session php83-mbstring php83-ctype \
        php83-pdo php83-pdo_sqlite php83-pdo_pgsql php83-pdo_mysql php83-openssl \
        tzdata \
    && ln -sf /usr/sbin/php-fpm83 /usr/sbin/php-fpm

COPY docker/nginx.conf      /etc/nginx/http.d/default.conf
COPY docker/php-fpm.conf    /etc/php83/php-fpm.d/www.conf
COPY docker/php.ini         /etc/php83/conf.d/99-batons.ini
COPY docker/entrypoint.sh   /entrypoint.sh
COPY app/                   /app/

# Icônes Material Design (celles de Home Assistant), téléchargées à la construction.
RUN chmod +x /entrypoint.sh && rm -rf /app/data /app/icons \
    && php83 /app/bin/fetch-icons.php

ENV BATONS_DATA_DIR=/data
VOLUME /data
EXPOSE 8080

# Étiquettes lues par Home Assistant (ignorées partout ailleurs).
LABEL org.opencontainers.image.title="Bâtons" \
      org.opencontainers.image.description="Compter et dater tout ce qui compte." \
      org.opencontainers.image.source="https://github.com/simpsonus/batons" \
      org.opencontainers.image.version="${BATONS_VERSION}" \
      io.hass.type="addon" \
      io.hass.version="${BATONS_VERSION}" \
      io.hass.arch="${TARGETARCH}"

HEALTHCHECK --interval=60s --timeout=5s CMD wget -qO- "http://127.0.0.1:8080/?r=health" || exit 1

ENTRYPOINT ["/entrypoint.sh"]
