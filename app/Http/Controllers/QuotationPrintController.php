<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Quotation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Printable quotation (use the browser's "Save as PDF"). Rendered from the stored
 * quotation, never from form input (SRS v6.1 §5).
 */
class QuotationPrintController extends Controller
{
    public function __invoke(Request $request, Quotation $quotation): View
    {
        abort_unless($request->user()->can('quotations.view'), 403);
        abort_unless(Quotation::query()->visibleTo($request->user())->whereKey($quotation->id)->exists(), 404);

        return view('print.quotation', [
            'quotation' => $quotation->load(['items', 'enquiry.farmer.village.tehsil.district', 'branch', 'preparedBy']),
            'company' => Company::current(),
        ]);
    }
}
