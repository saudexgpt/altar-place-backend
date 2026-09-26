<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;

class PlatformStatusController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'maintenance_mode' => (bool) PlatformSetting::get('maintenance_mode', false),
            'maintenance_message' => PlatformSetting::get('maintenance_message'),
        ]);
    }
}
