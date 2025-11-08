# Procesy i Joby - Konfiguracja Produkcyjna

## 1. Queue Workers (Workery kolejek)

### Wymagane workery:

```bash
# Worker dla integracji (najważniejszy)
php artisan queue:work --queue=integrations --sleep=3 --tries=3 --max-time=3600

# Worker dla importów
php artisan queue:work --queue=import --sleep=3 --tries=3 --max-time=3600

# Worker domyślny (dla pozostałych zadań)
php artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600
```

### Konfiguracja Docker (compose.yaml):

```yaml
services:
  # Worker dla integracji
  laravel.worker.integrations:
    build:
      context: ./vendor/laravel/sail/runtimes/8.3
      dockerfile: Dockerfile
      args:
        WWWGROUP: '${WWWGROUP}'
    image: sail-8.3/app
    extra_hosts:
      - 'host.docker.internal:host-gateway'
    environment:
      WWWUSER: '${WWWUSER}'
      LARAVEL_SAIL: 1
      XDEBUG_MODE: '${SAIL_XDEBUG_MODE:-off}'
      XDEBUG_CONFIG: '${SAIL_XDEBUG_CONFIG:-client_host=host.docker.internal}'
      IGNITION_LOCAL_SITES_PATH: '${PWD}'
    volumes:
      - '.:/var/www/html'
    networks:
      - sail
    depends_on:
      - pgsql
      - redis
    command: php artisan queue:work --queue=integrations --sleep=3 --tries=3 --max-time=3600 --verbose

  # Worker dla importów
  laravel.worker.import:
    build:
      context: ./vendor/laravel/sail/runtimes/8.3
      dockerfile: Dockerfile
      args:
        WWWGROUP: '${WWWGROUP}'
    image: sail-8.3/app
    extra_hosts:
      - 'host.docker.internal:host-gateway'
    environment:
      WWWUSER: '${WWWUSER}'
      LARAVEL_SAIL: 1
      XDEBUG_MODE: '${SAIL_XDEBUG_MODE:-off}'
      XDEBUG_CONFIG: '${SAIL_XDEBUG_CONFIG:-client_host=host.docker.internal}'
      IGNITION_LOCAL_SITES_PATH: '${PWD}'
    volumes:
      - '.:/var/www/html'
    networks:
      - sail
    depends_on:
      - pgsql
      - redis
    command: php artisan queue:work --queue=import --sleep=3 --tries=3 --max-time=3600 --verbose

  # Worker domyślny
  laravel.worker.default:
    build:
      context: ./vendor/laravel/sail/runtimes/8.3
      dockerfile: Dockerfile
      args:
        WWWGROUP: '${WWWGROUP}'
    image: sail-8.3/app
    extra_hosts:
      - 'host.docker.internal:host-gateway'
    environment:
      WWWUSER: '${WWWUSER}'
      LARAVEL_SAIL: 1
      XDEBUG_MODE: '${SAIL_XDEBUG_MODE:-off}'
      XDEBUG_CONFIG: '${SAIL_XDEBUG_CONFIG:-client_host=host.docker.internal}'
      IGNITION_LOCAL_SITES_PATH: '${PWD}'
    volumes:
      - '.:/var/www/html'
    networks:
      - sail
    depends_on:
      - pgsql
      - redis
    command: php artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600 --verbose
```

## 2. Scheduler (Harmonogram zadań)

### Konfiguracja crona:

