<?php

namespace App\Http\Controllers;

use App\Models\AccountFile;
use App\Models\Company;
use App\Models\Receipt;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Printable customer receipt, rendered from the stored receipt and payment (SRS §99).
 * A cancelled receipt prints with a CANCELLED mark.
 */
class ReceiptPrintController extends Controller
{
    public function __invoke(Request $request, Receipt $receipt): View
    {
        abort_unless($request->user()->can('accounts.view'), 403);
        abort_unless(AccountFile::query()->visibleTo($request->user())->whereKey($receipt->account_file_id)->exists(), 404);

        return view('print.receipt', [
            'receipt' => $receipt->load(['payment', 'issuer:id,name', 'accountFile.branch', 'accountFile.order.customer.village.tehsil.district']),
            'company' => Company::current(),
        ]);
    }
}
