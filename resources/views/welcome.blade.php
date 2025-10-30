{{-- filepath: /Volumes/Dysk 500/projekty/Laravel/rexbit/resources/views/welcome.blade.php --}}
@extends('components.layout.app')

@section('title', 'Welcome - ' . config('app.name', 'Laravel'))

@section('content')
<div>
    <a href="#" class="block max-w-sm p-6 bg-white border border-gray-200 rounded-lg shadow-sm hover:bg-gray-100 dark:bg-gray-800 dark:border-gray-700 dark:hover:bg-gray-700">

<h5 class="mb-2 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Noteworthy technology acquisitions 2021</h5>
<p class="font-normal text-gray-700 dark:text-gray-400">Here are the biggest enterprise technology acquisitions of 2021 so far, in reverse chronological order.</p>
</a>

<div class="p-8">
    {{-- ...existing code... --}}

    <x-ui.form 
        action="/contact" 
        method="POST"
        title="Formularz kontaktowy"
        subtitle="Wypełnij poniższe pola aby się z nami skontaktować"
        submit-text="Wyślij wiadomość"
        class="mb-8"
    >
        <x-ui.input 
            name="first_name" 
            label="Imię" 
            placeholder="Jan" 
            required 
        />

        <x-ui.input 
            type="email"
            name="email" 
            label="Email" 
            placeholder="jan@example.com"
            required 
        />

        <x-ui.input 
            name="subject" 
            label="Temat" 
            placeholder="Temat wiadomości"
            required 
        />
    </x-ui.form>

    <x-ui.form 
        action="/login" 
        method="POST"
        title="Logowanie"
        submit-text="Zaloguj się"
        submit-class="bg-green-600 hover:bg-green-700 focus:ring-green-300 dark:bg-green-600 dark:hover:bg-green-700"
    >
        <x-ui.input 
            type="email"
            name="email" 
            label="Email" 
            placeholder="twoj@email.com"
            required 
        />

        <x-ui.input 
            type="password"
            name="password" 
            label="Hasło" 
            placeholder="Wprowadź hasło"
            required 
        />
    </x-ui.form>

</div>  

</div>  
@endsection