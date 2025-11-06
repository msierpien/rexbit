# Czyszczenie i edycja dokumentów magazynowych

## Czyszczenie dokumentów magazynowych użytkownika

### Command: `warehouse:clean-documents`

Umożliwia całkowite wyczyszczenie wszystkich dokumentów magazynowych użytkownika wraz ze stanami magazynowymi.

#### Użycie

```bash
# Podgląd co zostanie usunięte (dry-run)
php artisan warehouse:clean-documents 1 --dry-run

# Usunięcie z potwierdzeniem
php artisan warehouse:clean-documents 1

# Usunięcie bez pytania o potwierdzenie
php artisan warehouse:clean-documents 1 --force
```

#### Parametry

- `userId` - ID użytkownika, którego dokumenty mają być usunięte
- `--dry-run` - Tryb podglądu bez faktycznego usuwania
- `--force` - Wymusza usunięcie bez pytania o potwierdzenie

#### Co zostaje usunięte?

1. Wszystkie dokumenty magazynowe użytkownika (wraz z soft-deleted)
2. Wszystkie pozycje dokumentów
3. Wszystkie stany magazynowe (warehouse_stock_totals)

#### Statystyki

Command wyświetla szczegółowe statystyki przed usunięciem:
- Liczba dokumentów według statusu (draft, posted, cancelled, archived)
- Liczba soft-deleted dokumentów
- Liczba pozycji dokumentów
- Liczba stanów magazynowych
- Suma wszystkich stanów magazynowych

#### Bezpieczeństwo

- Operacja jest wykonywana w transakcji - w przypadku błędu wszystko zostanie wycofane
- Użytkownik musi istnieć w bazie danych
- Wymaga potwierdzenia przed usunięciem (chyba że użyto `--force`)

#### Przykład użycia

```bash
# 1. Sprawdź co zostanie usunięte
docker compose exec laravel.test php artisan warehouse:clean-documents 1 --dry-run

# 2. Usuń dokumenty
docker compose exec laravel.test php artisan warehouse:clean-documents 1
```

---

## Edycja zatwierdzonych dokumentów (Admin)

### Funkcjonalność

System umożliwia edycję zatwierdzonych (posted) dokumentów magazynowych z automatycznym przeliczeniem stanów magazynowych.

**UWAGA**: Ta funkcjonalność jest dostępna tylko dla administratorów!

### Endpoint: `POST /warehouse/documents/{id}/edit-posted`

Pozwala na edycję pozycji zatwierdzonego dokumentu z automatycznym:
1. Wycofaniem starych ruchów magazynowych
2. Aktualizacją pozycji dokumentu
3. Zastosowaniem nowych ruchów magazynowych

#### Parametry żądania

```json
{
  "items": [
    {
      "product_id": 1,
      "quantity": 10.5,
      "unit_price": 99.99,
      "vat_rate": 23
    }
  ]
}
```

#### Walidacja

- `items` - wymagane, min. 1 pozycja
- `items.*.product_id` - wymagane, musi istnieć w products
- `items.*.quantity` - wymagane, min. 0.001
- `items.*.unit_price` - opcjonalne, min. 0
- `items.*.vat_rate` - opcjonalne, 0-100

#### Odpowiedź

Przekierowanie do widoku szczegółów dokumentu (`warehouse.documents.show`) z komunikatem sukcesu/błędu.

### Endpoint: `POST /warehouse/documents/{id}/preview-edit`

Podgląd zmian w stanach magazynowych przed zapisaniem edycji.

#### Odpowiedź JSON

```json
{
  "success": true,
  "changes": [
    {
      "product_id": 1,
      "old_quantity": 10.0,
      "new_quantity": 15.0,
      "old_stock_change": 10.0,
      "new_stock_change": 15.0,
      "net_stock_change": 5.0,
      "current_stock": 50.0,
      "new_stock": 55.0
    }
  ]
}
```

### Jak to działa?

#### 1. Wycofanie starych ruchów

