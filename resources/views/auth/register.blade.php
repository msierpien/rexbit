@extends('components.layout.app')

@section('title', 'Rejestracja')

@section('content')
    <section class="py-12 sm:py-16 bg-gray-50 dark:bg-gray-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center mb-10">
                <h1 class="text-3xl font-semibold text-gray-900 dark:text-gray-100">Utwórz nowe konto</h1>
                <p class="mt-3 text-gray-600 dark:text-gray-400">
                    Zarejestruj się, aby uzyskać dostęp do panelu użytkownika i nowych funkcji.
                </p>
            </div>

            <x-ui.form
                :action="route('register.store')"
                method="POST"
                title="Dane użytkownika"
                submit-text="Zarejestruj się"
            >
                <x-ui.input
                    name="name"
                    label="Imię i nazwisko"
                    placeholder="Jan Kowalski"
                    required
                    :value="old('name')"
                    :error="$errors->first('name')"
                />

                <x-ui.input
                    type="email"
                    name="email"
                    label="Adres e-mail"
                    placeholder="jan@example.com"
                    required
                    :value="old('email')"
                    :error="$errors->first('email')"
                />

                <x-ui.input
                    type="password"
                    name="password"
                    label="Hasło"
                    placeholder="Minimum 8 znaków"
                    required
                    :error="$errors->first('password')"
                    help-text="Użyj co najmniej 8 znaków, w tym liter i cyfr."
                />

                <x-ui.input
                    type="password"
                    name="password_confirmation"
                    label="Potwierdź hasło"
                    placeholder="Powtórz hasło"
                    required
                    :error="$errors->first('password_confirmation')"
                />

                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Masz już konto?
                    <a href="{{ route('login') }}" class="font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400">
                        Zaloguj się
                    </a>
                </p>
            </x-ui.form>
        </div>
    </section>
@endsection
