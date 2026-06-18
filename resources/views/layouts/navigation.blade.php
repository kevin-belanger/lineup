<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                        <x-application-logo class="block h-9 w-9 object-contain" />
                        <span class="text-lg font-semibold text-gray-900">
                            {{ app(\App\Services\ApplicationSettings::class)->displayName() }}
                        </span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    @if (Auth::user()->is_student)
                        <x-nav-link :href="route('student.dashboard')" :active="request()->routeIs('student.dashboard')">
                            {{ __('Student') }}
                        </x-nav-link>
                        <x-nav-link :href="route('student.history')" :active="request()->routeIs('student.history')">
                            {{ __('History') }}
                        </x-nav-link>
                    @endif
                    @if (Auth::user()->is_teacher)
                        <x-nav-link :href="route('teacher.dashboard')" :active="request()->routeIs('teacher.dashboard')">
                            {{ __('Teacher') }}
                        </x-nav-link>
                        <x-nav-link :href="route('teacher.priority-requests.index')" :active="request()->routeIs('teacher.priority-requests.*')">
                            {{ __('Priority requests') }}
                        </x-nav-link>
                        <x-nav-link :href="route('teacher.personal-notes.index')" :active="request()->routeIs('teacher.personal-notes.*')">
                            {{ __('Personal notes') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 sm:gap-3">
                @if (Auth::user()->canManageAdministration())
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-transparent text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" aria-label="{{ __('Administration') }}">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.654.852.075.037.15.076.222.117.323.183.72.19 1.04.003l1.124-.656a1.125 1.125 0 0 1 1.45.259l1.296 2.247a1.125 1.125 0 0 1-.26 1.45l-.975.718c-.305.224-.457.6-.42.977.007.083.011.167.011.252s-.004.169-.012.252c-.036.377.116.753.421.977l.975.718c.46.34.57.977.26 1.45l-1.296 2.247a1.125 1.125 0 0 1-1.45.259l-1.124-.656c-.32-.187-.717-.18-1.04.003a6.842 6.842 0 0 1-.222.117c-.341.166-.591.478-.654.852l-.213 1.281c-.09.542-.56.94-1.11.94h-2.593c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.063-.374-.313-.686-.654-.852a6.842 6.842 0 0 1-.222-.117c-.323-.183-.72-.19-1.04-.003l-1.124.656a1.125 1.125 0 0 1-1.45-.259l-1.296-2.247a1.125 1.125 0 0 1 .26-1.45l.975-.718c.305-.224.457-.6.42-.977a6.472 6.472 0 0 1-.011-.252c0-.085.004-.169.012-.252.036-.377-.116-.753-.421-.977l-.975-.718a1.125 1.125 0 0 1-.26-1.45l1.296-2.247a1.125 1.125 0 0 1 1.45-.259l1.124.656c.32.187.717.18 1.04-.003.072-.041.147-.08.222-.117.341-.166.591-.478.654-.852l.213-1.281Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('admin.users.index')">
                                {{ __('Users') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('admin.classrooms.index')">
                                {{ __('Rooms') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('admin.subjects.index')">
                                {{ __('Subjects') }}
                            </x-dropdown-link>
                            @if (Auth::user()->canManageSettings())
                                <x-dropdown-link :href="route('admin.settings.edit')">
                                    {{ __('Settings') }}
                                </x-dropdown-link>
                            @endif
                        </x-slot>
                    </x-dropdown>
                @endif

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->fullName() }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            @if (Auth::user()->is_student)
                <x-responsive-nav-link :href="route('student.dashboard')" :active="request()->routeIs('student.dashboard')">
                    {{ __('Student') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('student.history')" :active="request()->routeIs('student.history')">
                    {{ __('History') }}
                </x-responsive-nav-link>
            @endif
            @if (Auth::user()->is_teacher)
                <x-responsive-nav-link :href="route('teacher.dashboard')" :active="request()->routeIs('teacher.dashboard')">
                    {{ __('Teacher') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('teacher.priority-requests.index')" :active="request()->routeIs('teacher.priority-requests.*')">
                    {{ __('Priority requests') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('teacher.personal-notes.index')" :active="request()->routeIs('teacher.personal-notes.*')">
                    {{ __('Personal notes') }}
                </x-responsive-nav-link>
            @endif
            @if (Auth::user()->canManageAdministration())
                <div class="px-4 pt-3 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">
                    {{ __('Administration') }}
                </div>
                <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                    {{ __('Users') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.classrooms.index')" :active="request()->routeIs('admin.classrooms.*')">
                    {{ __('Rooms') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.subjects.index')" :active="request()->routeIs('admin.subjects.*')">
                    {{ __('Subjects') }}
                </x-responsive-nav-link>
                @if (Auth::user()->canManageSettings())
                    <x-responsive-nav-link :href="route('admin.settings.edit')" :active="request()->routeIs('admin.settings.*')">
                        {{ __('Settings') }}
                    </x-responsive-nav-link>
                @endif
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->fullName() }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
