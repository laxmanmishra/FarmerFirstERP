@php
    $user = auth()->user();
    $navigation = \App\Support\Navigation::for($user);
    $branches = $user->accessibleBranches();
    $currentBranch = $branches->firstWhere('id', $user->current_branch_id) ?? $branches->first();
    $unreadCount = $user->unreadNotifications()->count();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
<div x-data="{ sidebarOpen: false }" class="min-h-full">
    {{-- Mobile sidebar backdrop --}}
    <div x-show="sidebarOpen" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden" x-on:click="sidebarOpen = false"></div>

    {{-- Sidebar --}}
    <aside x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-brand-950 text-brand-100 transition-transform duration-200 lg:translate-x-0">
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-5">
            <span class="grid size-9 place-items-center rounded-lg bg-accent-500 text-brand-950">
                <x-ui.icon name="tractor" class="size-5" />
            </span>
            <div class="leading-tight">
                <p class="text-sm font-semibold text-white">{{ config('app.name') }}</p>
                <p class="text-[11px] text-brand-300">{{ __('Dealership ERP') }}</p>
            </div>
        </div>

        <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4" aria-label="{{ __('Main navigation') }}">
            @foreach ($navigation as $section)
                <div>
                    @if ($section['label'])
                        <p class="mb-1.5 px-3 text-[11px] font-semibold uppercase tracking-wider text-brand-400">{{ $section['label'] }}</p>
                    @endif
                    <ul class="space-y-0.5">
                        @foreach ($section['items'] as $item)
                            <li>
                                <a href="{{ $item['url'] }}" wire:navigate @class([
                                    'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors focus-ring',
                                    'bg-white/10 text-white' => $item['active'],
                                    'text-brand-200 hover:bg-white/5 hover:text-white' => ! $item['active'],
                                ]) @if ($item['active']) aria-current="page" @endif>
                                    <x-ui.icon :name="$item['icon']" @class(['size-5 shrink-0', 'text-accent-500' => $item['active'], 'text-brand-400 group-hover:text-brand-200' => ! $item['active']]) />
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="border-t border-white/10 px-5 py-3 text-[11px] text-brand-400">
            {{ now()->format('D, d M Y') }}
        </div>
    </aside>

    <div class="lg:pl-64">
        {{-- Top bar --}}
        <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button type="button" class="focus-ring -ml-1 rounded-md p-2 text-slate-500 lg:hidden" x-on:click="sidebarOpen = true">
                <span class="sr-only">{{ __('Open navigation') }}</span>
                <x-ui.icon name="menu" class="size-6" />
            </button>

            <div class="flex-1"></div>

            {{-- Branch selector --}}
            @if ($branches->count() > 1)
                <form method="POST" action="{{ route('branch.switch') }}" class="hidden sm:block">
                    @csrf
                    <label for="branch-switch" class="sr-only">{{ __('Branch') }}</label>
                    <select id="branch-switch" name="branch_id" onchange="this.form.submit()" class="form-control h-9 py-1 pr-8 text-sm">
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected($currentBranch?->id === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </form>
            @elseif ($currentBranch)
                <span class="hidden items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-600 sm:inline-flex">
                    <x-ui.icon name="building" class="size-4" /> {{ $currentBranch->name }}
                </span>
            @endif

            {{-- Notifications --}}
            <div x-data="{ open: false }" class="relative">
                <button type="button" x-on:click="open = !open" class="focus-ring relative rounded-full p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700">
                    <span class="sr-only">{{ __('Notifications') }}</span>
                    <x-ui.icon name="bell" class="size-5" />
                    @if ($unreadCount > 0)
                        <span class="absolute right-1 top-1 grid min-w-4 place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                    @endif
                </button>
                <div x-show="open" x-cloak x-transition x-on:click.outside="open = false"
                    class="absolute right-0 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-(--shadow-overlay)">
                    <p class="border-b border-slate-100 px-4 py-3 text-sm font-semibold text-slate-900">{{ __('Notifications') }}</p>
                    <ul class="max-h-80 divide-y divide-slate-100 overflow-y-auto">
                        @forelse ($user->unreadNotifications()->latest()->limit(8)->get() as $notification)
                            <li class="px-4 py-3 text-sm">
                                <p class="text-slate-700">{{ $notification->data['message'] ?? __('Notification') }}</p>
                                <p class="mt-0.5 text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</p>
                            </li>
                        @empty
                            <li class="px-4 py-8 text-center text-sm text-slate-500">{{ __('You are all caught up.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            {{-- User menu --}}
            <div x-data="{ open: false }" class="relative">
                <button type="button" x-on:click="open = !open" class="focus-ring flex items-center gap-2 rounded-full py-1 pl-1 pr-2 hover:bg-slate-100">
                    <span class="grid size-8 place-items-center rounded-full bg-brand-100 text-xs font-semibold text-brand-800">
                        {{ \Illuminate\Support\Str::of($user->name)->explode(' ')->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}
                    </span>
                    <span class="hidden text-left leading-tight md:block">
                        <span class="block text-sm font-medium text-slate-800">{{ $user->name }}</span>
                        <span class="block text-[11px] text-slate-500">{{ $user->getRoleNames()->first() }}</span>
                    </span>
                    <x-ui.icon name="chevron-down" class="hidden size-4 text-slate-400 md:block" />
                </button>
                <div x-show="open" x-cloak x-transition x-on:click.outside="open = false"
                    class="absolute right-0 mt-2 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-(--shadow-overlay)">
                    <div class="border-b border-slate-100 px-4 py-2.5">
                        <p class="truncate text-sm font-medium text-slate-900">{{ $user->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                    </div>
                    <a href="{{ route('password.change') }}" wire:navigate class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        <x-ui.icon name="lock" class="size-4 text-slate-400" /> {{ __('Change password') }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">
                            <x-ui.icon name="logout" class="size-4 text-slate-400" /> {{ __('Sign out') }}
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-[1600px] px-4 py-6 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>
    </div>
</div>

<x-ui.toasts />
</body>
</html>