```bash
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

### Zaplanowane zadania (app/Console/Kernel.php):

```php
protected function schedule(Schedule $schedule): void
{
    // Import danych dostawcy (co 60 minut)
    $schedule->command('integration:import --integration=6')
        ->hourly()
        ->withoutOverlapping()
        ->onOneServer();

    // Synchronizacja dostępności do PrestaShop Database (co 30 minut)
    $schedule->command('supplier:sync-availability --prestashop=7')
        ->everyThirtyMinutes()
        ->withoutOverlapping()
        ->onOneServer();

    // Alternatywnie: synchronizacja co godzinę o pełnej godzinie
    $schedule->command('supplier:sync-availability --prestashop=7')
        ->hourly()
        ->at(':00')
        ->withoutOverlapping()
        ->onOneServer();

    // Czyszczenie starych logów (codziennie o 2:00)
    $schedule->command('queue:prune-failed --hours=168')
        ->dailyAt('02:00');
}
```

### Dodanie schedulera do Docker (compose.yaml):

```yaml
  laravel.scheduler:
    build:
      context: ./vendor/laravel/sail/runtimes/8.3
      dockerfile: Dockerfile
      args:
        WWWGROUP: '${WWWGROUP}'
    image: sail-8.3/app
    extra_hosts:
      - 'host.docker.internal:host-gateway'
    environment:
      WWWUSER: '${WWWUSER}'
      LARAVEL_SAIL: 1
    volumes:
      - '.:/var/www/html'
    networks:
      - sail
    depends_on:
      - pgsql
      - redis
    command: >
      bash -c "while true; do
        php artisan schedule:run --verbose --no-interaction
        sleep 60
      done"
```

## 3. Joby i Kolejki

### Struktura jobów:

#### Import danych dostawcy (CSV/XML):
```
ExecuteIntegrationImport
  └─> ProcessSupplierAvailabilityChunk (wiele instancji)
        ├─> Aktualizuje supplier_availability w integration_product_links
        └─> Queue: import
```

#### Synchronizacja do PrestaShop Database:
```
SyncSupplierAvailabilityCommand (CLI)
  └─> PrestashopDatabaseSyncService
        ├─> Bezpośrednie UPDATE na MySQL
        ├─> Tabele: ps_stock_available, ps_product_lang
        └─> Wykonywane synchronicznie (brak queue)
```

#### Powiązanie produktów:
```
LinkIntegrationProducts (manual z UI)
  └─> IntegrationProductLinkService::autoLink()
        ├─> Dopasowanie po SKU/EAN
        └─> Queue: integrations
```

## 4. Monitoring i Zarządzanie

### Sprawdzanie statusu kolejek:

```bash
# Lista zadań w kolejce
php artisan queue:monitor integrations,import,default

# Statystyki
php artisan horizon:snapshot  # jeśli używasz Horizon

# Nieudane zadania
php artisan queue:failed

# Ponowne uruchomienie nieudanych
php artisan queue:retry all
```

### Logi:

```bash
# Logi aplikacji
tail -f storage/logs/laravel.log

# Logi workerów (jeśli używasz systemd)
journalctl -u laravel-worker-integrations -f

# W Dockerze
docker logs -f rexbit-laravel.worker.integrations-1
docker logs -f rexbit-laravel.worker.import-1
```

## 5. Przepływ Danych Produkcyjny

### Import danych dostawcy → Synchronizacja PrestaShop:

```
1. Scheduler (co 60 min)
   └─> php artisan integration:import --integration=6
         └─> ExecuteIntegrationImport (Queue: import)
               └─> ProcessSupplierAvailabilityChunk (wiele, Queue: import)
                     ├─> Aktualizuje integration_product_links.supplier_availability
                     └─> Format: {contractor_id, stock_quantity, delivery_days, purchase_price}

2. Scheduler (co 30 min)
   └─> php artisan supplier:sync-availability --prestashop=7
         └─> PrestashopDatabaseSyncService
               ├─> Odczytuje supplier_availability z integration_product_links
               ├─> Porównuje z metadata.last_availability (jeśli sync_only_changed=true)
               ├─> Aplikuje logikę progu (min_stock_threshold, domyślnie 20)
               └─> UPDATE bezpośrednio na MySQL:
                     ├─> ps_stock_available.out_of_stock (0=deny, 1=allow)
                     └─> ps_product_lang.available_now / available_later
```

## 6. Testowanie Importu CSV

### Test importu danych dostawcy:

```bash
# 1. Sprawdź istniejące profile importu
docker exec rexbit-laravel.test-1 php artisan tinker --execute="DB::table('integration_tasks')->where('integration_id', 6)->get(['id', 'name', 'source_location']);"

# 2. Uruchom import ręcznie
docker exec rexbit-laravel.test-1 php artisan integration:import --integration=6 --task=<TASK_ID> --limit=100

