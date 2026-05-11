#!/bin/sh
set -eu

APP_UID="${APP_UID:-1000}"
APP_GID="${APP_GID:-1000}"
APP_USER="${APP_USER:-app}"
APP_GROUP="${APP_GROUP:-app}"

mkdir -p \
    /var/www/html/bootstrap/cache \
    /var/www/html/public \
    /var/www/html/resources/js/actions \
    /var/www/html/resources/js/routes \
    /var/www/html/resources/js/wayfinder \
    /var/www/html/storage/app \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/testing \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs

if [ "$(id -u)" = "0" ]; then
    chown -R "${APP_UID}:${APP_GID}" \
        /var/www/html/bootstrap/cache \
        /var/www/html/public \
        /var/www/html/resources/js/actions \
        /var/www/html/resources/js/routes \
        /var/www/html/resources/js/wayfinder \
        /var/www/html/storage \
        /var/www/html/vendor \
        /var/www/html/node_modules

    chmod -R ug+rwX \
        /var/www/html/bootstrap/cache \
        /var/www/html/public \
        /var/www/html/resources/js/actions \
        /var/www/html/resources/js/routes \
        /var/www/html/resources/js/wayfinder \
        /var/www/html/storage

    exec gosu "${APP_USER}:${APP_GROUP}" "$@"
fi

chmod -R ug+rwX \
    /var/www/html/bootstrap/cache \
    /var/www/html/public \
    /var/www/html/resources/js/actions \
    /var/www/html/resources/js/routes \
    /var/www/html/resources/js/wayfinder \
    /var/www/html/storage || true

exec "$@"
