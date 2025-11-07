# 🚀 Quick Start: Dostępność u Dostawcy

## 📋 Szybki Przegląd

**Co to robi?**
- Import stanów magazynowych od dostawców (CSV)
- Automatyczna aktualizacja PrestaShop:
  - ✅ Dostępny u dostawcy → można zamawiać, "Wysyłka za 3 dni"
  - ❌ Niedostępny → NIE można zamawiać, "Produkt niedostępny"

**Kiedy się uruchamia?**
- Automatycznie codziennie o 6:00 (import) i 7:00 (sync do PrestaShop)
- Ręcznie przez panel admin lub komendy

## 🎯 Decyzje do Podjęcia PRZED Implementacją

### 1. Struktura Danych CSV od Dostawcy

Przykład - jaki format masz?

```csv
SKU;EAN;Nazwa;Stan;Termin
ABC123;5901234567890;Produkt 1;150;3
ABC124;5901234567891;Produkt 2;0;
ABC125;5901234567892;Produkt 3;25;5
```

**Pytania:**
- ✅ Jakie są nazwy kolumn w CSV?
- ✅ Jaki delimiter (`;` czy `,`)?
- ✅ Jak oznaczona jest dostępność (stan > 0, lub osobna kolumna TAK/NIE)?
- ✅ Czy jest informacja o terminie dostawy?

### 2. Logika Biznesowa

**Podstawowa logika (do potwierdzenia):**

| Stan u dostawcy | PrestaShop `out_of_stock` | PrestaShop `available_later` |
|-----------------|---------------------------|------------------------------|
| > 0 sztuk       | 1 (allow backorder)       | "Wysyłka za 3 dni"          |
| = 0 sztuk       | 0 (deny backorder)        | "Produkt niedostępny"       |

**Pytania:**
- ✅ Czy powyższa logika jest OK?
- ✅ Czy termin wysyłki ma być dynamiczny (z CSV) czy stały (3 dni)?
- ✅ Czy różne komunikaty dla różnych stanów? (np. "1-2 dni" vs "3-5 dni")

### 3. Wielu Dostawców

**Scenariusz:** Produkt X jest u 2 dostawców

**Opcja A:** Jeden produkt = jeden dostawca (PROSTSZE)
```
Product #123 → Dostawca GoDan → Stan: 50 szt
```

**Opcja B:** Jeden produkt = wielu dostawców (BARDZIEJ ZŁOŻONE)
```
Product #123 → Dostawca GoDan → Stan: 50 szt
              → Dostawca PartDeco → Stan: 0 szt
```

**Pytanie:**
- ✅ Która opcja? (Zalecam A dla prostoty)

### 4. Częstotliwość Synchronizacji

**Propozycja:**
- 🌅 6:00 - Import CSV od dostawców
- 🌅 7:00 - Sync do PrestaShop
- 🌆 14:00 - Dodatkowa synchronizacja popołudniu (opcjonalna)

**Pytanie:**
- ✅ Czy 2x dziennie wystarczy?
- ✅ Czy trzeba częściej (np. co 4 godz)?

## 🛠️ Implementacja - Krok po Kroku

### Krok 1: Przygotowanie (30 min)

```bash
# 1. Pobierz przykładowy CSV od dostawcy
# 2. Przeanalizuj format i kolumny
# 3. Przygotuj mapping kolumn
```

### Krok 2: Baza Danych (15 min)

```bash
# Utworzenie migracji
php artisan make:migration create_supplier_product_availability_table

# Uruchomienie migracji
php artisan migrate

# Sprawdzenie
php artisan tinker
>>> DB::table('supplier_product_availability')->count()
```

### Krok 3: Konfiguracja Integracji Dostawcy (10 min)

W panelu admin:
1. **Integracje** → **Dodaj Nową**
2. Typ: `Supplier CSV Availability`
3. Konfiguracja:
   ```json
   {
     "csv_url": "https://dostawca.pl/api/stock.csv",
     "delimiter": ";",
     "delivery_days_default": 3
   }
   ```
4. Mapping kolumn:
   - `sku` → nazwa kolumny SKU w CSV
   - `ean` → nazwa kolumny EAN w CSV
   - `stock_quantity` → nazwa kolumny ze stanem
   - `delivery_days` → (opcjonalnie)

### Krok 4: Testowy Import (15 min)

```bash
# Ręczny import
php artisan supplier:import-availability --integration=5

# Sprawdzenie wyników
php artisan tinker
>>> App\Models\SupplierProductAvailability::count()
>>> App\Models\SupplierProductAvailability::where('is_available', true)->count()
```

