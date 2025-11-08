# Integracja PrestaShop Database - Bezpośrednie połączenie z bazą danych

## Przegląd

Integracja **PrestaShop Database** umożliwia **100x szybszą synchronizację** dostępności produktów w porównaniu z API PrestaShop poprzez bezpośrednie połączenie z bazą danych MySQL.

### Porównanie wydajności

| Metoda | Produktów/sek | Czas dla 17,000 produktów |
|--------|---------------|---------------------------|
| PrestaShop API | ~5 | ~50 minut |
| PrestaShop Database | ~500-1000 | ~30 sekund |

## Bezpieczeństwo

Wszystkie dane wrażliwe są **bezpiecznie szyfrowane**:

- **Hasło do bazy danych** (`db_password`) - szyfrowane za pomocą Laravel `Crypt::encryptString()`
- Hasło jest przechowywane w kolumnie `config` (typ `jsonb`) tabeli `integrations`
- Deszyfrowanie odbywa się tylko w czasie wykonywania przez `IntegrationService::runtimeConfig()`

### Proces szyfrowania

1. **Podczas zapisu** (panel admina):
   - `IntegrationService::prepareConfigForStorage()` szyfruje `db_password`
   - Zaszyfrowana wartość zapisywana w bazie: `eyJpdiI6Ik5...` (base64)

2. **Podczas użycia** (synchronizacja):
   - `IntegrationService::runtimeConfig()` odszyfrowuje hasło
   - `PrestashopDatabaseSyncService` otrzymuje odszyfrowaną konfigurację
   - Połączenie MySQL używa odszyfrowanego hasła

## Konfiguracja w panelu admina

### Pola konfiguracyjne

| Pole | Wymagane | Domyślne | Opis |
|------|----------|----------|------|
| `db_host` | ✅ | `localhost` | Adres serwera MySQL (np. `127.0.0.1`, `mysql.example.com`) |
| `db_port` | ✅ | `3306` | Port MySQL |
| `db_name` | ✅ | - | Nazwa bazy danych PrestaShop |
| `db_username` | ✅ | - | Użytkownik MySQL z uprawnieniami do odczytu/zapisu |
| `db_password` | ✅* | - | Hasło MySQL (szyfrowane, *wymagane tylko przy tworzeniu) |
| `db_prefix` | ✅ | `ps_` | Prefiks tabel PrestaShop |
| `id_shop` | ✅ | `1` | ID sklepu (multi-shop) |
| `id_lang` | ✅ | `1` | ID języka |

\* *Przy edycji integracji pole `db_password` może być puste - zachowuje wtedy poprzednią wartość*

### Uprawnienia MySQL

Użytkownik MySQL musi mieć uprawnienia do:

```sql
-- Minimalne wymagane uprawnienia
GRANT SELECT, UPDATE ON prestashop_db.ps_stock_available TO 'prestashop_user'@'%';
GRANT SELECT, UPDATE ON prestashop_db.ps_product_lang TO 'prestashop_user'@'%';
GRANT SELECT ON prestashop_db.ps_configuration TO 'prestashop_user'@'%';
```

### Test połączenia

Po zapisaniu konfiguracji, kliknij **"Testuj połączenie"** aby sprawdzić:
- Połączenie z bazą MySQL
- Dostęp do tabeli `ps_configuration`
- Prawidłowy prefiks tabel

## Synchronizacja dostępności

### Używane tabele

1. **`ps_stock_available`** - stan dostępności produktu
   - `out_of_stock` = `1` → dozwolone zamówienia (≥ min threshold)
   - `out_of_stock` = `0` → niedozwolone zamówienia (< min threshold)

2. **`ps_product_lang`** - teksty dostępności
   - `available_now` → tekst gdy produkt dostępny
   - `available_later` → tekst gdy produkt niedostępny

### Konfiguracja synchronizacji

W panelu integracji, sekcja **"Synchronizacja dostępności dostawcy"**:

| Ustawienie | Opis |
|------------|------|
| **Min. ilość u dostawcy** | Próg dostępności (np. 20 szt) |
| **Synchronizuj tylko zmienione** | Pomija produkty bez zmiany stanu (optymalizacja) |
| **Tekst dostępny** | Wyświetlany gdy ≥ threshold (np. "Dostępne") |
| **Tekst niedostępny** | Wyświetlany gdy < threshold (np. "Tymczasowo niedostępne") |
| **Szablon czasu dostawy** | Wzór z `{days}` (np. "Dostawa w {days} dni") |

### Przykład konfiguracji

```json
{
  "supplier_sync": {
    "min_stock_threshold": 20,
    "sync_only_changed": true,
    "available_text": "Dostępne u dostawcy",
    "unavailable_text": "Produkt niedostępny",
    "delivery_text_template": "Wysyłka za {days} dni"
  }
}
```

## Użycie CLI

### Ręczna synchronizacja

```bash
php artisan supplier:sync-availability --prestashop=5 --limit=1000
```

### Parametry

- `--prestashop=ID` - ID integracji (wymagane)
- `--limit=N` - Maksymalna liczba produktów do synchronizacji
- `--contractor=ID` - Opcjonalnie: ID konkretnego dostawcy

### Przykładowe wyniki

