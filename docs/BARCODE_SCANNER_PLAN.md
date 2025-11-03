# Plan Implementacji Skanera Kodów EAN dla Dokumentów Magazynowych

## 📋 Analiza Wymagań

### Funkcje Podstawowe
1. ✅ **Skanowanie produktów po kodzie EAN**
   - Skaner fizyczny USB (działa jak klawiatura)
   - Automatyczne dodawanie/aktualizacja ilości produktu
   - Focus na polu skanowania

2. ✅ **Zwiększanie ilości przy ponownym skanowaniu**
   - Pierwsze skanowanie: dodaje 1 szt
   - Kolejne skanowanie tego samego produktu: +1 szt
   - Możliwość manualnej edycji ilości

3. ✅ **Dźwięki feedback**
   - Sukces (produkt znaleziony): pozytywny dźwięk (beep)
   - Błąd (produkt nie znaleziony): negatywny dźwięk (error)
   - Używamy Web Audio API (natywne, bez zależności)

4. ✅ **Integracja z dokumentami magazynowymi**
   - Dodanie komponentu do Create/Edit dokumentów
   - Możliwość przełączania między skanowaniem a manualnym dodawaniem
   - Zachowanie istniejącej funkcjonalności

### Funkcje Przyszłościowe
- ⏳ **Zbieranie zamówień** (kolejna faza)
- ⏳ **Inwentaryzacja** (kolejna faza)
- ⏳ **Szybkie przyjęcie/wydanie** (kolejna faza)

---

## 🔍 Analiza Istniejącego Kodu

### Struktura Komponentów
```
resources/js/
├── Pages/Warehouse/Documents/
│   ├── Create.jsx          ← Użycie DocumentItems
│   ├── Edit.jsx            ← Użycie DocumentItems
│   └── Index.jsx
└── components/warehouse/
    ├── document-items.jsx  ← Główny komponent pozycji
    ├── product-select.jsx  ← Select produktów
    └── stock-display.jsx   ← Wyświetlanie stanów
```

### Kluczowe Informacje
1. **Produkty zawierają pole `ean`** ✅
   ```javascript
   products: [{ id, name, sku, ean, warehouse_stocks }]
   ```

2. **DocumentItems używa state zarządzanego w parent**
   ```javascript
   const [items, setItems] = useState(initialItems);
   <DocumentItems items={items} onChange={setItems} />
   ```

3. **Format item w state**
   ```javascript
   {
     product_id: '',
     quantity: 1,
     unit_price: '',
     vat_rate: ''
   }
   ```

---

## ✅ Wykonalność Techniczna

### 1. Skanery USB (Keyboard Emulation)
✅ **TAK - W pełni wspierane**
- Skanery USB działają jak klawiatura
- Wysyłają kod EAN + Enter
- Używamy `keydown`/`keyup` events
- Debouncing dla szybkich skanów

### 2. Web Audio API
✅ **TAK - Natywne wsparcie przeglądarek**
```javascript
const audioContext = new AudioContext();
// Generowanie tonu: oscillator.frequency.value
```

### 3. React State Management
✅ **TAK - Proste rozszerzenie**
- Dodajemy nowy komponent `BarcodeScanner`
- Integrujemy z istniejącym `DocumentItems`
- Używamy `useCallback` dla performance

### 4. Wyszukiwanie po EAN
✅ **TAK - Dane już dostępne**
```javascript
const product = products.find(p => p.ean === scannedCode);
```

---

## 🏗️ Architektura Rozwiązania

### Komponenty do Utworzenia

```
resources/js/components/warehouse/
├── barcode-scanner.jsx          ← Nowy: Główny komponent skanera
├── barcode-input.jsx            ← Nowy: Input z obsługą skanowania
├── scanner-sounds.js            ← Nowy: Web Audio API helpers
└── document-items.jsx           ← Modyfikacja: Dodanie trybu skanowania
```

