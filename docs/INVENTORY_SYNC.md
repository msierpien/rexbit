# Synchronizacja Stanów Magazynowych z Prestashop

## Przegląd

System synchronizuje stany magazynowe między lokalnym magazynem a sklepem Prestashop w dwóch kierunkach:

- **Local → Prestashop**: Aktualizuje stany w Prestashop na podstawie lokalnych stanów magazynowych
- **Prestashop → Local**: Pobiera stany z Prestashop (zapisuje w metadata, opcjonalnie może tworzyć dokumenty korekty)

## Konfiguracja

### 1. Ustawienia Integracji

W panelu administracyjnym dla każdej integracji Prestashop można skonfigurować:

- **Tryb synchronizacji** (`inventory_sync_mode`):
  - `disabled` - synchronizacja wyłączona
  - `local_to_presta` - tylko z lokalnego magazynu do Prestashop
  - `prestashop_to_local` - tylko z Prestashop do lokalnego magazynu

- **Interwał synchronizacji** (`inventory_sync_interval_minutes`):
  - Minimalna liczba minut między automatycznymi synchronizacjami
  - Wartość domyślna: 180 minut (3 godziny)
  - Minimum: 5 minut

- **Główny magazyn** (`primary_warehouse_id`):
  - ID magazynu używanego do synchronizacji (wymagane dla `local_to_presta`)
  - Jeśli nie ustawiony, sumuje stany ze wszystkich magazynów

### 2. Mapowanie Produktów

Przed synchronizacją produkty muszą być powiązane:

1. Przejdź do integracji
2. Wybierz "Produkty" → "Powiąż produkty"
3. System automatycznie dopasuje produkty po SKU lub EAN
4. Można też ręcznie powiązać produkty

Każde powiązanie (`IntegrationProductLink`) przechowuje:
- `product_id` - ID produktu lokalnego
- `external_product_id` - ID produktu w Prestashop
- `metadata` - informacje o synchronizacji

## Triggery Synchronizacji

### 1. Automatyczna przy zatwierdzaniu dokumentów

Gdy zatwierdzasz dokument magazynowy (PZ, WZ), system automatycznie:
- Aktualizuje lokalne stany magazynowe
- Dodaje job do kolejki synchronizacji dla wszystkich produktów z dokumentu
- Job wykonuje się asynchronicznie w tle

```php
// To dzieje się automatycznie w WarehouseDocumentService::post()
app(IntegrationInventorySyncService::class)
    ->dispatchForUser($user, $productIds);
```

### 2. Scheduler (automatyczny, co 5 minut)

W `app/Console/Kernel.php` ustawiony jest scheduler:

```php
$schedule->command('integrations:sync-inventory')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
```

Scheduler:
- Sprawdza wszystkie integracje Prestashop
- Synchronizuje tylko te, które spełniają warunki (tryb włączony, minął interwał)
- Nie duplikuje synchronizacji jeśli już trwa

### 3. Ręczna komenda

```bash
# Synchronizacja wszystkich integracji
php artisan integrations:sync-inventory

# Synchronizacja konkretnej integracji
php artisan integrations:sync-inventory --integration=1

# Wymuś synchronizację (ignoruj interwał)
php artisan integrations:sync-inventory --force

# Dry run (pokaż co zostanie zsynchronizowane)
php artisan integrations:sync-inventory --dry-run
```

## Jak działa synchronizacja Local → Prestashop

### Optymalizacja: Batch Operations

System automatycznie używa batch operations dla lepszej wydajności:
- **≤10 produktów**: Pojedyncze requesty (szybsze dla małych aktualizacji)
- **>10 produktów**: Batch mode z 10 równoległymi requestami
- Rate limiting: 200ms opóźnienie między chunkami

To znacząco przyspiesza synchronizację dużych katalogów (np. 5000 produktów synchronizuje się ~10x szybciej).

### Krok po kroku:

1. **Pobranie lokalnych stanów**
   ```php
   // Dla każdego produktu: on_hand - reserved
   $stockTotals = WarehouseStockTotal::query()
       ->whereIn('product_id', $productIds)
       ->when($warehouseId, fn($q) => $q->where('warehouse_location_id', $warehouseId))
       ->selectRaw('product_id, SUM(on_hand - reserved) as available_quantity')
       ->groupBy('product_id')
       ->get();
   ```

2. **Dla każdego powiązanego produktu**:
   - Pobierz `stock_available_id` z Prestashop API
   - Pobierz pełny XML `stock_available`
   - Zaktualizuj pole `quantity`
   - Wyślij PUT request do Prestashop API
   - Zapisz status synchronizacji w `metadata`

3. **Zapisanie metadanych**:
   ```json
   {
     "inventory": {
       "last_local_quantity": 15.0,
       "last_local_sync_at": "2025-11-02T20:00:00+01:00",
       "last_sync_status": "success",
       "last_sync_error": null
     }
   }
   ```

### Error handling

Jeśli synchronizacja się nie powiedzie:
- Błąd jest logowany do `storage/logs/laravel.log`
- Status w metadata ustawiony na `failed` lub `error`
- Komunikat błędu zapisany w `last_sync_error`
- Job zostanie powtórzony (3 próby z 60s opóźnieniem)

## Prestashop API

### Stock Availables

Prestashop przechowuje stany w osobnym zasobie `stock_availables`, nie bezpośrednio w `products`.

