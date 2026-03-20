<?php

namespace App\Http\Controllers;

use App\Models\OrganizationUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SwitchBranchController extends Controller
{
    public function __invoke(Request $request, int $orgId): RedirectResponse
    {
        // Ensure the authenticated user actually belongs to the target org
        abort_unless(
            OrganizationUser::where('user_id', $request->user()->id)
                ->where('organization_id', $orgId)
                ->exists(),
            403,
        );

        session(['current_org_id' => $orgId]);

        return redirect()->route('dashboard');
    }
}
