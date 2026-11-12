FROM php:8.5.12-cli-bookworm

ARG UID=1000
ARG GID=1000

ENV COMPOSER_HOME=/tmp/composer \
    HOME=/tmp \
    TZ=America/Sao_Paulo

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        zip \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql bcmath zip \
    && echo "date.timezone=America/Sao_Paulo" > /usr/local/etc/php/conf.d/timezone.ini \
    && groupadd --force --gid "${GID}" fintrack \
    && useradd --non-unique --uid "${UID}" --gid "${GID}" --create-home --home-dir /home/fintrack --shell /bin/bash fintrack \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.11.2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
