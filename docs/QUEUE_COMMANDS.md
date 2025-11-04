# Dokumentacja Komend Queue i Jobów

## Przegląd Kolejek

System używa trzech głównych kolejek dla różnych typów zadań:

- **`import`** - Importy CSV/XML z plików lokalnych i zewnętrznych źródeł
- **`integrations`** - Synchronizacja z PrestaShop, powiązania produktów, stany magazynowe
- **`default`** - Pozostałe joby systemowe (powiadomienia, itp.)

## Komendy Queue (Kolejki Jobów)

### `queue:work` - Główny Worker do Przetwarzania Jobów

**Podstawowe użycie:**
```bash
# Worker dla importów
./vendor/bin/sail artisan queue:work --queue=import

# Worker dla integracji
./vendor/bin/sail artisan queue:work --queue=integrations

# Worker domyślny
./vendor/bin/sail artisan queue:work --queue=default
```

**Dodatkowe opcje:**
```bash
# Uruchom tylko jeden job i zakończ
./vendor/bin/sail artisan queue:work --queue=import --once

# Zatrzymaj gdy kolejka będzie pusta
./vendor/bin/sail artisan queue:work --queue=import --stop-when-empty

# Maksymalna liczba jobów przed restartowaniem
./vendor/bin/sail artisan queue:work --queue=import --max-jobs=100

# Maksymalny czas pracy (sekundy)
./vendor/bin/sail artisan queue:work --queue=import --max-time=3600

# Timeout dla pojedynczego joba (sekundy)
./vendor/bin/sail artisan queue:work --queue=import --timeout=300

# Limit pamięci (MB)
./vendor/bin/sail artisan queue:work --queue=import --memory=512
```

### `queue:failed` - Lista Nieudanych Jobów

```bash
# Lista wszystkich failed jobów
./vendor/bin/sail artisan queue:failed
```

**Przykładowy output:**
```
2025-11-03 18:17:47 8d260588-57df-4f55-b2f2-c47f6055a8ef database@integrations 
App\Jobs\LinkIntegrationProducts

2025-11-03 20:02:55 3f871b5c-14f3-40dd-82a3-61bcc0722ae8 database@default 
App\Jobs\ExecuteIntegrationTask
```

### `queue:retry` - Ponowne Uruchomienie Failed Jobów

```bash
# Uruchom ponownie wszystkie failed joby
./vendor/bin/sail artisan queue:retry all

# Uruchom ponownie konkretny job po ID
./vendor/bin/sail artisan queue:retry 8d260588-57df-4f55-b2f2-c47f6055a8ef

# Uruchom ponownie failed joby z konkretnej kolejki
./vendor/bin/sail artisan queue:retry --queue=integrations

# Uruchom ponownie zakres jobów (ID 1-5)
./vendor/bin/sail artisan queue:retry --range=1-5
```

### `queue:clear` - Czyszczenie Kolejki

```bash
# Wyczyść konkretną kolejkę
./vendor/bin/sail artisan queue:clear --queue=integrations

# Wyczyść wszystkie kolejki
./vendor/bin/sail artisan queue:clear
```

### `queue:flush` - Usunięcie Failed Jobów

```bash
# Usuń wszystkie failed joby
./vendor/bin/sail artisan queue:flush
```

### `queue:restart` - Restart Workerów

```bash
# Restartuj wszystkich workerów (po zmianie kodu)
./vendor/bin/sail artisan queue:restart
```

## Komendy Integracji

### `integrations:run-imports` - Automatyczne Uruchamianie Importów

```bash
# Sprawdź i uruchom zaplanowane importy
./vendor/bin/sail artisan integrations:run-imports
```

**Zastosowanie:**
- Uruchamiana automatycznie przez cron co 5 minut
- Sprawdza profile importów z `next_run_at <= now()`
- Dodaje joby `ExecuteIntegrationTask` do kolejki `import`

### `integrations:sync-inventory` - Synchronizacja Stanów Magazynowych

```bash
# Synchronizacja wszystkich aktywnych integracji
./vendor/bin/sail artisan integrations:sync-inventory

# Synchronizacja konkretnej integracji
./vendor/bin/sail artisan integrations:sync-inventory --integration=1

# Wymuszona synchronizacja (ignoruj interwały)
./vendor/bin/sail artisan integrations:sync-inventory --force

# Test synchronizacji (bez zmian)
./vendor/bin/sail artisan integrations:sync-inventory --dry-run

# Test konkretnej integracji
./vendor/bin/sail artisan integrations:sync-inventory --integration=1 --dry-run
```

