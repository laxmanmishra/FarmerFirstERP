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
<body class="h-full bg-white">
<div class="grid min-h-full lg:grid-cols-2">
    <div class="relative hidden overflow-hidden bg-brand-950 lg:block">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgb(255_255_255/0.08),transparent_45%),radial-gradient(circle_at_80%_70%,rgb(234_179_8/0.18),transparent_40%)]"></div>
        <div class="relative flex h-full flex-col justify-between p-12 text-brand-100">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-lg bg-accent-500 text-brand-950"><x-ui.icon name="tractor" class="size-6" /></span>
                <span class="text-lg font-semibold text-white">{{ config('app.name') }}</span>
            </div>
            <div class="max-w-md">
                <h2 class="text-3xl font-semibold leading-tight text-white">{{ __('From first enquiry to final handover — one system for the whole dealership.') }}</h2>
                <p class="mt-4 text-sm text-brand-200">{{ __('CRM, sales, finance, accounts, inventory, RTO, insurance, PDI and delivery, working in parallel with complete audit.') }}</p>
            </div>
            <p class="text-xs text-brand-400">&copy; {{ now()->year }} Farmer First</p>
        </div>
    </div>
    <div class="flex items-center justify-center px-6 py-12 sm:px-12">
        <div class="w-full max-w-sm">
            {{ $slot }}
        </div>
    </div>
</div>
<x-ui.toasts />
</body>
</html>