---

## 📝 Szczegółowy Plan Implementacji

### FAZA 1: Podstawy Skanowania (2-3h)

#### 1.1 Utility: Scanner Sounds
**Plik:** `resources/js/lib/scanner-sounds.js`

```javascript
// Generowanie dźwięków bez plików audio
class ScannerSounds {
  constructor() {
    this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
  }

  playSuccess() {
    // Beep 800Hz, 150ms
  }

  playError() {
    // Buzz 200Hz, 300ms
  }
}
```

**Zalety:**
- Brak zależności od plików .mp3/.wav
- Małe rozmiary
- Natywne wsparcie

#### 1.2 Hook: useBarcodeScan
**Plik:** `resources/js/hooks/useBarcodeScan.js`

```javascript
export function useBarcodeScan({ onScan, enabled = true }) {
  const [buffer, setBuffer] = useState('');
  const timeoutRef = useRef(null);

  useEffect(() => {
    if (!enabled) return;

    const handleKeyPress = (e) => {
      // Zbieraj znaki
      // Wykryj Enter = koniec skanowania
      // Wywołaj onScan(code)
    };

    window.addEventListener('keypress', handleKeyPress);
    return () => window.removeEventListener('keypress', handleKeyPress);
  }, [enabled, onScan]);

  return { buffer };
}
```

**Features:**
- Automatyczne wykrywanie końca skanowania (Enter)
- Buffer timeout (zapobiega konfliktom z normalnym wpisywaniem)
- Możliwość wyłączenia (toggle)

#### 1.3 Komponent: BarcodeInput
**Plik:** `resources/js/components/warehouse/barcode-input.jsx`

```jsx
export default function BarcodeInput({ onScan, disabled = false }) {
  const inputRef = useRef(null);
  const [value, setValue] = useState('');

  const handleSubmit = (e) => {
    e.preventDefault();
    if (value.trim()) {
      onScan(value.trim());
      setValue('');
    }
  };

  // Auto-focus po skanowaniu
  useEffect(() => {
    inputRef.current?.focus();
  }, [value]);

  return (
    <form onSubmit={handleSubmit}>
      <Input
        ref={inputRef}
        value={value}
        onChange={e => setValue(e.target.value)}
        placeholder="Zeskanuj kod kreskowy lub wpisz EAN..."
        disabled={disabled}
      />
    </form>
  );
}
```

### FAZA 2: Integracja z DocumentItems (2-3h)

#### 2.1 Modyfikacja DocumentItems
**Plik:** `resources/js/components/warehouse/document-items.jsx`

**Zmiany:**
1. Dodanie przycisku "Tryb skanowania"
2. Wyświetlanie `BarcodeInput` w trybie skanowania
3. Logika dodawania/aktualizacji produktów

```jsx
export default function DocumentItems({ items, onChange, products, warehouseId }) {
  const [scanMode, setScanMode] = useState(false);
  const sounds = useMemo(() => new ScannerSounds(), []);

  const handleBarcodeScan = (ean) => {
    const product = products.find(p => p.ean === ean);
    
    if (!product) {
      sounds.playError();
      // Pokaż toast: "Produkt nie znaleziony"
      return;
    }

    // Sprawdź czy produkt już jest w items
    const existingIndex = items.findIndex(i => i.product_id === product.id);
    
    if (existingIndex >= 0) {
      // Zwiększ ilość
      const updated = [...items];
      updated[existingIndex].quantity = 
        parseFloat(updated[existingIndex].quantity || 0) + 1;
      onChange(updated);
    } else {
      // Dodaj nowy
      onChange([...items, {
        product_id: product.id,
        quantity: 1,
        unit_price: '',
        vat_rate: ''
      }]);
    }

    sounds.playSuccess();
  };

  return (
    <div className="space-y-4">
      {/* Przełącznik trybu */}
      <div className="flex items-center justify-between">
        <Button
          type="button"
          variant={scanMode ? "default" : "outline"}
          onClick={() => setScanMode(!scanMode)}
        >
          {scanMode ? "Tryb skanowania aktywny" : "Włącz skaner"}
        </Button>
      </div>

      {/* Input skanera (tylko w scan mode) */}
      {scanMode && (
        <BarcodeInput onScan={handleBarcodeScan} />
      )}

      {/* Istniejąca tabela */}
      <div className="overflow-x-auto">
        {/* ... reszta kodu ... */}
      </div>
    </div>
  );
}
```

