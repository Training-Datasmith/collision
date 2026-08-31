#!/usr/bin/env bash
# Cloud grind bootstrap: MySQL + Composer dependency-on-root fix.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

wait_for_mysql() {
    for _ in $(seq 1 45); do
        if mysqladmin ping -h127.0.0.1 -uroot --silent 2>/dev/null; then
            return 0
        fi
        sleep 2
    done
    echo "MySQL did not become ready on 127.0.0.1:3306" >&2
    return 1
}

if ! mysqladmin ping -h127.0.0.1 -uroot --silent 2>/dev/null; then
    if command -v docker >/dev/null 2>&1; then
        docker rm -f collision-mysql 2>/dev/null || true
        docker run -d --name collision-mysql \
            -e MYSQL_ALLOW_EMPTY_PASSWORD=yes \
            -e MYSQL_DATABASE=laravel \
            -p 3306:3306 \
            mysql:8.4
    else
        sudo service mysql start 2>/dev/null || sudo service mariadb start 2>/dev/null || true
    fi
    wait_for_mysql
fi

# Upstream CI pattern: satisfy pestphp/pest -> nunomaduro/collision from root package.
composer config version "8.x-dev"
composer update --prefer-stable --no-interaction --prefer-dist --no-progress --ansi
