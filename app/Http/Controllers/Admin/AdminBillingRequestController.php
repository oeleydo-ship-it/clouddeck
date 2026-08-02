<?php

namespace App\Http\Controllers\Admin;

use App\Billing\Contracts\BillingGateway;
use App\Http\Controllers\Controller;
use App\Models\BillingRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminBillingRequestController extends Controller
{
    public function update(Request $request, BillingRequest $billingRequest, AuditLogger $audit, BillingGateway $billing): RedirectResponse
    {
        abort_unless($billingRequest->status === 'pending', 422);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'admin_note' => ['nullable', 'string', 'max:1000'], 'period_days' => ['nullable', 'integer', 'between:1,3660']]);
        DB::transaction(function () use ($request, $billingRequest, $billing, $data): void {
            if ($data['decision'] === 'approve') {
                $billing->activate($billingRequest, $data['period_days'] ?? ($billingRequest->billing_cycle === 'yearly' ? 365 : 30));
            }

            $billingRequest->update(['status' => $data['decision'] === 'approve' ? 'approved' : 'rejected', 'admin_note' => $data['admin_note'] ?? null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        });
        $audit->record($request, 'billing.reviewed', $billingRequest, ['status' => 'pending'], ['status' => $billingRequest->status, 'note' => $billingRequest->admin_note]);

        return back()->with('status', 'Billing request '.$billingRequest->status.'.');
    }
}