#### 2.2 Toast Notifications
**Użycie:** Informowanie użytkownika

```javascript
import { toast } from 'sonner'; // lub inny toast library

// Sukces
toast.success(`Dodano: ${product.name} (+1 szt)`);

// Błąd
toast.error(`Produkt o kodzie EAN "${ean}" nie został znaleziony`);
```

### FAZA 3: UX Improvements (1-2h)

#### 3.1 Wizualne Wskaźniki
- Badge z licznikiem zeskanowanych produktów
- Podświetlenie ostatnio zeskanowanego wiersza
- Animacja dodania (+1)

#### 3.2 Skróty Klawiszowe
```javascript
// Ctrl+S = Toggle scan mode
// Ctrl+Enter = Save document
// ESC = Exit scan mode
```

#### 3.3 Mobilny Support (opcjonalnie)
- Camera API dla skanowania przez kamerę telefonu
- Używamy biblioteki: `react-qr-barcode-scanner`

---

## 🎯 Roadmap Implementacji

### Sprint 1 (4-6h) - MVP
- [ ] **Task 1.1:** Utworzyć `scanner-sounds.js` (30min)
- [ ] **Task 1.2:** Utworzyć `useBarcodeScan` hook (1h)
- [ ] **Task 1.3:** Utworzyć `BarcodeInput` komponent (1h)
- [ ] **Task 1.4:** Zmodyfikować `DocumentItems` - dodać scan mode (2h)
- [ ] **Task 1.5:** Testy manualne z fizycznym skanerem (30min)

### Sprint 2 (2-3h) - Polish
- [ ] **Task 2.1:** Dodać toast notifications (sonner) (30min)
- [ ] **Task 2.2:** Dodać animacje i highlighting (1h)
- [ ] **Task 2.3:** Skróty klawiszowe (30min)
- [ ] **Task 2.4:** Dokumentacja użytkownika (30min)

### Sprint 3 (opcjonalny, 3-4h) - Advanced
- [ ] **Task 3.1:** Camera scanning (mobile) (2h)
- [ ] **Task 3.2:** Batch scanning mode (1h)
- [ ] **Task 3.3:** History ostatnich skanów (1h)

---

## 🧪 Plan Testowania

### Testy Manualne
1. **Skanowanie podstawowe**
   - ✓ Zeskanuj produkt istniejący w bazie
   - ✓ Zeskanuj produkt nieistniejący
   - ✓ Zeskanuj ten sam produkt 3x
   - ✓ Sprawdź dźwięki

2. **Edycja manualna**
   - ✓ Zmień ilość ręcznie po zeskanowaniu
   - ✓ Usuń produkt zeskanowany
   - ✓ Dodaj produkt manualnie w scan mode

3. **Zapisywanie dokumentu**
   - ✓ Zapisz dokument ze zeskanowanymi produktami
   - ✓ Edytuj zapisany dokument
   - ✓ Zatwierdź dokument

### Scenariusze Edge Cases
- Bardzo szybkie skanowanie (debouncing)
- Skanowanie podczas focus na innym polu
- Przełączanie trybu podczas aktywnego skanowania
- Produkt bez kodu EAN

---

## 📦 Zależności

### Wymagane (już zainstalowane)
- ✅ React 18
- ✅ Inertia.js
- ✅ Tailwind CSS

