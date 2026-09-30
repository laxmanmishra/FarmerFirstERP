@php use App\Support\Money; $payment = $receipt->payment; $order = $receipt->accountFile->order; $customer = $order->customer; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('Receipt') }} {{ $receipt->receipt_no }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A5 landscape; margin: 10mm; }
        @media print { .no-print { display: none !important; } body { background: #fff; } }
    </style>
</head>
<body class="bg-slate-100 text-slate-800">
<div class="no-print mx-auto flex max-w-3xl justify-end gap-2 px-4 pt-6">
    <button onclick="window.print()" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white">{{ __('Print / Save as PDF') }}</button>
</div>
<main class="relative mx-auto my-6 max-w-3xl overflow-hidden bg-white p-10 shadow-sm print:my-0 print:p-0 print:shadow-none">
    @if ($receipt->cancelled_at)
        <p class="pointer-events-none absolute inset-0 flex items-center justify-center text-7xl font-black uppercase tracking-widest text-rose-500/20 -rotate-12">{{ __('Cancelled') }}</p>
    @endif
    <header class="flex items-start justify-between border-b-2 border-brand-700 pb-4">
        <div>
            <h1 class="text-xl font-bold text-brand-900">{{ $company?->name }}</h1>
            <p class="max-w-xs whitespace-pre-line text-xs text-slate-600">{{ $receipt->accountFile->branch->address ?? $company?->address }}</p>
            @if ($company?->gstin)<p class="text-xs text-slate-600">GSTIN {{ $company->gstin }}</p>@endif
        </div>
        <div class="text-right">
            <p class="text-lg font-semibold uppercase tracking-wide text-slate-700">{{ __('Payment receipt') }}</p>
            <p class="tabular text-sm font-medium">{{ $receipt->receipt_no }}</p>
            <p class="text-xs text-slate-500">{{ __('Date') }}: {{ $receipt->issued_at->format('d M Y') }}</p>
        </div>
    </header>

    <section class="mt-6 space-y-2 text-sm">
        <p>{{ __('Received with thanks from') }} <span class="font-semibold">{{ $payment->payer_type->value === 'financer' ? __('the financer on behalf of') : '' }} {{ $customer->name }}</span>
            ({{ $customer->customer_no }}, {{ $customer->locationLabel() }})</p>
        <p>{{ __('the sum of') }} <span class="tabular text-lg font-bold">₹ {{ Money::format($receipt->amount) }}</span></p>
        <p>{{ __('by :mode', ['mode' => $payment->mode->label()]) }}@if ($payment->reference_no) · {{ __('ref.') }} {{ $payment->reference_no }}@endif @if ($payment->bank_name) · {{ $payment->bank_name }}@endif
            @if ($payment->instrument_date) · {{ __('dated') }} {{ $payment->instrument_date->format('d M Y') }}@endif</p>
        <p>{{ __('against booking') }} <span class="tabular font-medium">{{ $order->order_no }}</span> ({{ __('payment') }} {{ $payment->payment_no }}).</p>
        @if ($payment->mode->canBounce())
            <p class="text-xs text-slate-500">{{ __('Subject to realisation of the instrument.') }}</p>
        @endif
        @if ($receipt->cancelled_at)
            <p class="text-xs font-medium text-rose-700">{{ __('Cancelled on :date: :reason', ['date' => $receipt->cancelled_at->format('d M Y'), 'reason' => $receipt->cancellation_reason]) }}</p>
        @endif
    </section>

    <footer class="mt-12 flex justify-between text-xs text-slate-500">
        <p>{{ __('Issued by') }} {{ $receipt->issuer?->name }}</p>
        <p>{{ __('For') }} {{ $company?->name }}</p>
    </footer>
</main>
</body>
</html>
