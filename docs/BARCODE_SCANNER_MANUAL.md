# Skaner Kodów EAN - Instrukcja Użytkownika

## 📱 Aktywacja Skanera

### 1. Otwórz dokument magazynowy
- Przejdź do **Magazyn → Dokumenty**
- Kliknij **Nowy dokument** lub edytuj istniejący dokument w statusie DRAFT

### 2. Uruchom skaner
- W prawym dolnym rogu ekranu pojawi się **niebieska ikona skanera** (gdy jesteś w sekcji "Pozycje dokumentu")
- Kliknij ikonę aby otworzyć panel skanera

## 🔍 Skanowanie Produktów

### Metoda 1: Skaner USB (Automatyczny)
1. Upewnij się, że panel skanera jest otwarty
2. **Zeskanuj kod kreskowy** produktu skanerem USB
3. Produkt zostanie automatycznie dodany z ilością **1 szt**
4. Usłyszysz pozytywny dźwięk (beep-beep) ✅
5. Zobaczysz powiadomienie toast z nazwą produktu

**Ponowne skanowanie tego samego produktu:**
- Automatycznie zwiększa ilość o **+1 szt**
- Wiersz produktu zostanie podświetlony na zielono (przez 2 sekundy)
- Tabela automatycznie przewinie się do tego produktu

### Metoda 2: Wpisanie ręczne
1. Wpisz kod EAN w pole "Zeskanuj lub wpisz kod EAN"
2. Naciśnij **Enter** lub kliknij przycisk ✓
3. Produkt zostanie dodany tak samo jak przy skanowaniu

### Błędne skanowanie
Jeśli produkt nie zostanie znaleziony:
- Usłyszysz negatywny dźwięk (buzz) ❌
- Zobaczysz powiadomienie błędu z kodem EAN
- Sprawdź czy produkt istnieje w bazie i ma przypisany kod EAN

## ⚡ Szybkie Dodawanie Większej Ilości

### Scenariusz: Zeskanowałeś 33 szt, chcesz dodać jeszcze 10 szt

1. Zeskanuj produkt (dodane: 33 szt)
2. W panelu skanera pojawi się sekcja **"Ostatnio zeskanowane"**
3. Wpisz **10** w pole "Dodaj więcej sztuk"
4. Możesz użyć przycisków **+ / -** do zmiany ilości
5. Kliknij **Dodaj**
6. Nowa ilość: **43 szt** (33 + 10) ✅

### Alternatywnie - Edycja manualna
- W tabeli produktów możesz ręcznie zmienić ilość w dowolnym momencie
- Kliknij w pole "Ilość" i wpisz nową wartość

## 🎯 Funkcje Panelu Skanera

### Sekcja 1: Input Skanowania
- **Auto-focus** - pole jest zawsze aktywne, gotowe do skanowania
- **Bufor skanowania** - widzisz co jest aktualnie skanowane
- **Przycisk ✓** - zatwierdzenie ręcznie wpisanego EAN

### Sekcja 2: Ostatnio Zeskanowane
- **Nazwa produktu** - pełna nazwa ostatnio zeskanowanego produktu
- **SKU i EAN** - identyfikatory produktu
- **Quick Add** - szybkie dodawanie większej ilości
  - Przyciski **-** / **+** do regulacji
  - Pole numeryczne do wpisania ilości
  - Przycisk **Dodaj** do zatwierdzenia

### Sekcja 3: Instrukcje
- Podstawowe informacje jak używać skanera
- Zawsze widoczne dla nowych użytkowników

## 🎨 Wizualne Wskaźniki

### Highlighting Produktów
- **Zielone tło** - produkt właśnie zeskanowany (2 sekundy)
- **Auto-scroll** - tabela automatycznie przewija do zeskanowanego produktu
- **Badge "Skanowanie..."** - aktywne skanowanie w toku

### Dźwięki
- **Beep-beep** (1000Hz → 1200Hz) - sukces ✅
- **Buzz** (200Hz) - błąd ❌
- Możliwość wyłączenia dźwięków w przyszłych wersjach

### Toast Notifications
- **Sukces** (zielony) - "Zeskanowano: [nazwa produktu]"
- **Błąd** (czerwony) - "Produkt o kodzie EAN [kod] nie został znaleziony"
- Automatycznie znikają po 3-5 sekundach

