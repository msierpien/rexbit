# Docker - Uruchamianie Queue Workerów w Tle

## 1. Supervisor (Zalecany sposób)

Supervisor to proces manager który automatycznie uruchamia i monitoruje workery.

### Instalacja w kontenerze Laravel Sail

Dodaj do `docker-compose.yml`:

```yaml
services:
  laravel.test:
    build:
      context: './vendor/laravel/sail/runtimes/8.3'
      dockerfile: Dockerfile
      args:
        WWWGROUP: '${WWWGROUP}'
    image: sail-8.3/app
    extra_hosts:
      - 'host.docker.internal:host-gateway'
    ports:
      - '${APP_PORT:-80}:80'
      - '${VITE_PORT:-5173}:${VITE_PORT:-5173}'
    environment:
      WWWUSER: '${WWWUSER}'
      LARAVEL_SAIL: 1
      XDEBUG_MODE: '${SAIL_XDEBUG_MODE:-off}'
      XDEBUG_CONFIG: '${SAIL_XDEBUG_CONFIG:-client_host=host.docker.internal}'
      IGNITION_LOCAL_SITES_PATH: '${PWD}'
    volumes:
      - '.:/var/www/html'
      - './docker/supervisor:/etc/supervisor/conf.d' # Dodaj tę linię
    networks:
      - sail
    depends_on:
      - pgsql
```

### Konfiguracja Supervisor

Utwórz folder i pliki:

```bash
mkdir -p docker/supervisor
```

**Plik `docker/supervisor/laravel-worker.conf`:**

```ini
[program:laravel-queue-import]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --queue=import --timeout=300 --sleep=3 --tries=3 --max-jobs=1000
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=sail
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker-import.log
stopwaitsecs=3600

[program:laravel-queue-integrations]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --queue=integrations --timeout=300 --sleep=3 --tries=3 --max-jobs=1000
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=sail
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker-integrations.log
stopwaitsecs=3600

[program:laravel-queue-default]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --queue=default --timeout=60 --sleep=3 --tries=3 --max-jobs=1000
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=sail
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker-default.log
stopwaitsecs=3600

[program:laravel-cron]
process_name=%(program_name)s
command=/usr/sbin/cron -f
autostart=true
autorestart=true
user=root
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/cron.log
```

### Customowy Dockerfile

Utwórz `docker/8.3/Dockerfile`:

```dockerfile
FROM ubuntu:22.04

LABEL maintainer="Taylor Otwell"

ARG WWWGROUP
ARG NODE_VERSION=20
ARG POSTGRES_VERSION=15

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND noninteractive
ENV TZ=UTC

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Instalacja podstawowych pakietów + supervisor
RUN apt-get update \
    && mkdir -p /etc/apt/keyrings \
    && apt-get install -y gnupg gosu curl ca-certificates zip unzip git supervisor sqlite3 libcap2-bin libpng-dev python2 dnsutils librsvg2-bin fswatch \
    && curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x14aa40ec0831756756d7f66c4f4ea0aae5267a6c' | gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu jammy main" > /etc/apt/sources.list.d/ppa_ondrej_php.list \
    && apt-get update \
    && apt-get install -y php8.3-cli php8.3-dev \
       php8.3-pgsql php8.3-sqlite3 php8.3-gd php8.3-imagick \
       php8.3-curl \
       php8.3-imap php8.3-mysql php8.3-mbstring \
       php8.3-xml php8.3-zip php8.3-bcmath php8.3-soap \
       php8.3-intl php8.3-readline \
       php8.3-ldap \
       php8.3-msgpack php8.3-igbinary php8.3-redis php8.3-swoole \
       php8.3-memcached php8.3-pcov php8.3-imagick php8.3-xdebug \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y nodejs \
    && npm install -g npm \
    && npm install -g pnpm \
    && npm install -g bun \
    && curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | gpg --dearmor | tee /etc/apt/keyrings/yarn.gpg >/dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/yarn.gpg] https://dl.yarnpkg.com/debian/ stable main" > /etc/apt/sources.list.d/yarn.list \
    && curl -sS https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor | tee /etc/apt/keyrings/pgdg.gpg >/dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/pgdg.gpg] http://apt.postgresql.org/pub/repos/apt jammy-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get install -y yarn \
    && apt-get install -y mysql-client \
    && apt-get install -y postgresql-client-$POSTGRES_VERSION \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN setcap "cap_net_bind_service=+ep" /usr/bin/php8.3

RUN groupadd --force -g $WWWGROUP sail
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail

# Konfiguracja supervisor
RUN mkdir -p /var/log/supervisor
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Cron setup
RUN apt-get update && apt-get install -y cron
COPY docker/cron/laravel-cron /etc/cron.d/laravel-cron
RUN chmod 0644 /etc/cron.d/laravel-cron
RUN crontab /etc/cron.d/laravel-cron

COPY docker/8.3/start-container /usr/local/bin/start-container
COPY docker/8.3/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/8.3/php.ini /etc/php/8.3/cli/conf.d/99-sail.ini
RUN chmod +x /usr/local/bin/start-container

EXPOSE 8000

ENTRYPOINT ["start-container"]
```

