# System integralności dokumentów magazynowych

## Opis problemu
Dotychczas dokumenty magazynowe mogły być usuwane bez kontroli, co mogło prowadzić do sytuacji gdzie nowsze dokumenty odwołują się do usuniętych dokumentów starszych, co niszczyło integralność danych magazynowych.

## Rozwiązanie
Wprowadzono system kontroli integralności dokumentów, który:

1. **Uniemożliwia usuwanie dokumentów z wcześniejszymi ID** jeśli istnieją nowsze zatwierdzone dokumenty w tym samym magazynie
2. **Wprowadza soft delete** - dokumenty są oznaczane jako usunięte, ale pozostają w bazie danych
3. **Śledzi kto usunął dokument** poprzez kolumnę `deleted_by`
4. **Blokuje edycję zatwierdzonych dokumentów** - tylko dokumenty w statusie `draft` mogą być edytowane

## Implementacja

### Nowe kolumny w tabeli `warehouse_documents`
```sql
ALTER TABLE warehouse_documents ADD COLUMN deleted_at TIMESTAMP NULL;
ALTER TABLE warehouse_documents ADD COLUMN deleted_by BIGINT UNSIGNED NULL;
ALTER TABLE warehouse_documents ADD CONSTRAINT fk_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL;
```

### Nowe metody w modelu `WarehouseDocument`

#### `canBeDeleted(): bool`
Sprawdza czy dokument może zostać usunięty:
- Dokumenty `draft` - zawsze mogą być usunięte
- Dokumenty `posted` - mogą być usunięte tylko jeśli nie ma nowszych zatwierdzonych dokumentów w tym magazynie

#### `getDeletionBlockReason(): ?string`
Zwraca przyczynę dlaczego dokument nie może zostać usunięty lub `null` jeśli może.

#### `canBeEdited(): bool`  
Sprawdza czy dokument może zostać edytowany:
- Dokumenty `draft` - zawsze mogą być edytowane
- Dokumenty `posted` - nie mogą być edytowane

### Walidacja w kontrolerze
- **Przed usunięciem**: sprawdzenie `canBeDeleted()` i wyświetlenie błędu jeśli nie można
- **Przed edycją**: sprawdzenie `canBeEdited()` w metodach `edit()` i `update()`
- **Przy usuwaniu**: zapisanie `deleted_by` przed soft delete

### Zmiany w interfejsie
- Przyciski "Edytuj" są nieaktywne dla zatwierdzonych dokumentów
- Przyciski "Usuń" są nieaktywne dla dokumentów które nie mogą być usunięte
- Tooltip z wyjaśnieniem dlaczego akcja jest zablokowana
- Komunikaty błędów przy próbie nieprawidłowych operacji

## Zasady biznesowe

### Status dokumentów
1. **draft** - dokumenty robocze
   - Mogą być edytowane
   - Mogą być usuwane
   - Nie wpływają na stany magazynowe

2. **posted** - dokumenty zatwierdzone  
   - Nie mogą być edytowane
   - Mogą być usuwane tylko jeśli nie ma nowszych zatwierdzonych dokumentów
   - Wpływają na stany magazynowe

### Integralność chronologiczna
Dokumenty z wcześniejszymi ID nie mogą być usuwane jeśli istnieją nowsze zatwierdzone dokumenty w tym samym magazynie. Ma to na celu:
- Zachowanie ciągłości historii magazynowej
- Uniknięcie sytuacji gdzie nowsze dokumenty odwołują się do nieistniejących starszych
- Możliwość prawidłowego przeliczania stanów magazynowych

## Testowanie
System zawiera kompleksowe testy funkcjonalne (`WarehouseDocumentIntegrityTest`) sprawdzające:
- Usuwanie dokumentów draft i posted w różnych scenariuszach
- Edycję dokumentów w zależności od statusu  
- Blokowanie nieprawidłowych operacji
- Poprawność komunikatów o błędach

## Migracja
Istniejące dokumenty pozostają bez zmian. Nowe zasady obowiązują od momentu wdrożenia systemu.