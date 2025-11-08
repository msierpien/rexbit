# Supplier Availability Sync

This document łączy wcześniejsze opracowania (`SUPPLIER_AVAILABILITY_PLAN.md`, `SUPPLIER_AVAILABILITY_QUICKSTART*.md`) w jeden przewodnik opisujący architekturę, konfigurację i narzędzia CLI związane z modułem dostępności u dostawców.

## Architektura

1. **Źródło danych** &mdash; Integracje typu `CSV_XML_IMPORT` mogą mieć zadania (`IntegrationTask`) z `resource_type = supplier-availability`. Plik CSV/XML jest parsowany tym samym mechanizmem co import produktów (parser, scheduler, kolejka `import`).
2. **Mapowanie** &mdash; W konfiguracji zadania definiujemy mapy kolumn → pól logicznych (`sku`, `ean`, `stock_quantity`, `delivery_days`, `is_available`, `purchase_price`, `supplier_sku`, `supplier_code`). Mappings trafiają do `IntegrationTask::mappings` z `target_type = supplier_availability`.
3. **Processing** &mdash; `ExecuteIntegrationTask` rozpoznaje typ zasobu i dla dostępności tworzy joby `ProcessSupplierAvailabilityChunk`, które korzystają z `SupplierAvailabilityImportService`.
4. **Storage** &mdash; Dane zapisujemy w `integration_product_links.supplier_availability` (JSON/JSONB). Model `IntegrationProductLink` posiada helpery (`isAvailableAtSupplier()`, `getPrestashopOutOfStockValue()` itd.) oraz metodę `updateSupplierAvailability`.
5. **Synchronizacja z Prestashop** &mdash; `SupplierAvailabilitySyncService` iteruje po linkach z `external_product_id` i aktualizuje `stock_availables` w Prestashop przez `PrestashopProductService::updateProductAvailability`.

Schemat przepływu:

```
CSV/XML dostawcy
   ↓ (IntegrationTask + parser)
ProcessSupplierAvailabilityChunk
   ↓
integration_product_links.supplier_availability
   ↓
supplier:sync-availability (CLI) → Prestashop API (allow_oosp + available_later)
```

## Konfiguracja zadania importu

1. Utwórz integrację `CSV/XML Import` (driver `CsvXmlImportIntegrationDriver`).
2. Dodaj zadanie:
   - `resource_type`: `supplier-availability`
   - Źródło: URL / plik / S3 (tak jak przy imporcie produktów)
   - Mapowania (frontend zapisuje je w `mappings.supplier_availability.*`)
   - Opcje w `task.options.supplier_availability`:
     - `contractor_id` – ID dostawcy z tabeli `contractors`
     - `default_delivery_days`
     - `sync_purchase_price` (bool)
3. Uruchom ręcznie (`POST /integrations/{integration}/tasks/{task}/run`) lub poczekaj na scheduler (`integrations:run-imports`).

## Struktura danych w `supplier_availability`

```json
{
  "is_available": true,
  "stock_quantity": 42,
  "delivery_days": 3,
  "supplier_sku": "XYZ-123",
  "supplier_code": "ACME",
  "purchase_price": 12.34,
  "available_later": "Wysyłka za 3 dni",
  "contractor_id": 5,
  "last_checked_at": "2025-11-06T12:00:00Z",
  "last_status_change_at": "2025-11-05T07:24:12Z"
}
```

Helpery w modelu automatycznie synchronizują `last_checked_at` / `last_status_change_at` i wyliczają teksty dla Prestashop.

## CLI

### Import (istniejące komendy)
- `integrations:run-imports` – scheduler integracji CSV/XML.
- `queue:work --queue=import` – przetwarza chunk’i importowe (zarówno produkty jak i dostępność).

### Synchronizacja Prestashop

```
php artisan supplier:sync-availability --prestashop=1 [--contractor=5] [--limit=500]
```

Parametry:
- `--prestashop` – wymagane ID integracji Prestashop.
- `--contractor` – opcjonalny filtr po dostawcy (ID z `contractors`).
- `--limit` – maksymalna liczba pozycji do zsynchronizowania podczas jednego uruchomienia.

Komenda korzysta z `SupplierAvailabilitySyncService`, który bazuje na helperach `IntegrationProductLink`.

## Biblioteki i komponenty systemowe

- **Parsery importu** – `ImportParserFactory` (CSV, XML, JSON).
- **Źródła plików** – `ImportSourceResolver` (URL, storage, upload).
- **Kolejki** – wszystkie joby importowe trafiają na kolejkę `import`.
- **Prestashop API** – `PrestashopProductService` (HTTP client na `Illuminate\Support\Facades\Http`).
- **Logika dostawców** – nowe serwisy:
  - `SupplierAvailabilityImportService`
  - `SupplierAvailabilitySyncService`
- **Modele wspierające** – `IntegrationTask`, `IntegrationTaskRun`, `IntegrationProductLink`, `Contractor`.

## Dalsze kroki / kontrola jakości

- Dodaj testy feature/unit dla `SupplierAvailabilityImportService` i `SupplierAvailabilitySyncService`.
- W panelu integracji przygotuj UI dla mapowania pól i ustawień (`options.supplier_availability`).
- Jeśli potrzebujesz dodatkowych scenariuszy (np. wielu dostawców w jednym pliku), możesz rozszerzyć mapowanie o pole `contractor_id` i przekazywać je do serwisu importu.
