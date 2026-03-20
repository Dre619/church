<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function complete(Request $request): JsonResponse
    {
        $request->user()->update(['onboarding_completed_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->user()->update(['onboarding_completed_at' => null]);

        return response()->json(['ok' => true]);
    }
}
