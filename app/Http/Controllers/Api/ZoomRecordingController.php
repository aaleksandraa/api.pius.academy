<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\ZoomRecording;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoomRecordingController extends Controller
{
    public function index(): JsonResponse
    {
        $recordings = ZoomRecording::orderByDesc('recorded_at')->get();

        return response()->json([
            'recordings' => $recordings->map(fn($r) => $this->formatRecording($r)),
        ]);
    }

    public function show(ZoomRecording $zoomRecording): JsonResponse
    {
        return response()->json([
            'recording' => $this->formatRecording($zoomRecording),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'vimeo_embed' => 'required|string',
            'recorded_at' => 'required|date',
        ]);

        $recording = ZoomRecording::create($validated);

        // Notify all users about new zoom recording
        Notification::notifyAll(
            'new_zoom',
            'Novi Zoom snimak',
            'Zoom snimak "' . $recording->title . '" je sada dostupan.',
            '/zoom',
            null,
            $request->user()->id
        );

        return response()->json([
            'message' => 'Snimak je uspješno kreiran.',
            'recording' => $this->formatRecording($recording),
        ], 201);
    }

    public function update(Request $request, ZoomRecording $zoomRecording): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'vimeo_embed' => 'required|string',
            'recorded_at' => 'required|date',
        ]);

        $zoomRecording->update($validated);

        return response()->json([
            'message' => 'Snimak je uspješno ažuriran.',
            'recording' => $this->formatRecording($zoomRecording),
        ]);
    }

    public function destroy(ZoomRecording $zoomRecording): JsonResponse
    {
        $zoomRecording->delete();

        return response()->json([
            'message' => 'Snimak je uspješno obrisan.',
        ]);
    }

    private function formatRecording(ZoomRecording $recording): array
    {
        return [
            'id' => $recording->id,
            'title' => $recording->title,
            'vimeo_embed' => $recording->vimeo_embed,
            'vimeo_id' => $recording->vimeo_id,
            'vimeo_hash' => $recording->vimeo_hash,
            'recorded_at' => $recording->recorded_at->format('Y-m-d'),
            'created_at' => $recording->created_at,
        ];
    }
}
