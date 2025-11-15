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

## 11. Moduł Zamówień – Plan wdrożenia

### 11.1 Cel i zakres
- Wdrożyć moduł „Zamówienia” obejmujący dane klienta, pozycje, statusy, dokumenty WZ, rezerwacje stanów i integracje z zewnętrznymi źródłami.
- W panelu dodać dział `Zamówienia` z dwiema zakładkami: `Lista zamówień` (widok operacyjny) oraz `Ustawienia` (konfiguracja statusów, numeracji, automatyki, integracji kurierów/płatności).
- Każde zamówienie ma generować dokumenty magazynowe WZ i rezerwacje produktów oraz synchronizować stany z magazynem/integracjami.

### 11.2 Model danych (nowe tabele)
- **orders** – `user_id`, `number`, `source`, `integration_id`, `external_order_id`, `status`, `payment_status`, `fulfillment_status`, `currency`, `totals`, `delivery_method`, `metadata`, timestampy.
- **order_items** – `order_id`, `product_id`, `integration_product_link_id`, snapshot produktu (`name`, `sku`, `ean`), `quantity`, `price_net`, `price_gross`, `vat_rate`, `discount_total`, `warehouse_location_id`, `metadata`.
- **order_addresses** – `order_id`, `type` (`customer`, `billing`, `shipping`, `pickup`), dane kontaktowe i adresowe, `vat_id`, `metadata`; pozwala przechować dane klienta niezależnie od kontrahentów.
- **order_payments** – `order_id`, `provider`, `external_payment_id`, `status`, `amount`, `currency`, `paid_at`, `due_date`, `metadata`.
- **order_shipments** – `order_id`, `carrier`, `service`, `tracking_number`, `labels_path`, `packages`, `status`, `shipped_at`, `metadata`.
- **order_status_history** – `order_id`, `from_status`, `to_status`, `changed_by`, `context`, `created_at`.
- **order_documents** – `order_id`, `warehouse_document_id`, `type` (`reservation`, `issue_wz`, `packing_list`), `status`, `metadata`.
- **order_item_reservations** – `order_item_id`, `warehouse_location_id`, `reserved_qty`, `status` (`open`, `partially_released`, `released`), `expires_at`.
- **order_settings** – preferencje użytkownika: status startowy, słowniki statusów, numeracja zamówień, domyślne magazyny, przełączniki automatyki (auto-rezerwacja, auto-WZ, auto-synchronizacja z PrestaShop).
- **order_notes / order_audit_logs** – historia komunikacji i logi automatycznych działań (opcjonalne, pomocne do timeline’u znanego z BaseLinkera).

### 11.3 Statusy i automaty
- **Status główny (`orders.status`)**:
  - Standardowy flow: `draft` → `awaiting_payment` → `paid` → `awaiting_fulfillment` → `picking` → `ready_for_shipment` → `shipped` → `completed`.
  - Ścieżki boczne: `cancelled` (do momentu wysyłki), `return_requested`, `returned`.
- **Status płatności (`payment_status`)**: `pending`, `partially_paid`, `paid`, `refunded`.
- **Status realizacji (`fulfillment_status`)**: `unassigned`, `reserved`, `picking`, `packed`, `shipped`.
- Przejścia pilnuje `OrderWorkflowService` (walidacja, side effects, event `OrderStatusChanged`). Każda zmiana trafia do `order_status_history` wraz z użytkownikiem/jobem i komentarzem.
- Dostosowanie statusów (nazwy, kolory, mapowania integracji) przechowywane w `order_settings`.

### 11.4 Proces magazynowy i dokumenty
1. **Tworzenie zamówienia** – dane z integracji (Prestashop API/DB) lub manualnie z panelu. Status początkowy zgodnie z ustawieniami użytkownika.
2. **Rezerwacja stanów** – job `ReserveOrderStock` rezerwuje ilości w `warehouse_stock_totals` (`reserved`↑) i zapisuje szczegóły w `order_item_reservations`. Obsługuje częściowe rezerwacje i kolejkę braków.
3. **Dokument rezerwacyjny** – opcjonalny dokument logiczny (np. typ `reservation`) przypięty w `order_documents` dla raportowania i śledzenia braków.
4. **Kompletacja i wydanie** – przejście do `picking/ready_for_shipment` uruchamia `GenerateWarehouseDocumentForOrder`, który:
   - grupuje pozycje wg magazynu,
   - korzysta z `WarehouseDocumentService` do wygenerowania dokumentu `WZ`,
   - odkłada informację w `order_documents` (link do `warehouse_document_id`), a następnie zatwierdza dokument (status `posted`) i aktualizuje `warehouse_stock_totals.on_hand`.
