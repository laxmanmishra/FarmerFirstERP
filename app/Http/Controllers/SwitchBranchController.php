<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Changes the user's working branch (top-bar selector) to one they may access.
 */
class SwitchBranchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $branchId = $request->validate(['branch_id' => ['required', 'integer']])['branch_id'];

        abort_unless($request->user()->canAccessBranch($branchId), 403);

        $request->user()->forceFill(['current_branch_id' => $branchId])->save();

        return back()->with('toast', ['type' => 'success', 'message' => __('Branch switched.')]);
    }
}
