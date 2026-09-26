<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPlatformSettingsController extends Controller
{
    public function updateMaintenance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        PlatformSetting::set('maintenance_mode', $validated['enabled']);
        PlatformSetting::set('maintenance_message', $validated['message'] ?? null);

        return response()->json([
            'maintenance_mode' => $validated['enabled'],
            'maintenance_message' => $validated['message'] ?? null,
        ]);
    }
}
