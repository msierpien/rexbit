{{-- filepath: /Volumes/Dysk 500/projekty/Laravel/rexbit/resources/views/components/layout/navigations.blade.php --}}
<nav class="bg-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Logo/Brand -->
            <div class="flex items-center">
                <a href="/" class="flex-shrink-0 flex items-center">
                    <span class="text-2xl font-bold text-gray-800">RexBit</span>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center space-x-8">
          
                @auth
                    <a href="{{ auth()->user()->role?->value === 'admin' ? route('dashboard.admin') : route('dashboard.user') }}"
                       class="text-gray-600 hover:text-gray-900 px-3 py-2 text-sm font-medium transition duration-150">
                        Dashboard
                    </a>

                    @if(auth()->user()->role?->value === 'admin')
                        <a href="{{ route('admin.users.index') }}"
                           class="text-gray-600 hover:text-gray-900 px-3 py-2 text-sm font-medium transition duration-150">
                            Użytkownicy
                        </a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-md text-sm font-medium transition duration-150">
                            Wyloguj
                        </button>
                    </form>
                @else
                    <a href="{{ route('register') }}" class="text-gray-600 hover:text-gray-900 px-3 py-2 text-sm font-medium transition duration-150">
                        Zarejestruj się
                    </a>
                    <a href="{{ route('login') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition duration-150">
                        Zaloguj się
                    </a>
                @endauth
            </div>

            <!-- Mobile menu button -->
            <div class="md:hidden flex items-center">
                <button type="button" class="mobile-menu-button inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500" aria-controls="mobile-menu" aria-expanded="false">
                    <span class="sr-only">Open main menu</span>
                    <!-- Hamburger icon -->
                    <svg class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <!-- Close icon (hidden by default) -->
                    <svg class="hidden h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu (hidden by default) -->
    <div class="mobile-menu hidden md:hidden">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3 bg-gray-50">
            <a href="{{ route('home') }}" class="text-gray-600 hover:text-gray-900 block px-3 py-2 text-base font-medium">Home</a>
            <a href="/about" class="text-gray-600 hover:text-gray-900 block px-3 py-2 text-base font-medium">About</a>
            <a href="/services" class="text-gray-600 hover:text-gray-900 block px-3 py-2 text-base font-medium">Services</a>
            <a href="/contact" class="text-gray-600 hover:text-gray-900 block px-3 py-2 text-base font-medium">Contact</a>

            @auth
                <a href="{{ auth()->user()->role?->value === 'admin' ? route('dashboard.admin') : route('dashboard.user') }}"
                   class="text-gray-600 hover:text-gray-900 block px-3 py-2 text-base font-medium">
                   Dashboard
                </a>
                @if(auth()->user()->role?->value === 'admin')
                    <a href="{{ route('admin.users.index') }}"
                       class="text-gray-600 hover:text-gray-900 block px-3 py-2 text-base font-medium">
                        Użytkownicy
                    </a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-md text-base font-medium">
                        Wyloguj
                    </button>
                </form>
            @else
                <a href="{{ route('register') }}" class="text-gray-600 hover:text-gray-900 block px-3 py-2 text-base font-medium">
                    Zarejestruj się
                </a>
                <a href="{{ route('login') }}" class="bg-blue-600 hover:bg-blue-700 text-white block px-3 py-2 rounded-md text-base font-medium mt-4">
                    Zaloguj się
                </a>
            @endauth
        </div>
    </div>
</nav>

<script>
// Mobile menu toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuButton = document.querySelector('.mobile-menu-button');
    const mobileMenu = document.querySelector('.mobile-menu');
    
    mobileMenuButton.addEventListener('click', function() {
        mobileMenu.classList.toggle('hidden');
        
        // Toggle icons
        const hamburgerIcon = mobileMenuButton.querySelector('svg:first-of-type');
        const closeIcon = mobileMenuButton.querySelector('svg:last-of-type');
        
        hamburgerIcon.classList.toggle('hidden');
        closeIcon.classList.toggle('hidden');
    });
});
</script>