### Krok 5: Testowa Synchronizacja do PrestaShop (15 min)

```bash
# Synchronizacja z PrestaShop
php artisan supplier:sync-availability --prestashop=1 --supplier=5

# Sprawdzenie w PrestaShop
# Sprawdź kilka produktów czy ustawienia się zmieniły
```

### Krok 6: Automatyzacja (5 min)

```bash
# Sprawdź czy scheduler działa
php artisan schedule:list

# Testowe uruchomienie
php artisan schedule:run

# W produkcji cron już jest skonfigurowany:
# * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
```

## ✅ Checklist Implementacji

### Przed Startem
- [ ] Przykładowy CSV od dostawcy
- [ ] Decyzja: logika biznesowa
- [ ] Decyzja: jeden vs wielu dostawców
- [ ] Decyzja: częstotliwość synchronizacji
- [ ] Dostęp do PrestaShop API

### Implementacja Kodu
- [ ] Migracja bazy danych
- [ ] Model `SupplierProductAvailability`
- [ ] Import Service
- [ ] Sync Service  
- [ ] PrestaShop API update method
- [ ] Jobs (Import + Sync)
- [ ] Commands
- [ ] Scheduler config

### Testy
- [ ] Test importu CSV (ręcznie)
- [ ] Test synchronizacji do PrestaShop (ręcznie)
- [ ] Test na 5-10 produktach
- [ ] Weryfikacja w PrestaShop
- [ ] Test schedulera

### Produkcja
- [ ] Deploy kodu
- [ ] Migracja bazy danych
- [ ] Konfiguracja integracji w panelu
- [ ] Monitoring przez tydzień
- [ ] Dokumentacja dla użytkowników

## 🎓 Przykłady Użycia

### Import ręczny dla testów

```bash
# Import dostępności
php artisan supplier:import-availability \
  --integration=5 \
  --dry-run  # Symulacja bez zapisu

# Import z zapisem
php artisan supplier:import-availability --integration=5
```

### Synchronizacja ręczna

```bash
# Sync wszystkich produktów
php artisan supplier:sync-availability --prestashop=1

# Sync tylko produktów z jednego dostawcy
php artisan supplier:sync-availability \
  --prestashop=1 \
  --supplier=5
```

### Debug / Sprawdzenie stanu

```bash
php artisan tinker
>>> use App\Models\SupplierProductAvailability;
>>> 
>>> // Ile produktów tracked
>>> SupplierProductAvailability::count()
>>> 
>>> // Ile dostępnych
>>> SupplierProductAvailability::where('is_available', true)->count()
>>> 
>>> // Ostatni import
>>> SupplierProductAvailability::max('last_checked_at')
>>> 
>>> // Produkty które się zmieniły ostatnio
>>> SupplierProductAvailability::where('last_status_change_at', '>=', now()->subDay())->get()
```

## 🔍 Monitoring

### Sprawdzenie czy działa

```bash
# Logi importu
tail -f storage/logs/laravel.log | grep "Supplier availability"

# Status schedulera
php artisan schedule:list

# Failed jobs
php artisan queue:failed
```

### Metryki do monitorowania

1. **Liczba produktów tracked** - czy rośnie?
2. **Dostępność (%)** - jaki % produktów jest dostępny?
3. **Ostatni import** - czy działa codziennie?
4. **Błędy** - czy są failed jobs?

## 📞 Wsparcie

### Problemy i Rozwiązania

**Problem:** Import nie znajduje produktów
```bash
# Sprawdź SKU/EAN w bazie
SELECT id, sku, ean FROM products WHERE user_id = 1 LIMIT 10;

# Sprawdź mapping w CSV
head -5 plik.csv
```

**Problem:** Synchronizacja do PrestaShop nie działa
```bash
# Sprawdź logi
tail -100 storage/logs/laravel.log | grep PrestaShop

# Test połączenia
php artisan integrations:test 1
```

**Problem:** Scheduler nie działa
```bash
# Sprawdź czy cron jest skonfigurowany
crontab -l

# Ręczne uruchomienie
php artisan schedule:run
```

## 📚 Dokumentacja

- 📖 [Pełny Plan Implementacji](SUPPLIER_AVAILABILITY_PLAN.md)
- 📖 [Synchronizacja PrestaShop](INTEGRATION_MANUAL_SYNC.md)
- 📖 [Queue Commands](QUEUE_COMMANDS.md)

---

**Gotowy do implementacji?** Zacznij od decyzji w sekcji "Decyzje do Podjęcia"! 🚀