```php
// Dla każdej starej pozycji dokumentu
foreach ($oldItems as $item) {
    $quantityChange = getQuantityChangeForType($documentType, $quantity);
    $stock->on_hand -= $quantityChange; // Odwrócenie ruchu
}
```

#### 2. Aktualizacja pozycji

```php
// Usunięcie starych pozycji
$document->items()->delete();

// Dodanie nowych pozycji
foreach ($newItems as $item) {
    WarehouseDocumentItem::create([...]);
}
```

#### 3. Zastosowanie nowych ruchów

```php
// Dla każdej nowej pozycji dokumentu
foreach ($newItems as $item) {
    $quantityChange = getQuantityChangeForType($documentType, $quantity);
    $stock->on_hand += $quantityChange; // Nowy ruch
}
```

### Typy dokumentów

- **PZ** (Przyjęcie zewnętrzne) - **+** (zwiększa stan)
- **IN** (Przyjęcie wewnętrzne) - **+** (zwiększa stan)
- **WZ** (Wydanie zewnętrzne) - **-** (zmniejsza stan)
- **OUT** (Wydanie wewnętrzne) - **-** (zmniejsza stan)

### Audit Trail

Każda edycja zatwierdzonego dokumentu jest logowana w audit trail z następującymi informacjami:
- Kto edytował (user_id, user_name)
- Kiedy (timestamp)
- Liczba starych i nowych pozycji
- Typ dokumentu i numer
- ID magazynu

### Przykład użycia w kontrolerze

```php
// Sprawdź czy użytkownik jest adminem
if (!$request->user()->isAdmin()) {
    return redirect()->back()->with('error', 'Tylko administrator może edytować zatwierdzone dokumenty.');
}

// Edytuj dokument
$this->editService->editPostedDocument(
    $warehouse_document,
    $validated['items'],
    $request->user()
);
```

### Bezpieczeństwo

1. **Autoryzacja** - Tylko administratorzy mogą edytować zatwierdzone dokumenty
2. **Walidacja użytkownika** - Można edytować tylko swoje dokumenty
3. **Walidacja statusu** - Tylko dokumenty w statusie "posted" mogą być edytowane
4. **Transakcje** - Wszystko wykonywane w transakcji DB
5. **Logging** - Pełne logowanie operacji w logach i audit trail

### Routing

```php
// routes/web.php
Route::post('/warehouse/documents/{warehouse_document}/edit-posted', 
    [WarehouseDocumentController::class, 'editPosted'])
    ->name('warehouse.documents.edit-posted');

Route::post('/warehouse/documents/{warehouse_document}/preview-edit', 
    [WarehouseDocumentController::class, 'previewPostedEdit'])
    ->name('warehouse.documents.preview-edit');
```

### Testowanie

```bash
# Sprawdź dokumenty użytkownika
docker compose exec laravel.test php artisan tinker --execute="
  \$doc = App\Models\WarehouseDocument::find(2);
  echo 'Document: ' . \$doc->number . PHP_EOL;
  echo 'Status: ' . \$doc->status->value . PHP_EOL;
  echo 'Items: ' . \$doc->items->count() . PHP_EOL;
"

# Podgląd zmian (przez API)
curl -X POST http://localhost/warehouse/documents/2/preview-edit \
  -H "Content-Type: application/json" \
  -d '{"items":[{"product_id":1,"quantity":20}]}'
```

---

## Przykłady użycia

### 1. Czyszczenie dokumentów użytkownika ID 1

```bash
# Podgląd
docker compose exec laravel.test php artisan warehouse:clean-documents 1 --dry-run

# Wyjście:
# Analizuję dokumenty użytkownika: Jan Kowalski (ID: 1)
# 
# ┌─────────────────────────┬─────────┐
# │ Metryka                 │ Wartość │
# ├─────────────────────────┼─────────┤
# │ Dokumenty ogółem        │ 24      │
# │ Robocze (draft)         │ 0       │
# │ Zatwierdzone (posted)   │ 22      │
# │ Anulowane (cancelled)   │ 1       │
# │ Zarchiwizowane          │ 1       │
# │ Usunięte (soft deleted) │ 0       │
# │ Pozycje ogółem          │ 24      │
# └─────────────────────────┴─────────┘
#
# Znalezione stany magazynowe: 5 pozycji
# Suma stanów magazynowych: 150

# Faktyczne usunięcie
docker compose exec laravel.test php artisan warehouse:clean-documents 1
```

