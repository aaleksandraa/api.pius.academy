<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PushNotificationController extends Controller
{
    /**
     * Store or update push token for the authenticated user
     */
    public function storeToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'platform' => 'required|in:android,ios,web',
        ]);

        // Deactivate any existing tokens with the same value (in case user logged out and back in)
        PushToken::where('token', $validated['token'])
            ->where('user_id', '!=', $request->user()->id)
            ->update(['is_active' => false]);

        // Create or update token for current user
        PushToken::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'token' => $validated['token'],
            ],
            [
                'platform' => $validated['platform'],
                'is_active' => true,
            ]
        );

        Log::info('Push token saved for user ' . $request->user()->id . ': ' . substr($validated['token'], 0, 20) . '...');

        return response()->json([
            'message' => 'Push token saved successfully.',
        ]);
    }

    /**
     * Remove push token (on logout)
     */
    public function removeToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        PushToken::where('user_id', $request->user()->id)
            ->where('token', $validated['token'])
            ->update(['is_active' => false]);

        return response()->json([
            'message' => 'Push token removed successfully.',
        ]);
    }
}
