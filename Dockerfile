# Dev-образ для роботи з пакетом без локального PHP:
# PHP 7.3 (мінімальна підтримувана версія) + Composer + pcov для покриття.
#
#   docker build -t partner-sdk .
#   docker run --rm -v "$PWD":/app partner-sdk composer install
#   docker run --rm -v "$PWD":/app partner-sdk vendor/bin/phpunit
#   docker run --rm -v "$PWD":/app partner-sdk vendor/bin/phpunit --coverage-text
FROM php:7.3-cli

# git та unzip потрібні Composer для завантаження пакетів
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

# pcov — драйвер покриття коду для PHPUnit
RUN pecl install pcov \
    && docker-php-ext-enable pcov

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

CMD ["vendor/bin/phpunit"]