### 2. Edycja zatwierdzonego dokumentu (jako admin)

**Frontend przykład (JavaScript/React):**

```javascript
// Podgląd zmian
const response = await fetch(`/warehouse/documents/${documentId}/preview-edit`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    items: [
      { product_id: 1, quantity: 20, unit_price: 99.99, vat_rate: 23 }
    ]
  })
});

const { changes } = await response.json();

// Wyświetl zmiany użytkownikowi
changes.forEach(change => {
  console.log(`Produkt ${change.product_id}:`);
  console.log(`  Stara ilość: ${change.old_quantity}`);
  console.log(`  Nowa ilość: ${change.new_quantity}`);
  console.log(`  Zmiana stanu: ${change.net_stock_change}`);
  console.log(`  Stan po zmianie: ${change.new_stock}`);
});

// Zapisz zmiany
await fetch(`/warehouse/documents/${documentId}/edit-posted`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    items: [
      { product_id: 1, quantity: 20, unit_price: 99.99, vat_rate: 23 }
    ]
  })
});
```

#### Aktualny widok w aplikacji

- Dla administratorów, którzy otworzą widok `Magazyn -> Dokumenty -> Szczegóły`, renderowana jest dodatkowa karta "Edycja zatwierdzonego dokumentu".
- Formularz korzysta z komponentu `DocumentItems`, więc UX jest identyczny jak w zwykłej edycji roboczych dokumentów (wyszukiwarka produktów, skaner kodów, podsumowania wartości).
- Przycisk „Podgląd zmian” wysyła bieżące pozycje pod `/warehouse/documents/{id}/preview-edit` i prezentuje tabelę z przewidywanym wpływem na stany magazynowe (kolumny: poprzednia ilość, nowa ilość, zmiana netto, stan przed/po).
- Przycisk „Zapisz zmiany” korzysta z endpointu `/warehouse/documents/{id}/edit-posted`, a po sukcesie pozostajemy na widoku szczegółów dokumentu z komunikatem flash.
- Panel jest automatycznie resetowany po przeładowaniu danych (np. po udanym zapisie), więc zawsze pracujemy na aktualnych pozycjach dokumentu.

---

## Uwagi implementacyjne

### Tabela `warehouse_stock_totals`

```sql
CREATE TABLE warehouse_stock_totals (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    product_id BIGINT NOT NULL,
    warehouse_location_id BIGINT,
    on_hand DECIMAL(12,3) DEFAULT 0,
    reserved DECIMAL(12,3) DEFAULT 0,
    incoming DECIMAL(12,3) DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(user_id, product_id, warehouse_location_id)
);
```

### Model `WarehouseStockTotal`

```php
class WarehouseStockTotal extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'warehouse_location_id',
        'on_hand',
        'reserved',
        'incoming',
    ];

    protected $casts = [
        'on_hand' => 'decimal:3',
        'reserved' => 'decimal:3',
        'incoming' => 'decimal:3',
    ];
}
```

---

## FAQ

**Q: Co się stanie jeśli edytuję dokument PZ z 10 szt. na 5 szt.?**

A: Stan magazynowy zostanie zmniejszony o 5 szt. (10 zostanie wycofane, 5 dodane, net = -5).

**Q: Czy mogę edytować dokumenty innych użytkowników?**

A: Nie, nawet administrator może edytować tylko swoje dokumenty.

**Q: Co się stanie jeśli podczas edycji wystąpi błąd?**

A: Wszystko zostanie wycofane (rollback transakcji), stany magazynowe pozostaną niezmienione.

**Q: Czy edycja zatwierdzonego dokumentu jest logowana?**

A: Tak, w audit trail oraz w logach aplikacji.

**Q: Jak zmienić użytkownika na admina?**

A: W tinker: `User::find(1)->update(['role' => 'admin']);`
