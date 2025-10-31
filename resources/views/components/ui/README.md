# UI Components

Zestaw komponentów Blade w katalogu `resources/views/components/ui` pomaga utrzymać spójny wygląd i przyspiesza budowanie widoków. Poniżej krótki opis wraz z przykładowym użyciem.

| Komponent | Zastosowanie | Przykład |
| --- | --- | --- |
| `x-ui.card` | Karty/sekcje z opcjonalnym tytułem i akcjami | `<x-ui.card title="Lista" :subtitle="$count">Treść</x-ui.card>` |
| `x-ui.stat-tile` | Kafelki statystyk na dashboardach | `<x-ui.stat-tile label="Użytkownicy" value="128" trend="+5%" />` |
| `x-ui.alert` | Komunikaty informacyjne/sukces/błąd | `<x-ui.alert variant="success">Zapisano!</x-ui.alert>` |
| `x-ui.badge` | Etykiety statusów/rol | `<x-ui.badge variant="warning">Pending</x-ui.badge>` |
| `x-ui.button` | Przyciski i linki stylowane jak przycisk | `<x-ui.button>Akcja</x-ui.button>` lub `<x-ui.button as="a" href="#">Link</x-ui.button>` |
| `x-ui.select` | Pola wyboru z etykietą i walidacją | `<x-ui.select name="role" :options="$roles" label="Rola" />` |
| `x-ui.table` (+ `table.head`, `table.heading`, `table.row`, `table.cell`) | Tabele danych z responsywnym wrapperem | zob. `resources/views/dashboard/admin/users/index.blade.php` |
| `x-ui.avatar` | Inicjały użytkownika w formie avatara | `<x-ui.avatar :name="$user->name" size="md" />` |

Aby zobaczyć ich użycie w praktyce, zajrzyj do:

- `resources/views/dashboard/admin/users/index.blade.php`
- `resources/views/dashboard/admin/users/edit.blade.php`
- `resources/views/dashboard/admin.blade.php`
- `resources/views/dashboard/user.blade.php`
