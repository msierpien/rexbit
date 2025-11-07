# 🚀 Supplier Availability - Quick Start

## Czym Jest Ta Funkcjonalność?

System importu dostępności produktów od dostawców i automatycznej synchronizacji do PrestaShop.

**Przykład**: Dostawca dostarcza CSV z informacją, które produkty są dostępne. System:
1. Importuje CSV codziennie (automatycznie)
2. Aktualizuje informacje o dostępności produktów
3. Synchronizuje do PrestaShop (ustawia "allow backorder" + "available later" label)

## Architektura (Używa Istniejącej Infrastruktury!)

### Co już mamy:
- ✅ Tabela `contractors` z `is_supplier` boolean
- ✅ System `IntegrationTask` (obsługuje CSV import, mappings, scheduler)
- ✅ Tabela `integration_product_links` (linkuje produkty między systemami)

### Co dodajemy:
- ➕ Kolumna `supplier_availability` (JSONB) do `integration_product_links`
- ➕ Service `SupplierAvailabilityImportService` (import CSV → JSONB)
- ➕ Service `SupplierAvailabilitySyncService` (JSONB → PrestaShop API)
- ➕ Command `supplier:sync-to-prestashop`

## Struktura Danych

### integration_product_links.supplier_availability (JSONB)
```json
{
  "is_available": true,
  "stock_quantity": 50,
  "delivery_days": 3,
  "supplier_sku": "ABC123",
  "contractor_id": 123,
  "last_checked_at": "2025-01-07T10:00:00Z",
  "last_status_change_at": "2025-01-05T08:30:00Z"
}
```

## Przykładowy CSV od Dostawcy

```csv
sku,dostepny,stan,dni_dostawy
ABC123,1,50,3
XYZ789,0,0,7
DEF456,1,25,2
```

## Konfiguracja (5 minut)

### 1. Utwórz Integrację CSV dla Dostawcy

```php
// Tinker lub UI
$integration = Integration::create([
    'user_id' => 1,
    'type' => IntegrationType::CSV_XML_IMPORT,
    'name' => 'Dostawca ABC - dostępność',
    'config' => [
        'contractor_id' => 123, // ID z tabeli contractors (where is_supplier=true)
    ],
    'is_active' => true,
]);
```

### 2. Utwórz IntegrationTask

```php
$task = IntegrationTask::create([
    'integration_id' => $integration->id,
    'task_type' => 'import',
    'resource_type' => 'supplier-availability', // WAŻNE!
    'format' => 'csv',
    'source_location' => 'https://dostawca.com/stock.csv',
    'fetch_mode' => 'daily',
    'fetch_interval' => 720, // 12 godzin
    'mappings' => [
        'supplier_sku' => 'sku',       // CSV kolumna 'sku' → supplier_sku
        'is_available' => 'dostepny',   // CSV kolumna 'dostepny' → is_available
        'stock_quantity' => 'stan',     // CSV kolumna 'stan' → stock_quantity
        'delivery_days' => 'dni_dostawy', // CSV kolumna 'dni_dostawy' → delivery_days
    ],
    'is_active' => true,
]);
```

### 3. Dodaj Scheduler (w Kernel.php)

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule): void
{
    // ... inne taski ...
    
    // Sync dostępności do PrestaShop 2x dziennie
    $schedule->command('supplier:sync-to-prestashop')
        ->twiceDaily(7, 15) // 7:00 i 15:00
        ->onOneServer()
        ->runInBackground();
}
```

## Jak To Działa?

### Automatyczny Przepływ (Codziennie)

```
┌─────────────────────────────────────────────────────────────┐
│ 1. IMPORT CSV (automatyczny przez IntegrationTask)         │
│    - Scheduler sprawdza task.fetch_mode='daily'            │
│    - Pobiera CSV z task.source_location                    │
│    - Parsuje według task.mappings                          │
│    - SupplierAvailabilityImportService zapisuje do JSONB   │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. SYNC DO PRESTASHOP (7:00 i 15:00)                       │
│    - Command znajduje integration PrestaShop               │
│    - SupplierAvailabilitySyncService pobiera produkty      │
│    - Dla każdego: updateProductAvailability() w API        │
│    - Ustawia: out_of_stock + available_later              │
└─────────────────────────────────────────────────────────────┘
```

### Ręczne Uruchomienie

```bash
# Ręczny import (jeśli nie chcesz czekać na scheduler)
php artisan integrations:execute-task {task_id}

# Ręczny sync do PrestaShop
php artisan supplier:sync-to-prestashop --prestashop=1
```

## Mapowanie do PrestaShop

| supplier_availability | PrestaShop | Wartość |
|----------------------|------------|---------|
| `is_available: true` | `out_of_stock` | `1` (allow backorder) |
| `is_available: false` | `out_of_stock` | `0` (deny orders) |
| `delivery_days: 3` | `available_later` | "Wysyłka za 3 dni" |
| `is_available: false` | `available_later` | "Produkt niedostępny" |

## Monitoring

### Query dostępności

```php
// Ile produktów ma info o dostępności
$total = IntegrationProductLink::whereNotNull('supplier_availability')->count();

// Ile jest dostępnych
$available = IntegrationProductLink::whereNotNull('supplier_availability')
    ->whereJsonPath('supplier_availability->is_available', true)
    ->count();

// Ostatnia aktualizacja
$lastChecked = IntegrationProductLink::whereNotNull('supplier_availability')
    ->max(DB::raw("(supplier_availability->>'last_checked_at')::timestamp"));
```

## Troubleshooting

### Import nie działa
```bash
# Sprawdź task
php artisan tinker
>>> IntegrationTask::where('resource_type', 'supplier-availability')->get()

# Sprawdź logi
tail -f storage/logs/laravel.log | grep supplier
```

### Sync nie aktualizuje PrestaShop
```bash
# Test połączenia PrestaShop
php artisan integrations:test-connection {integration_id}

# Sprawdź produkty z availability
php artisan tinker
>>> IntegrationProductLink::whereNotNull('supplier_availability')->count()
```

## Decyzje Biznesowe Do Podjęcia

1. **Częstotliwość**: Sync 2x dziennie (7:00, 15:00) wystarczy?
2. **Wiele dostawców**: Jeden produkt może mieć wielu dostawców?
3. **Priorytet**: Jeśli wielu dostawców - który ma priorytet?
4. **Historia**: Czy przechowywać historię zmian dostępności?
5. **Powiadomienia**: Email gdy produkt staje się niedostępny?

## Szacowany Czas Implementacji

- Migration + Model rozszerzenie: **1-2h**
- Import Service: **2-3h**
- Sync Service: **2-3h**
- Command + Scheduler: **1-2h**

**TOTAL: 6-10 godzin**

---

📚 Szczegóły: Zobacz `SUPPLIER_AVAILABILITY_PLAN.md`