## 🔧 Wymagania Techniczne

### Skaner USB
- **Wspierane**: Wszystkie skanery USB emulujące klawiaturę
- **Format**: EAN-13, EAN-8, Code128, Code39 (wszystkie standardy)
- **Zakończenie**: Skaner powinien wysyłać **Enter** po kodzie

### Produkty w Bazie
- Produkt musi mieć **wypełnione pole EAN** w bazie danych
- EAN musi być **unikalny** dla każdego produktu
- Format: 8-13 cyfr (EAN-8 lub EAN-13)

### Przeglądarka
- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- Web Audio API musi być wspierane (dla dźwięków)

## 💡 Wskazówki i Triki

### 1. Maksymalna Szybkość
- Skanuj produkty jeden po drugim bez przerw
- System automatycznie obsługuje szybkie skanowanie (debouncing)
- Nie musisz czekać na animacje

### 2. Grupowanie Produktów
- Najpierw zeskanuj wszystkie **różne produkty** (po 1 szt każdy)
- Potem użyj funkcji **"Dodaj więcej sztuk"** dla produktów z większą ilością
- To przyspiesza proces przy dużych dostawach

### 3. Korekta Błędów
- Jeśli zeskanowałeś zły produkt, po prostu go **usuń** przyciskiem Usuń w tabeli
- Możesz też zmienić ilość na **0** jeśli chcesz zatrzymać wiersz

### 4. Praca bez Skanera
- Panel można używać również **bez fizycznego skanera**
- Wystarczy wpisać EAN ręcznie i nacisnąć Enter
- Przydatne przy pracy mobilnej lub zdalnej

### 5. Zamknięcie Panelu
- Kliknij **X** w prawym górnym rogu panelu
- Panel można otworzyć ponownie klikając **niebieską ikonę** w prawym dolnym rogu
- Wszystkie zeskanowane produkty są **zachowane** w tabeli

## 🚀 Przyszłe Funkcje (Roadmap)

### W Planach
- [ ] Camera scanning - skanowanie przez kamerę telefonu (QR/barcode)
- [ ] Batch mode - skanowanie wielu produktów bez przełączania
- [ ] Historia skanów - podgląd 10 ostatnio zeskanowanych produktów
- [ ] Statystyki - licznik zeskanowanych produktów w sesji
- [ ] Export skanów - eksport do CSV/Excel
- [ ] Tryb inwentaryzacji - specjalny tryb do inwentaryzacji magazynu
- [ ] Zbieranie zamówień - integracja z modułem zamówień

## ❓ FAQ

### Q: Dlaczego skanowanie nie działa?
**A:** Sprawdź:
1. Czy panel skanera jest **otwarty** (niebieski panel widoczny)
2. Czy produkt ma **wypełnione pole EAN** w bazie
3. Czy skaner jest **podłączony** i działa (przetestuj w notatniku)
4. Czy nie jesteś w innym polu input (kliknij w pole EAN w panelu)

### Q: Produkt dodaje się 2 razy
**A:** To normalne działanie! Ponowne skanowanie **zwiększa ilość** zamiast dodawać nowy wiersz.

### Q: Nie słyszę dźwięków
**A:** 
1. Sprawdź głośność przeglądarki/systemu
2. Niektóre przeglądarki blokują dźwięki do pierwszej interakcji użytkownika
3. Kliknij gdziekolwiek na stronie i spróbuj ponownie

### Q: Czy mogę używać skanera na telefonie?
**A:** Obecnie skaner USB działa tylko na komputerach. Skanowanie przez kamerę (QR/barcode) będzie dostępne w przyszłych wersjach.

### Q: Czy mogę wyłączyć dźwięki?
**A:** Obecnie nie ma takiej opcji w UI, ale funkcja będzie dodana w przyszłych wersjach.

## 📞 Wsparcie

W razie problemów:
1. Sprawdź czy kod EAN jest poprawny w bazie (Produkty → Lista → Edycja)
2. Przetestuj skaner w prostym edytorze tekstu (Notatnik)
3. Sprawdź konsolę przeglądarki (F12) pod kątem błędów
4. Skontaktuj się z administratorem systemu

---

**Wersja dokumentacji:** 1.0  
**Data aktualizacji:** 3 listopada 2025  
**Kompatybilność:** Laravel 11, React 18, Inertia.js