**Zastosowanie:**
- Synchronizuje stany magazynowe z PrestaShop
- Uruchamiana automatycznie po zapostowaniu dokumentów magazynowych
- Może być uruchamiana ręcznie lub przez cron

### `integrations:sync-logs` - Przegląd Logów Synchronizacji

```bash
# Wyświetl logi synchronizacji
./vendor/bin/sail artisan integrations:sync-logs
```

## Komendy Magazynowe

### `warehouse:recalculate-stocks` - Przeliczanie Stanów Magazynowych

```bash
# Przelicz wszystkie stany magazynowe
./vendor/bin/sail artisan warehouse:recalculate-stocks

# Test przeliczenia (bez zapisywania zmian)
./vendor/bin/sail artisan warehouse:recalculate-stocks --dry-run
```

**Zastosowanie:**
- Przelicza stany na podstawie zapostowanych dokumentów
- Używane przy problemach z bilansem magazynowym
- Regeneruje tabelę `warehouse_stock_totals`

## Scenariusze Użycia

### 1. Uruchomienie Systemu Produkcyjnego

```bash
# Uruchom wszystkie workery w tle
./vendor/bin/sail artisan queue:work --queue=import --timeout=300 &
./vendor/bin/sail artisan queue:work --queue=integrations --timeout=300 &
./vendor/bin/sail artisan queue:work --queue=default --timeout=60 &

# Sprawdź czy działają
ps aux | grep "queue:work"
```

### 2. Diagnostyka Problemów

```bash
# 1. Sprawdź failed joby
./vendor/bin/sail artisan queue:failed

# 2. Sprawdź szczegóły błędu w bazie
./vendor/bin/sail artisan tinker
>>> DB::table('failed_jobs')->latest()->first()->exception;

# 3. Uruchom ponownie po naprawie
./vendor/bin/sail artisan queue:retry all
```

### 3. Ręczna Synchronizacja

```bash
# 1. Test synchronizacji
./vendor/bin/sail artisan integrations:sync-inventory --dry-run

# 2. Synchronizacja konkretnej integracji
./vendor/bin/sail artisan integrations:sync-inventory --integration=1 --force

# 3. Sprawdź logi
./vendor/bin/sail artisan integrations:sync-logs
```

### 4. Import CSV/XML

```bash
# 1. Sprawdź zaplanowane importy
./vendor/bin/sail artisan integrations:run-imports

# 2. Uruchom workera dla importów
./vendor/bin/sail artisan queue:work --queue=import --timeout=600

# 3. Monitoruj postęp
watch './vendor/bin/sail artisan queue:failed'
```

### 5. Obsługa Problemów z Timeout

```bash
# 1. Zwiększ timeout dla workerów
./vendor/bin/sail artisan queue:work --queue=integrations --timeout=600

# 2. Restart workerów po zmianie kodu
./vendor/bin/sail artisan queue:restart

# 3. Wyczyść zablokowane joby
./vendor/bin/sail artisan queue:clear --queue=integrations
```

## Monitorowanie

### Sprawdzanie Statusu Kolejek

```bash
# Liczba jobów w każdej kolejce
./vendor/bin/sail artisan tinker --execute="
echo 'Import: ' . DB::table('jobs')->where('queue', 'import')->count();
echo 'Integrations: ' . DB::table('jobs')->where('queue', 'integrations')->count();
echo 'Default: ' . DB::table('jobs')->where('queue', 'default')->count();
echo 'Failed: ' . DB::table('failed_jobs')->count();
"
```

### Sprawdzanie Workerów

```bash
# Lista uruchomionych workerów
ps aux | grep "queue:work"

# Zabicie wszystkich workerów
pkill -f "queue:work"
```

## Automatyzacja (Cron)

Dodaj do `crontab` lub `supervisor`:

```bash
# Importy co 5 minut
*/5 * * * * cd /path/to/project && ./vendor/bin/sail artisan integrations:run-imports

# Synchronizacja co godzinę
0 * * * * cd /path/to/project && ./vendor/bin/sail artisan integrations:sync-inventory

# Restart workerów o 3:00 w nocy
0 3 * * * cd /path/to/project && ./vendor/bin/sail artisan queue:restart
```

## Troubleshooting

### Częste Problemy

1. **Timeout jobów** - zwiększ `--timeout=600`
2. **Failed joby** - sprawdź `queue:failed` i `queue:retry`
3. **Zablokowane workery** - użyj `queue:restart`
4. **Problemy z pamięcią** - ustaw `--memory=512`
5. **API timeout** - sprawdź konfigurację integracji

### Logi

```bash
# Logi Laravel
./vendor/bin/sail logs -f

# Logi konkretnego workera
./vendor/bin/sail artisan queue:work --queue=import -vvv
```