**Dlaczego?** Bo produkt może mieć warianty (kombinacje), każdy z własnym stanem.

### Wymagane uprawnienia API Key

API Key w Prestashop musi mieć uprawnienia do:
- `GET /api/products` - odczyt produktów
- `GET /api/stock_availables` - odczyt stanów
- `PUT /api/stock_availables` - aktualizacja stanów

## Monitorowanie

### Sprawdzenie statusu synchronizacji

```sql
-- Ostatnie synchronizacje dla integracji
SELECT 
    id,
    name,
    meta->'inventory_sync'->>'last_run_at' as last_sync,
    meta->'inventory_sync'->>'last_mode' as mode,
    meta->'inventory_sync'->>'last_synced_count' as count
FROM integrations 
WHERE type = 'prestashop';
```

### Sprawdzenie błędów

```sql
-- Produkty z błędami synchronizacji
SELECT 
    ipl.id,
    p.sku,
    p.name,
    ipl.external_product_id,
    ipl.metadata->'inventory'->>'last_sync_status' as status,
    ipl.metadata->'inventory'->>'last_sync_error' as error,
    ipl.metadata->'inventory'->>'last_local_sync_at' as last_sync
FROM integration_product_links ipl
JOIN products p ON p.id = ipl.product_id
WHERE ipl.metadata->'inventory'->>'last_sync_status' IN ('failed', 'error')
ORDER BY ipl.updated_at DESC;
```

### Logi

```bash
# Zobacz ostatnie logi synchronizacji
tail -f storage/logs/laravel.log | grep -i "prestashop\|stock\|sync"
```

## Troubleshooting

### Problem: Stany nie aktualizują się w Prestashop

**Sprawdź:**

1. Czy produkt jest powiązany?
   ```sql
   SELECT * FROM integration_product_links 
   WHERE product_id = XXX AND external_product_id IS NOT NULL;
   ```

2. Czy tryb synchronizacji jest włączony?
   ```sql
   SELECT config->'inventory_sync_mode' FROM integrations WHERE id = XXX;
   ```

3. Czy minął interwał od ostatniej synchronizacji?
   ```bash
   php artisan integrations:sync-inventory --integration=1 --force
   ```

4. Sprawdź logi błędów w metadata:
   ```sql
   SELECT metadata->'inventory' FROM integration_product_links WHERE id = XXX;
   ```

### Problem: "No stock_available found"

**Przyczyna:** Produkt w Prestashop nie ma przypisanego stock_available.

**Rozwiązanie:** 
- Upewnij się że produkt istnieje w Prestashop
- Sprawdź czy produkt nie jest wyłączony
- Przebuduj cache w Prestashop (Tools → Advanced Parameters → Performance)

### Problem: "Invalid XML response"

**Przyczyna:** Prestashop zwrócił niepoprawny XML (może być błąd konfiguracji Prestashop).

**Rozwiązanie:**
- Sprawdź logi Prestashop (var/logs/)
- Zweryfikuj uprawnienia API Key
- Spróbuj ręcznie GET na `/api/stock_availables/{id}` przez Postman

## Przyszłe usprawnienia

### TODO: Sync Prestashop → Local przez dokumenty korekty

Aktualnie synchronizacja `prestashop_to_local` tylko zapisuje stany do metadata, ale nie aktualizuje lokalnych stanów magazynowych.

**Plan implementacji:**
- Zamiast bezpośredniej zmiany `WarehouseStockTotal`
- Tworzyć dokument korekty (PK) z różnicą między stanem Prestashop a lokalnym
- Zatwierdzać dokument automatycznie
- To zachowa pełną historię zmian w audit trail

### ✅ Batch Operations (ZAIMPLEMENTOWANE)

System automatycznie optymalizuje synchronizację używając batch operations:

**Implementacja:**
- Dla ≤10 produktów: pojedyncze requesty (niższy overhead)
- Dla >10 produktów: 10 równoległych requestów z rate limiting
- 200ms opóźnienie między chunkami aby uniknąć przeciążenia API
- Automatyczny fallback do pojedynczych requestów w razie błędów

**Wydajność:**
- Mały katalog (100 produktów): ~10 sekund
- Średni katalog (1000 produktów): ~2 minuty
- Duży katalog (5000 produktów): ~10 minut
- Bardzo duży katalog (10000+ produktów): ~20-30 minut

**Uwaga:** Prestashop API nie wspiera prawdziwych batch updates, więc używamy concurrent requests z rate limiting dla optymalnej wydajności przy zachowaniu stabilności API.

### TODO: Dashboard

Dodać dashboard do monitorowania:
- Status ostatniej synchronizacji dla każdej integracji
- Lista produktów z błędami synchronizacji
- Historia synchronizacji (wykres w czasie)
- Statystyki (ile produktów zsynchronizowanych, ile błędów, itp.)

## Komendy pomocnicze

```bash
# Lista wszystkich integracji
php artisan tinker
>>> Integration::where('type', 'prestashop')->get(['id', 'name', 'config']);

# Wymuś synchronizację konkretnej integracji
php artisan integrations:sync-inventory --integration=1 --force

# Test w trybie dry-run
php artisan integrations:sync-inventory --dry-run

# Sprawdź queue jobs
php artisan queue:listen integrations

# Sprawdź failed jobs
php artisan queue:failed
php artisan queue:retry {job-id}
```