5. **Zwolnienie rezerwacji** – po zatwierdzeniu WZ lub anulowaniu zamówienia job `ReleaseOrderReservation` zmniejsza `reserved` i oznacza rekord jako `released`. W przypadku anulacji generuje dokument storna (np. `IN`).
6. **Aktualizacja integracji** – listener `OrderFulfilled` wywołuje `IntegrationInventorySyncService` i ewentualnie `PrestashopOrderStatusSyncService`, aby zwrócić statusy/tracking.

### 11.5 Panel i API
- Dodaj wpis `Zamówienia` w menu dashboardu (obok `Produkty`/`Integracje`).
- **Lista zamówień** (Inertia):
  - filtry po statusie, płatności, źródle integracji, dacie;
  - kolumny: numer, klient, suma brutto, statusy, przypisany magazyn, integracja;
  - widok szczegółowy z sekcjami jak na screenie (informacje o płatności, adresy, pozycje, dokumenty WZ, historia zmian, rezerwacje, timeline konwersacji).
- **Ustawienia**:
  - konfiguracja statusów (kolejność, kolory, mapowania do integracji);
  - numeracja zamówień (prefiks, sufiks, resetowanie);
  - ustawienia rezerwacji (domyślny magazyn, czas wygaśnięcia rezerwacji, czy rezerwować automatycznie po `awaiting_fulfillment`);
  - konfiguracja przewoźników/kurierów (np. API InPost, DPD), integracji płatności (PayU, Przelewy24);
  - przełączniki automatyki (auto-WZ, auto-zwalnianie rezerwacji przy anulacji).
- Backend:
  - kontrolery: `OrderController`, `OrderSettingsController`, `OrderDocumentController`, `OrderStatusController`, `OrderShipmentController`;
  - zasoby API (`OrderResource`, `OrderItemResource`, `OrderSettingsResource`, `OrderShipmentResource`);
  - polityki (`OrderPolicy`) i testy feature (lista, tworzenie, zmiana statusu, generowanie WZ, anulacja).

### 11.6 Integracje i kolejki
- Utwórz dedykowany worker:

```bash
php artisan queue:work --queue=orders --sleep=3 --tries=3 --max-time=3600
```

- Scheduler:

```php
$schedule->command('orders:sync-external --integration=7')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

$schedule->command('orders:release-expired-reservations')
    ->everyFiveMinutes()
    ->withoutOverlapping();
```

- Rozszerzenia integracji:
  - `PrestashopDatabaseIntegrationDriver` – pobieranie zamówień (tabele `ps_orders`, `ps_order_detail`, `ps_address`, `ps_customer`) + mapowanie statusów do lokalnych.
  - webhooki (np. `orders.updated`) wysyłające statusy i numery listów przewozowych z powrotem do platform e-commerce.
  - możliwość ręcznego przypięcia zamówienia do istniejącej integracji/dokumentu.

### 11.7 Sugestie i dalsze kroki
- Dodać moduł wiadomości przy zamówieniu (email/SMS/chat) + automatyczne makra (np. „poinformuj klienta o wysyłce”).
- Integracja z bramkami płatności (PayU, P24) w celu automatycznego uaktualniania `payment_status`.
- Generator PDF (potwierdzenie zamówienia, WZ, dokument rezerwacji) z możliwością wysyłki mailowej.
- Dashboard KPI dla zamówień (czas realizacji, suma zamówień, procent opóźnionych, poziom rezerwacji).
- Funkcje B2B: udostępnianie klientowi portalu do śledzenia statusu i pobierania dokumentów.
