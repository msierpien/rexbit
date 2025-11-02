# System zarządzania statusami dokumentów magazynowych

## Opis problemu
Poprzedni system statusów był uproszczony - miał tylko statusy `draft` i `posted` bez jasnych zasad przejść między nimi i brak kontroli nad operacjami w zależności od statusu.

## Rozwiązanie - State Machine
Wprowadzono kompletny system zarządzania statusami oparty na state machine z jasno określonymi stanami i dozwolonymi przejściami.

### Statusy dokumentów

#### 1. **DRAFT** (Roboczy)
- **Opis:** Dokument w fazie tworzenia/edycji
- **Dozwolone operacje:** edycja, usuwanie, przejście do POSTED/CANCELLED
- **Wpływ na magazyn:** brak

#### 2. **POSTED** (Zatwierdzony) 
- **Opis:** Dokument zatwierdzony i wpływający na stany magazynowe
- **Dozwolone operacje:** przejście do CANCELLED/ARCHIVED, usuwanie z ograniczeniami
- **Wpływ na magazyn:** tak - ruchy magazynowe są aplikowane

#### 3. **CANCELLED** (Anulowany)
- **Opis:** Dokument anulowany (cofnięcie zatwierdzonego lub porzucenie roboczego)
- **Dozwolone operacje:** przejście do ARCHIVED, usuwanie
- **Wpływ na magazyn:** cofnięcie ruchów jeśli był zatwierdzony

#### 4. **ARCHIVED** (Zarchiwizowany)
- **Opis:** Dokument zarchiwizowany - tylko do odczytu
- **Dozwolone operacje:** tylko odczyt
- **Wpływ na magazyn:** zachowanie obecnego stanu

### Dozwolone przejścia statusów

```
DRAFT → POSTED     (zatwierdzenie)
DRAFT → CANCELLED  (anulowanie roboczego)

POSTED → CANCELLED (anulowanie zatwierdzonego)  
POSTED → ARCHIVED  (archiwizacja zatwierdzonego)

CANCELLED → ARCHIVED (archiwizacja anulowanego)

ARCHIVED → brak (status finalny)
```

## Implementacja

### Enum `WarehouseDocumentStatus`
```php
enum WarehouseDocumentStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted'; 
    case CANCELLED = 'cancelled';
    case ARCHIVED = 'archived';
}
```

**Metody pomocnicze:**
- `canTransitionTo(status)` - sprawdza dozwolone przejścia
- `allowsEditing()` - czy można edytować w tym statusie
- `allowsDeletion()` - czy można usunąć bez warunków
- `allowsConditionalDeletion()` - czy można usunąć z warunkami
- `affectsStock()` - czy wpływa na stany magazynowe
- `badgeClass()` - klasy CSS dla wyświetlania

### Model `WarehouseDocument`
**Nowe metody:**
- `changeStatus(newStatus, user)` - zmienia status z walidacją i loggingiem
- `canTransitionTo(status)` - deleguje do enuma
- `getAvailableTransitions()` - zwraca dostępne przejścia dla UI

**Enhanced metody:**
- `canBeDeleted()` - rozszerzona logika uwzględniająca nowe statusy
- `canBeEdited()` - deleguje do enuma

### Serwis `WarehouseDocumentService`
**Nowe metody:**
- `post(document, user)` - zatwierdza dokument (draft → posted)
- `cancel(document, user, reason)` - anuluje dokument z powodem
- `archive(document, user)` - archiwizuje dokument

**Funkcjonalności:**
- Automatyczne zarządzanie ruchami magazynowymi przy zmianach statusu
- Logging zmian statusów w metadata dokumentu
- Walidacja dozwolonych przejść

### Kontroler `WarehouseDocumentController`
**Nowe endpointy:**
- `POST /warehouse/documents/{id}/post` - zatwierdzenie
- `POST /warehouse/documents/{id}/cancel` - anulowanie (z opcjonalnym powodem)
- `POST /warehouse/documents/{id}/archive` - archiwizacja

**Rozszerzone dane:**
- `status_label` - czytelna nazwa statusu
- `status_badge_class` - klasy CSS dla badge'ów
- `available_transitions` - dostępne akcje dla danego dokumentu

### Frontend Components

#### `DocumentStatusActions`
- Dynamiczne przyciski akcji na podstawie dostępnych przejść
- Modal do wprowadzania powodu anulowania
- Potwierdzenia przed wykonaniem akcji

#### `StatusBadge`
- Wyświetla status z odpowiednimi kolorami
- Automatyczne pobieranie stylów z enuma

## Zasady biznesowe

### 1. Workflow dokumentów
- **Draft** → można swobodnie edytować i usuwać
- **Posted** → tylko odczyt, można anulować lub zarchiwizować
- **Cancelled** → tylko odczyt, można zarchiwizować  
- **Archived** → całkowicie niemodyfikowalny

### 2. Integralność magazynowa
- Przy przejściu draft → posted: aplikacja ruchów magazynowych
- Przy przejściu posted → cancelled: cofnięcie ruchów magazynowych
- Status archived zachowuje obecny stan

### 3. Logging i audit
- Wszystkie zmiany statusów logowane w metadata dokumentu
- Zapisywanie kto i kiedy zmienił status
- Powody anulowania zapisywane w metadata

### 4. Usuwanie dokumentów
- **Bezwarunkowe:** draft, cancelled
- **Warunkowe:** posted (tylko jeśli brak nowszych posted w magazynie)
- **Zabronione:** archived

## Routing

```php
// Status management
POST /warehouse/documents/{id}/post     - zatwierdzenie  
POST /warehouse/documents/{id}/cancel   - anulowanie
POST /warehouse/documents/{id}/archive  - archiwizacja
```

## Testowanie

### `WarehouseDocumentStatusTest`
- Testowanie wszystkich dozwolonych i niedozwolonych przejść
- Sprawdzanie logging'u zmian statusów
- Walidacja zasad edycji i usuwania

### `WarehouseDocumentIntegrityTest`  
- Zaktualizowane do współpracy z nowym systemem statusów
- Zachowanie wcześniejszych zasad integralności

## Kompatybilność wsteczna
- Istniejące dokumenty z statusami `draft`/`posted` działają bez zmian
- Nowe statusy `cancelled`/`archived` dostępne od wdrożenia
- Brak zmian w strukturze bazy danych (status pozostaje string)

## Korzyści
1. **Jasny workflow** - jednoznaczne zasady przejść między statusami
2. **Kontrola integralności** - niemożność nieprawidłowych operacji  
3. **Audit trail** - pełne śledzenie zmian statusów
4. **Elastyczność** - łatwe dodawanie nowych statusów w przyszłości
5. **UX** - intuicyjny interfejs z kontekstowymi akcjami