## 2. Docker Compose with Command

Alternatywnie, dodaj osobny serwis dla każdej kolejki:

```yaml
services:
  laravel.test:
    # ... istniejąca konfiguracja
  
  queue-import:
    build:
      context: './vendor/laravel/sail/runtimes/8.3'
      dockerfile: Dockerfile
    image: sail-8.3/app
    environment:
      WWWUSER: '${WWWUSER}'
      LARAVEL_SAIL: 1
    volumes:
      - '.:/var/www/html'
    networks:
      - sail
    depends_on:
      - pgsql
    command: php artisan queue:work --queue=import --timeout=300 --sleep=3 --tries=3
    restart: unless-stopped

  queue-integrations:
    build:
      context: './vendor/laravel/sail/runtimes/8.3'
      dockerfile: Dockerfile
    image: sail-8.3/app
    environment:
      WWWUSER: '${WWWUSER}'
      LARAVEL_SAIL: 1
    volumes:
      - '.:/var/www/html'
    networks:
      - sail
    depends_on:
      - pgsql
    command: php artisan queue:work --queue=integrations --timeout=300 --sleep=3 --tries=3
    restart: unless-stopped

  cron:
    build:
      context: './vendor/laravel/sail/runtimes/8.3'
      dockerfile: Dockerfile  
    image: sail-8.3/app
    environment:
      WWWUSER: '${WWWUSER}'
      LARAVEL_SAIL: 1
    volumes:
      - '.:/var/www/html'
    networks:
      - sail
    depends_on:
      - pgsql
    command: bash -c "cron && tail -f /var/log/cron.log"
    restart: unless-stopped
```

## 3. Cron Setup

Utwórz `docker/cron/laravel-cron`:

```bash
# Laravel Queue Workers
*/5 * * * * cd /var/www/html && php artisan integrations:run-imports >> /var/www/html/storage/logs/cron.log 2>&1
0 * * * * cd /var/www/html && php artisan integrations:sync-inventory >> /var/www/html/storage/logs/cron.log 2>&1
0 3 * * * cd /var/www/html && php artisan queue:restart >> /var/www/html/storage/logs/cron.log 2>&1
```

## 4. Komenda do Uruchamiania

```bash
# Uruchom wszystko w tle
./vendor/bin/sail up -d

# Sprawdź status
./vendor/bin/sail ps

# Logi workerów
./vendor/bin/sail logs queue-import -f
./vendor/bin/sail logs queue-integrations -f

# Restart konkretnego workera
./vendor/bin/sail restart queue-import
```

## 5. Monitorowanie

```bash
# Status supervisor
./vendor/bin/sail exec laravel.test supervisorctl status

# Restart workerów przez supervisor
./vendor/bin/sail exec laravel.test supervisorctl restart laravel-queue-import:*

# Logi workerów
./vendor/bin/sail exec laravel.test tail -f /var/www/html/storage/logs/worker-import.log
```

**Zalecany sposób**: Użyj **Supervisor** - jest to najbardziej niezawodne rozwiązanie do zarządzania workerami w tle w środowisku produkcyjnym.