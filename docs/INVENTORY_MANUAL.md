# System Inwentaryzacji Magazynowej - Instrukcja Użytkownika

## 📋 Wprowadzenie

System inwentaryzacji magazynowej umożliwia przeprowadzenie precyzyjnej kontroli stanów magazynowych z wykorzystaniem skanera kodów EAN. System automatycznie porównuje stany rzeczywiste z systemowymi i generuje dokumenty korygujące.

## 🚀 Rozpoczęcie pracy

### 1. Dostęp do systemu inwentaryzacji
- Przejdź do **Magazyn → Inwentaryzacje** w menu głównym
- Zobaczysz listę wszystkich inwentaryzacji z możliwością filtrowania

### 2. Tworzenie nowej inwentaryzacji
1. Kliknij **"Nowa inwentaryzacja"**
2. Wprowadź nazwę (np. "Inwentaryzacja Q4 2024")
3. Wybierz magazyn do zinwentaryzowania
4. Opcjonalnie dodaj opis
5. Kliknij **"Utwórz inwentaryzację"**

## 📊 Statusy inwentaryzacji

Inwentaryzacja przechodzi przez następujące statusy:

- **🟦 Projekt** - Inwentaryzacja utworzona, można edytować
- **🟡 W trakcie** - Inwentaryzacja rozpoczęta, można skanować produkty
- **🟠 Zakończona** - Inwentaryzacja zakończona, gotowa do zatwierdzenia
- **🟢 Zatwierdzona** - Inwentaryzacja zatwierdzona, utworzono dokumenty korygujące
- **🔴 Anulowana** - Inwentaryzacja anulowana

## 📱 Przeprowadzanie inwentaryzacji

### Krok 1: Rozpocznij inwentaryzację
1. Otwórz inwentaryzację w statusie "Projekt"
2. Kliknij **"Rozpocznij"**
3. System automatycznie wczyta wszystkie produkty z aktualnych stanów magazynowych

### Krok 2: Skanowanie produktów
Po rozpoczęciu inwentaryzacji zobaczysz:
- **Floating przycisk skanera** w prawym dolnym rogu
- Tabela z produktami do policzenia

#### Używanie skanera EAN:
1. **Automatyczne skanowanie**:
   - Podłącz skaner USB (keyboard emulation)
   - Skanuj kody EAN produktów
   - System automatycznie znajdzie produkt i zaktualizuje ilość

2. **Ręczne wprowadzanie**:
   - Kliknij ikonę skanera w prawym dolnym rogu
   - Wprowadź kod EAN ręcznie
   - Kliknij "Skanuj" lub naciśnij Enter

3. **Korekta ilości**:
   - Po zeskanowaniu produktu możesz szybko skorygować ilość
   - Użyj przycisków +/- lub wprowadź dokładną ilość
   - Zmiany są zapisywane natychmiast

### Krok 3: Analiza rozbieżności
System automatycznie:
- Porównuje policzony stan z stanem systemowym
- Oznacza rozbieżności kolorami:
  - 🟢 **Zgodność** - stan się zgadza
  - 🟡 **Nadwyżka** - policzono więcej niż w systemie
  - 🔴 **Niedobór** - policzono mniej niż w systemie

### Krok 4: Zakończenie inwentaryzacji
1. Po policzeniu wszystkich produktów kliknij **"Zakończ"**
2. Sprawdź podsumowanie rozbieżności
3. Kliknij **"Zatwierdź"** aby utworzyć dokumenty korygujące

## 📈 Funkcje zaawansowane

### Filtrowanie rozbieżności
- Użyj przycisku **"Tylko rozbieżności"** aby pokazać tylko produkty z różnicami
- Pomaga skupić się na problemach wymagających uwagi

### Podsumowanie inwentaryzacji
System automatycznie oblicza:
- Liczbę policzonych produktów
- Liczbę rozbieżności
- Wartość finansową rozbieżności
- Szczegółowe statystyki

### Dokumenty korygujące
Po zatwierdzeniu inwentaryzacji system automatycznie utworzy:
- **Dokument IN** - dla nadwyżek (produkty których jest więcej)
- **Dokument OUT** - dla niedoborów (produkty których jest mniej)

## 🔧 Rozwiązywanie problemów

### Skaner nie działa
1. Sprawdź czy skaner jest podłączony (USB)
2. Upewnij się, że skaner działa w trybie "keyboard emulation"
3. Przetestuj skaner w notatniku - powinien wpisywać kod + Enter

### Produkt nie został znaleziony
1. Sprawdź czy produkt ma przypisany kod EAN w systemie
2. Upewnij się, że kod EAN jest poprawny
3. Kod EAN musi być dokładnie taki sam jak w systemie

### Błędy przy zapisywaniu
1. Sprawdź połączenie internetowe
2. Odśwież stronę i spróbuj ponownie
3. Skontaktuj się z administratorem jeśli problem się powtarza

## 💡 Wskazówki i najlepsze praktyki

### Przed inwentaryzacją
- Upewnij się, że wszystkie produkty mają kody EAN
- Sprawdź czy skaner działa poprawnie
- Zaplanuj inwentaryzację poza godzinami szczytu

### Podczas inwentaryzacji
- Skanuj produkty systematycznie (np. półka po półce)
- Używaj funkcji filtrowania dla lepszej orientacji
- Zapisuj uwagi do problematycznych pozycji

### Po inwentaryzacji
- Przejrzyj wszystkie rozbieżności przed zatwierdzeniem
- Sprawdź czy dokumenty korygujące zostały utworzone
- Archiwizuj dokumentację inwentaryzacji

## 📞 Pomoc techniczna

W przypadku problemów:
1. Sprawdź konsolę przeglądarki (F12) pod kątem błędów
2. Sprawdź czy wszystkie dane są poprawnie wprowadzone
3. Skontaktuj się z działem IT z opisem problemu

---

**Wersja dokumentacji:** 1.0  
**Data aktualizacji:** 3 listopada 2025  
**Kompatybilność:** Laravel 11, React 18, Inertia.js