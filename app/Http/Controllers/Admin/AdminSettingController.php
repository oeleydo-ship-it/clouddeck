<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminSettingController extends Controller
{
    public function update(Request $request, AuditLogger $audit, SystemSettings $settings): RedirectResponse
    {
        $data = $request->validate(['support_email' => ['nullable', 'email'], 'registration_enabled' => ['sometimes', 'boolean'], 'email_verification_required' => ['sometimes', 'boolean'], 'maintenance_banner' => ['nullable', 'string', 'max:500']]);
        foreach (['support_email' => 'string', 'registration_enabled' => 'boolean', 'email_verification_required' => 'boolean', 'maintenance_banner' => 'string'] as $key => $type) {
            $value = $type === 'boolean' ? ($request->boolean($key) ? '1' : '0') : ($data[$key] ?? '');
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $value, 'type' => $type, 'is_public' => true]);
            $settings->forget($key);
        }
        $audit->record($request, 'settings.updated', null, [], ['keys' => array_keys($data)]);

        return back()->with('status', 'System settings updated.');
    }
}