# 3. Sprawdź wynik
docker exec rexbit-laravel.test-1 bash -c "echo 'DB::table(\"integration_product_links\")->where(\"integration_id\", 6)->whereNotNull(\"supplier_availability\")->count();' | php artisan tinker"

# 4. Przetestuj synchronizację
docker exec rexbit-laravel.test-1 php artisan supplier:sync-availability --prestashop=7 --limit=10
```

### Sprawdzenie struktury CSV:

```bash
# Zobacz pierwszy wiersz importowanego pliku
docker exec rexbit-laravel.test-1 bash -c "echo 'DB::table(\"integration_tasks\")->where(\"integration_id\", 6)->first([\"last_headers\", \"mappings\"]);' | php artisan tinker"
```

### Wymagane mapowania dla supplier availability:

```json
{
  "sku": "column_name_for_sku",
  "ean": "column_name_for_ean",
  "stock_quantity": "column_name_for_stock",
  "delivery_days": "column_name_for_delivery",
  "purchase_price": "column_name_for_price"
}
```

## 7. Rozwiązywanie Problemów

### Problem: Worker przestał działać

```bash
# Restart workerów w Docker
docker restart rexbit-laravel.worker.integrations-1
docker restart rexbit-laravel.worker.import-1

# Sprawdź czy są zablokowane zadania
php artisan queue:restart
```

### Problem: Zadania się nie wykonują

```bash
# Sprawdź connection w .env
QUEUE_CONNECTION=redis  # lub database

# Sprawdź Redis
docker exec rexbit-redis-1 redis-cli PING

# Wyczyść stuck jobs
php artisan queue:flush
php artisan queue:restart
```

### Problem: Synchronizacja nie aktualizuje PrestaShop

```bash
# Sprawdź połączenie z bazą MySQL
docker exec rexbit-laravel.test-1 bash -c "php artisan tinker --execute=\"DB::connection('pgsql')->table('integrations')->where('id', 7)->value('config');\""

# Test bezpośredniego połączenia
docker exec rexbit-laravel.test-1 php artisan integration:test --integration=7

# Sprawdź logi
tail -f storage/logs/laravel.log | grep "PrestaShop DB"
```

## 8. Metryki Wydajności

### Aktualne wyniki (integracja #7 - PrestaShop Database):

- **10 produktów**: ~2 sekundy
- **100 produktów**: ~2 sekundy
- **1000 produktów**: ~20 sekund (szacowane)
- **12,465 produktów**: ~250 sekund (~4 minuty)

### Porównanie z API:

| Liczba produktów | API (PRESTASHOP) | Database (PRESTASHOP_DB) | Przyspieszenie |
|------------------|------------------|--------------------------|----------------|
| 100 | ~20s | ~2s | **10x** |
| 1,000 | ~3.3min | ~20s | **10x** |
| 10,000 | ~33min | ~3.3min | **10x** |
| 17,000 | ~57min | ~5.7min | **10x** |

## 9. Checklist Uruchomienia Produkcyjnego

- [ ] Skonfigurować workery w Docker (compose.yaml)
- [ ] Dodać scheduler do Docker (compose.yaml)
- [ ] Skonfigurować harmonogram w Kernel.php
- [ ] Ustawić QUEUE_CONNECTION=redis w .env
- [ ] Włączyć product_listing_enabled dla integracji #7
- [ ] Skonfigurować ustawienia synchronizacji (min_stock_threshold=20)
- [ ] Przetestować import CSV z danymi dostawcy
- [ ] Przetestować synchronizację na 100 produktach
- [ ] Uruchomić pełną synchronizację wszystkich produktów
- [ ] Skonfigurować monitoring logów (opcjonalnie Horizon)
- [ ] Ustawić alerty dla nieudanych zadań (opcjonalnie)

## 10. Komendy Produkcyjne

```bash
# Uruchomienie workerów (jeśli nie Docker)
supervisorctl start laravel-worker:*

# Restart po deploy
php artisan queue:restart

# Monitoring
php artisan queue:monitor integrations,import,default --max=100

# Statystyki
php artisan queue:work integrations --once  # Test pojedynczego joba
```
