@extends('components.layout.app')

@section('title', 'Logowanie')

@section('content')
    <section class="py-12 sm:py-16 bg-gray-50 dark:bg-gray-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center mb-10">
                <h1 class="text-3xl font-semibold text-gray-900 dark:text-gray-100">Zaloguj się do panelu</h1>
                <p class="mt-3 text-gray-600 dark:text-gray-400">
                    Uzyskaj dostęp do swojego konta i personalizowanego pulpitu.
                </p>
            </div>

            @if(session('status'))
                <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">
                    {{ session('status') }}
                </div>
            @endif

            <x-ui.form
                :action="route('login.store')"
                method="POST"
                title="Dane logowania"
                submit-text="Zaloguj się"
            >
                <x-ui.input
                    type="email"
                    name="email"
                    label="Adres e-mail"
                    placeholder="twoj@email.com"
                    required
                    :value="old('email')"
                    :error="$errors->first('email')"
                />

                <x-ui.input
                    type="password"
                    name="password"
                    label="Hasło"
                    placeholder="Wprowadź hasło"
                    required
                    :error="$errors->first('password')"
                />

                <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-300">
                    <label class="inline-flex items-center gap-2">
                        <input
                            id="remember"
                            name="remember"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                            @checked(old('remember'))
                        >
                        <span>Zapamiętaj mnie</span>
                    </label>

                    <a href="#" class="font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400">Zapomniałeś hasła?</a>
                </div>
            </x-ui.form>
        </div>
    </section>
@endsection