```
Start synchronizacji dostępności (integration_id=5, type=prestashop-db, contractor=—, limit=1000)
Zakończono synchronizację. Sukcesy: 850, pominięto: 150, błędy: 0, łącznie: 1000
```

## Automatyczna synchronizacja

### Harmonogram (Scheduler)

W `app/Console/Kernel.php`:

```php
// Synchronizacja co 30 minut
$schedule->command('supplier:sync-availability --prestashop=5')
    ->everyThirtyMinutes()
    ->withoutOverlapping();
```

### Kolejka (Queue)

Możesz utworzyć Job dla asynchronicznej synchronizacji:

```php
dispatch(new SyncSupplierAvailabilityJob($integration));
```

## Rozwiązywanie problemów

### Błąd połączenia

```
Nie można połączyć się z bazą danych PrestaShop: SQLSTATE[HY000] [2002] Connection refused
```

**Rozwiązanie:**
- Sprawdź `db_host` i `db_port`
- Upewnij się, że MySQL jest dostępny zdalnie (bind-address w `my.cnf`)
- Sprawdź firewall

### Błąd uprawnień

```
SQLSTATE[42000]: Access denied for user 'prestashop_user'@'%' to database 'prestashop_db'
```

**Rozwiązanie:**
- Nadaj uprawnienia: `GRANT SELECT, UPDATE ON prestashop_db.* TO 'prestashop_user'@'%';`
- Odśwież uprawnienia: `FLUSH PRIVILEGES;`

### Błąd prefiksu tabel

```
SQLSTATE[42S02]: Base table or view not found: 1146 Table 'prestashop_db.ps_stock_available' doesn't exist
```

**Rozwiązanie:**
- Sprawdź poprawny prefiks w tabeli `ps_configuration` lub w pliku `parameters.php`
- Zaktualizuj pole `db_prefix` w konfiguracji integracji

## Architektura techniczna

### Klasy

1. **`IntegrationType::PRESTASHOP_DB`** - enum case
2. **`PrestashopDatabaseIntegrationDriver`** - definicja pól konfiguracyjnych
3. **`PrestashopDatabaseSyncService`** - logika synchronizacji przez SQL
4. **`IntegrationService`** - szyfrowanie/deszyfrowanie konfiguracji
5. **`SyncSupplierAvailabilityCommand`** - CLI komenda

### Przepływ danych

```
Panel Admina (Edit.jsx)
    ↓ POST /integrations/{id}
IntegrationController::update()
    ↓ validates & encrypts
IntegrationService::prepareConfigForStorage()
    ↓ Crypt::encryptString(db_password)
Database (integrations.config jsonb)

---

Synchronizacja CLI/Scheduler
    ↓ php artisan supplier:sync-availability
SyncSupplierAvailabilityCommand::handle()
    ↓ if type === PRESTASHOP_DB
syncUsingDatabase()
    ↓ new PrestashopDatabaseSyncService($task, $integrationService)
IntegrationService::runtimeConfig()
    ↓ Crypt::decryptString(config['db_password'])
PrestashopDatabaseSyncService::syncToPrestashop()
    ↓ new PDO(mysql:...)
Direct SQL UPDATE on ps_stock_available & ps_product_lang
```

## Migracja z API do Database

### Krok 1: Utwórz nową integrację Database

Panel → Integracje → Dodaj integrację → **PrestaShop Database**

### Krok 2: Wypełnij dane MySQL

Skopiuj dane z pliku `app/config/parameters.php` PrestaShop:
- `database_host` → `db_host`
- `database_port` → `db_port`
- `database_name` → `db_name`
- `database_user` → `db_username`
- `database_password` → `db_password`
- `database_prefix` → `db_prefix`

### Krok 3: Testuj połączenie

Kliknij **"Testuj połączenie"** aby zweryfikować konfigurację.

### Krok 4: Zaktualizuj harmonogram

W `app/Console/Kernel.php` zmień `--prestashop=3` (stara API) na `--prestashop=5` (nowa DB):

```php
// Było:
$schedule->command('supplier:sync-availability --prestashop=3')
    ->everyThirtyMinutes();

// Jest:
$schedule->command('supplier:sync-availability --prestashop=5')
    ->everyThirtyMinutes();
```

### Krok 5: Test synchronizacji

```bash
php artisan supplier:sync-availability --prestashop=5 --limit=100
```

Powinieneś zobaczyć **znacząco szybszy** czas wykonania.

## FAQ

**Q: Czy mogę używać obu integracji jednocześnie (API + Database)?**  
A: Tak, ale synchronizuj tylko przez jedną - wybierz szybszą (Database).

**Q: Czy hasło jest widoczne w logach?**  
A: Nie. Hasło jest szyfrowane i nie pojawia się w logach. W logach widać tylko `[encrypted]`.

**Q: Co się stanie jeśli zmienię klucz aplikacji (`APP_KEY`)?**  
A: Utracisz dostęp do zaszyfrowanych haseł. Musisz ponownie wprowadzić hasła w panelu admina.

**Q: Czy integracja działa z PrestaShop w wersji 1.6?**  
A: Tak, struktura tabel `ps_stock_available` i `ps_product_lang` jest kompatybilna.

**Q: Czy mogę używać z multi-shop?**  
A: Tak, ustaw właściwy `id_shop` w konfiguracji. Każdy sklep wymaga osobnej integracji.
