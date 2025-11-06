# 📦 Przewodnik Synchronizacji PrestaShop

## 🎯 Kiedy używać pełnej synchronizacji?

### ✅ Użyj pełnej synchronizacji gdy:
- Wykonujesz inwentaryzację i chcesz ustawić rzeczywiste stany
- Migracja/import dużej ilości produktów
- Po dłuższej przerwie w działaniu systemu
- Wykryto rozbieżności między stanami lokalnymi a PrestaShop

### ❌ NIE używaj pełnej synchronizacji dla:
- Pojedynczych dokumentów magazynowych (działa automatycznie)
- Małych zmian stanów (< 100 produktów)
- Codziennych operacji

## 🚀 Jak uruchomić pełną synchronizację?

### Krok 1: Przygotowanie

Sprawdź ile produktów będzie synchronizowanych:

```bash
docker compose exec laravel.test php artisan tinker --execute="
\$integration = App\Models\Integration::where('type', 'prestashop')->first();
\$total = \$integration->productLinks()->whereNotNull('external_product_id')->count();
echo 'Produktów do synchronizacji: ' . \$total . PHP_EOL;
echo 'Szacowany czas: ' . round(\$total * 0.6 / 60) . ' minut' . PHP_EOL;
echo 'Liczba jobów: ' . ceil(\$total / 500) . PHP_EOL;
"
```

### Krok 2: Uruchom Queue Workers

**WAŻNE:** Potrzebujesz działających workerów!

#### Opcja A: Ręcznie (rozwój/testowanie)

```bash
# W jednym terminalu
docker compose exec laravel.test php artisan queue:work --queue=integrations --timeout=3600

# Możesz uruchomić więcej workerów w osobnych terminalach dla szybszej synchronizacji
```

#### Opcja B: W tle (produkcja)

```bash
# Używając supervisord (zalecane)
# Zobacz: docs/DOCKER_QUEUE_WORKERS.md
```

### Krok 3: Uruchom Synchronizację

#### Przez Panel Admin:

1. Przejdź do **Integracje** → Twoja integracja PrestaShop
2. Kliknij przycisk **"Synchronizuj stany"**
3. System wyświetli komunikat: "Synchronizacja została uruchomiona w tle"

#### Przez Artisan:

```bash
docker compose exec laravel.test php artisan integrations:sync-inventory
```

### Krok 4: Monitorowanie

```bash
# Sprawdź status jobów
docker compose exec laravel.test php artisan queue:work --queue=integrations --stop-when-empty

# Sprawdź logi
docker compose exec laravel.test tail -f storage/logs/laravel.log | grep "Integration inventory sync"

# Sprawdź nieudane joby
docker compose exec laravel.test php artisan queue:failed
```

## 📊 Przykładowe Czasy Synchronizacji

| Liczba produktów | Liczba jobów | Czas (1 worker) | Czas (4 workers) |
|------------------|--------------|-----------------|------------------|
| 100              | 1            | ~1 min          | ~1 min           |
| 500              | 1            | ~5 min          | ~5 min           |
| 1,000            | 2            | ~10 min         | ~5 min           |
| 5,000            | 10           | ~50 min         | ~13 min          |
| 17,800           | 36           | ~3 godz.        | ~45 min          |

## 🔧 Rozwiązywanie Problemów

### Problem: Worker zatrzymuje się po 60 sekundach

**Rozwiązanie:**
```bash
# Uruchom z dłuższym timeout
php artisan queue:work --queue=integrations --timeout=3600
```

### Problem: Zbyt wolna synchronizacja

**Rozwiązania:**

1. **Uruchom więcej workerów** (4-8 równolegle):
```bash
# Terminal 1
docker compose exec laravel.test php artisan queue:work --queue=integrations --timeout=3600

# Terminal 2
docker compose exec laravel.test php artisan queue:work --queue=integrations --timeout=3600

# Terminal 3, 4, itd...
```

2. **Użyj supervisord** (automatyczne zarządzanie):
```bash
# Zobacz docs/DOCKER_QUEUE_WORKERS.md
```

### Problem: Failed jobs

**Sprawdzenie:**
```bash
php artisan queue:failed
```

**Ponowne uruchomienie:**
```bash
# Konkretny job
php artisan queue:retry <job-id>

# Wszystkie failed jobs
php artisan queue:retry all
```

**Czyszczenie:**
```bash
php artisan queue:flush
```

### Problem: Błędy PrestaShop API (429 Too Many Requests)

**Przyczyna:** PrestaShop rate limiting

**Rozwiązanie:**
- System automatycznie retry (3 próby z 60s delay)
- Zmniejsz liczbę workerów (2-3 zamiast 8)
- Sprawdź konfigurację rate limiting w PrestaShop

## 💡 Best Practices

### 1. Automatyczna Synchronizacja (Zalecane)

Dla codziennych operacji **NIE używaj** przycisku "Synchronizuj wszystko". System automatycznie synchronizuje zmiany:

- ✅ Po zatwierdzeniu dokumentu PZ/WZ/IN/OUT
- ✅ Po zatwierdzeniu inwentaryzacji
- ✅ Po anulowaniu dokumentu

### 2. Pełna Synchronizacja (Rzadko)

Używaj tylko gdy:
- Robiłeś zmiany bezpośrednio w PrestaShop
- Po importie masowym produktów
- Po długiej przerwie w działaniu integracji

### 3. Planowana Synchronizacja

W `config/integrations.php` możesz ustawić automatyczną synchronizację:

```php
'prestashop' => [
    'inventory_sync' => [
        'mode' => 'local_to_presta', // lub 'disabled', 'prestashop_to_local'
        'interval_minutes' => 5, // Co ile minut (domyślnie)
    ],
],
```

### 4. Monitoring

Regularnie sprawdzaj:

```bash
# Liczba produktów w kolejce
docker compose exec laravel.test php artisan queue:status

# Failed jobs
docker compose exec laravel.test php artisan queue:failed

# Logi synchronizacji
docker compose exec laravel.test tail -100 storage/logs/laravel.log | grep "sync"
```

## 📈 Optymalizacja dla Dużych Sklepów (> 10,000 produktów)

### 1. Zwiększ liczbę workerów

```bash
# supervisord.conf
[program:laravel-worker-integrations]
process_name=%(program_name)s_%(process_num)02d
command=php artisan queue:work --queue=integrations --timeout=3600
numprocs=8  # 8 równoległych workerów
```

### 2. Użyj dedykowanego serwera kolejek (Redis)

```env
QUEUE_CONNECTION=redis
```

### 3. Podziel na mniejsze chunki (opcjonalnie)

W `IntegrationInventorySyncService::dispatchChunkedSync()` zmień chunk size:

```php
$chunks = array_chunk($allProductIds, 250); // Zamiast 500
```

### 4. Planuj pełne synchronizacje poza godzinami szczytu

```bash
# W cronie (2:00 w nocy)
0 2 * * * cd /path/to/app && php artisan integrations:sync-inventory
```

## 🎓 Dodatkowe Zasoby

- [Dokumentacja Synchronizacji](INTEGRATION_MANUAL_SYNC.md)
- [Queue Workers w Docker](DOCKER_QUEUE_WORKERS.md)
- [Komendy Queue](QUEUE_COMMANDS.md)
- [Inwentaryzacja](INVENTORY_SYNC.md)

---

**Ostatnia aktualizacja:** 6 listopada 2025