### Do Dodania
```bash
# Toast notifications
npm install sonner

# Opcjonalnie: Camera barcode scanning (mobile)
npm install react-qr-barcode-scanner
```

---

## 🔐 Bezpieczeństwo

### Validacja Backend
```php
// Controller: WarehouseDocumentController
public function store(Request $request) {
    // Istniejąca walidacja już obsługuje:
    // - product_id musi istnieć
    // - quantity musi być > 0
    // Nie wymaga zmian!
}
```

### Frontend Validation
- Sprawdzenie czy product istnieje (po EAN)
- Sprawdzenie czy product należy do user
- Sanityzacja input (trim, uppercase EAN)

---

## 📊 Metryki Sukcesu

1. **Funkcjonalność**
   - ✓ Skanowanie działa z popularnymiSkanerami USB
   - ✓ Dźwięki działają we wszystkich przeglądarkach
   - ✓ 100% kompatybilność z istniejącymi dokumentami

2. **Performance**
   - Czas reakcji na skan: < 100ms
   - Brak lagów przy szybkim skanowaniu (10 produktów/10s)

3. **UX**
   - Intuicyjne przełączanie trybów
   - Jasne komunikaty błędów
   - Skróty klawiszowe działają

---

## 🚀 Deployment

### Development
```bash
npm run dev
# Test z USB skanerem
```

### Production
```bash
npm run build
./vendor/bin/sail artisan optimize:clear
```

### Rollback Plan
- Komponent `BarcodeInput` można ukryć feature flagą
- Istniejąca funkcjonalność pozostaje nienaruszona
- Brak zmian w API/Backend

---

## 📚 Dokumentacja dla Użytkownika

### Instrukcja Obsługi Skanera

1. **Aktywacja trybu skanowania**
   - Kliknij "Włącz skaner" podczas edycji dokumentu
   - Lub użyj skrótu `Ctrl+S`

2. **Skanowanie produktów**
   - Zeskanuj kod kreskowy EAN produktu
   - Produkt zostanie automatycznie dodany z ilością 1
   - Kolejne skanowanie tego samego produktu zwiększy ilość o 1

3. **Edycja ilości**
   - Możesz ręcznie zmienić ilość w tabeli
   - Skanowanie dalej działa i dodaje +1 do aktualnej ilości

4. **Dźwięki**
   - Beep = produkt znaleziony i dodany
   - Buzz = produkt nie znaleziony (sprawdź kod EAN w bazie)

---

## ✅ Verdict: WYKONALNE!

### Podsumowanie
- ✅ **Technicznie wykonalne** - wszystkie technologie wspierane
- ✅ **Niskie ryzyko** - minimalne zmiany w istniejącym kodzie
- ✅ **Szybka implementacja** - MVP w 4-6 godzin
- ✅ **Skalowalne** - łatwe dodanie nowych funkcji (zbieranie zamówień, inwentaryzacja)

### Rekomendacja
**START IMPLEMENTATION** 🚀

Proponuję zacząć od MVP (Sprint 1) i przetestować z prawdziwym skanerem USB. Po weryfikacji, możemy dodać polish i advanced features.

### Next Steps
1. Zainstalować `sonner` (toast notifications)
2. Utworzyć komponenty w kolejności: sounds → hook → input → integration
3. Testować na bieżąco z fizycznym skanerem
4. Iteracyjne usprawnienia UX

---

## 🎓 Learning Points

### Kluczowe Technologie
- **Web Audio API** - generowanie dźwięków w przeglądarce
- **Keyboard Events** - obsługa skanerów USB
- **React Hooks** - custom hook dla skanowania
- **Debouncing** - zapobieganie duplikatom przy szybkim skanowaniu

### Best Practices
- Separacja logiki (hook) od UI (komponent)
- Accessibility - działanie bez myszy
- Progressive Enhancement - graceful degradation bez skanera
- User Feedback - dźwięki + toasts + wizualne wskaźniki
