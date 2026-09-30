@php use App\Support\Money; $farmer = $quotation->enquiry->farmer; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('Quotation') }} {{ $quotation->reference() }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 14mm; }
        @media print { .no-print { display: none !important; } body { background: #fff; } }
    </style>
</head>
<body class="bg-slate-100 text-slate-800">
<div class="no-print mx-auto flex max-w-3xl justify-end gap-2 px-4 pt-6">
    <button onclick="window.print()" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white">{{ __('Print / Save as PDF') }}</button>
</div>
<main class="mx-auto my-6 max-w-3xl bg-white p-10 shadow-sm print:my-0 print:p-0 print:shadow-none">
    <header class="flex items-start justify-between border-b-2 border-brand-700 pb-5">
        <div>
            <h1 class="text-2xl font-bold text-brand-900">{{ $company?->name }}</h1>
            <p class="text-xs text-slate-500">{{ $company?->legal_name }}</p>
            <p class="mt-1 max-w-xs whitespace-pre-line text-xs text-slate-600">{{ $quotation->branch->address ?? $company?->address }}</p>
            <p class="text-xs text-slate-600">{{ collect([$quotation->branch->phone ?? $company?->phone, $company?->email])->filter()->implode(' · ') }}</p>
            @if ($company?->gstin)<p class="text-xs text-slate-600">GSTIN {{ $company->gstin }}</p>@endif
        </div>
        <div class="text-right">
            <p class="text-lg font-semibold uppercase tracking-wide text-slate-700">{{ __('Quotation') }}</p>
            <p class="tabular text-sm font-medium">{{ $quotation->reference() }}</p>
            <p class="text-xs text-slate-500">{{ __('Date') }}: {{ ($quotation->issued_at ?? $quotation->created_at)->format('d M Y') }}</p>
            <p class="text-xs text-slate-500">{{ __('Valid until') }}: {{ $quotation->valid_until->format('d M Y') }}</p>
        </div>
    </header>

    <section class="mt-5 grid grid-cols-2 gap-6 text-sm">
        <div>
            <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Customer') }}</p>
            <p class="font-medium">{{ $farmer->name }}</p>
            @if ($farmer->father_name)<p class="text-slate-600">{{ __('S/o or W/o') }} {{ $farmer->father_name }}</p>@endif
            <p class="text-slate-600">{{ $farmer->locationLabel() }}</p>
            <p class="tabular text-slate-600">{{ $farmer->mobile }}</p>
        </div>
        <div class="text-right">
            <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Sales executive') }}</p>
            <p>{{ $quotation->preparedBy?->name }}</p>
            <p class="text-slate-600">{{ __('Enquiry') }} {{ $quotation->enquiry->enquiry_no }}</p>
        </div>
    </section>

    <table class="mt-6 w-full text-sm">
        <thead>
            <tr class="border-b border-slate-300 text-left text-xs uppercase text-slate-500">
                <th class="py-2">#</th><th class="py-2">{{ __('Description') }}</th><th class="py-2 text-right">{{ __('Qty') }}</th>
                <th class="py-2 text-right">{{ __('Rate') }}</th><th class="py-2 text-right">{{ __('Discount') }}</th><th class="py-2 text-right">{{ __('Tax') }}</th><th class="py-2 text-right">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quotation->items as $item)
                <tr class="border-b border-slate-100">
                    <td class="py-2 text-slate-500">{{ $loop->iteration }}</td>
                    <td class="py-2">{{ $item->description }}</td>
                    <td class="tabular py-2 text-right">{{ $item->quantity }}</td>
                    <td class="tabular py-2 text-right">{{ Money::format($item->unit_price) }}</td>
                    <td class="tabular py-2 text-right">{{ Money::compare($item->discount_amount, 0) > 0 ? Money::format($item->discount_amount) : '—' }}</td>
                    <td class="tabular py-2 text-right">{{ $item->tax_percent }}%</td>
                    <td class="tabular py-2 text-right">{{ Money::format($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <section class="mt-4 flex justify-end">
        <dl class="w-72 space-y-1 text-sm">
            <div class="flex justify-between"><dt>{{ __('Total') }}</dt><dd class="tabular">{{ Money::format(Money::add($quotation->items_total, $quotation->charges_total)) }}</dd></div>
            @if (Money::compare($quotation->exchange_value, 0) > 0)
                <div class="flex justify-between"><dt>{{ __('Less: exchange') }}</dt><dd class="tabular">−{{ Money::format($quotation->exchange_value) }}</dd></div>
            @endif
            <div class="flex justify-between border-t border-slate-300 pt-1 text-base font-bold"><dt>{{ __('Net payable') }}</dt><dd class="tabular">{{ Money::format($quotation->net_amount) }}</dd></div>
            @if (Money::compare($quotation->finance_amount, 0) > 0)
                <div class="flex justify-between text-slate-600"><dt>{{ __('Finance (subject to approval)') }}</dt><dd class="tabular">{{ Money::format($quotation->finance_amount) }}</dd></div>
                <div class="flex justify-between font-medium"><dt>{{ __('Customer contribution') }}</dt><dd class="tabular">{{ Money::format($quotation->customer_contribution) }}</dd></div>
            @endif
        </dl>
    </section>

    @if ($quotation->terms)
        <section class="mt-8 text-xs text-slate-600">
            <p class="font-semibold uppercase text-slate-500">{{ __('Terms & conditions') }}</p>
            <p class="mt-1 whitespace-pre-line">{{ $quotation->terms }}</p>
        </section>
    @endif

    <footer class="mt-14 flex justify-between text-xs text-slate-500">
        <p>{{ __('Customer signature') }}</p>
        <p>{{ __('For') }} {{ $company?->name }}</p>
    </footer>
</main>
</body>
</html>
