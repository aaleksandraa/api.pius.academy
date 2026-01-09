<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Notification;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MaterialController extends Controller
{
    public function index(): JsonResponse
    {
        $materials = Material::active()->ordered()->get();

        return response()->json([
            'materials' => $materials->map(fn($m) => $this->formatMaterial($m)),
            'enabled' => Setting::getBool('materials_enabled', true),
        ]);
    }

    // Admin methods
    public function adminIndex(): JsonResponse
    {
        $materials = Material::ordered()->get();

        return response()->json([
            'materials' => $materials->map(fn($m) => $this->formatMaterial($m)),
            'enabled' => Setting::getBool('materials_enabled', true),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:10240',
            'file' => 'nullable|file|max:51200', // 50MB max
        ]);

        $imageUrl = null;
        $fileUrl = null;
        $fileName = null;

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('materials/images', 'public');
            $imageUrl = Storage::disk('public')->url($path);
        }

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();
            $path = $file->store('materials/files', 'public');
            $fileUrl = Storage::disk('public')->url($path);
        }

        $material = Material::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'image_url' => $imageUrl,
            'file_url' => $fileUrl,
            'file_name' => $fileName,
            'sort_order' => Material::max('sort_order') + 1,
        ]);

        // Notify all users about new material (materials are active by default)
        if ($material->is_active && Setting::getBool('materials_enabled', true)) {
            Notification::notifyAll(
                'new_material',
                'Novi materijal',
                'Materijal "' . $material->title . '" je sada dostupan.',
                '/materials',
                null,
                $request->user()->id
            );
        }

        return response()->json([
            'message' => 'Materijal je uspješno dodan.',
            'material' => $this->formatMaterial($material),
        ], 201);
    }

    public function update(Request $request, Material $material): JsonResponse
    {
        $wasActive = $material->is_active;

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:10240',
            'file' => 'nullable|file|max:51200',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('materials/images', 'public');
            $material->image_url = Storage::disk('public')->url($path);
        }

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $material->file_name = $file->getClientOriginalName();
            $path = $file->store('materials/files', 'public');
            $material->file_url = Storage::disk('public')->url($path);
        }

        $material->title = $validated['title'];
        $material->description = $validated['description'] ?? $material->description;
        
        if (isset($validated['is_active'])) {
            $material->is_active = $validated['is_active'];
        }

        $material->save();

        // Notify all users if material just became active
        if (!$wasActive && $material->is_active && Setting::getBool('materials_enabled', true)) {
            Notification::notifyAll(
                'new_material',
                'Novi materijal',
                'Materijal "' . $material->title . '" je sada dostupan.',
                '/materials',
                null,
                $request->user()->id
            );
        }

        return response()->json([
            'message' => 'Materijal je uspješno ažuriran.',
            'material' => $this->formatMaterial($material),
        ]);
    }

    public function destroy(Material $material): JsonResponse
    {
        $material->delete();

        return response()->json([
            'message' => 'Materijal je uspješno obrisan.',
        ]);
    }

    public function toggleEnabled(): JsonResponse
    {
        $current = Setting::getBool('materials_enabled', true);
        Setting::set('materials_enabled', !$current ? '1' : '0');

        return response()->json([
            'message' => !$current ? 'Stranica Materijali je omogućena.' : 'Stranica Materijali je onemogućena.',
            'enabled' => !$current,
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:materials,id',
        ]);

        foreach ($validated['ids'] as $index => $id) {
            Material::where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json([
            'message' => 'Redoslijed je uspješno ažuriran.',
        ]);
    }

    public function checkEnabled(): JsonResponse
    {
        return response()->json([
            'enabled' => Setting::getBool('materials_enabled', true),
        ]);
    }

    private function formatMaterial(Material $material): array
    {
        return [
            'id' => $material->id,
            'title' => $material->title,
            'description' => $material->description,
            'image_url' => $material->image_url,
            'file_url' => $material->file_url,
            'file_name' => $material->file_name,
            'is_active' => $material->is_active,
            'sort_order' => $material->sort_order,
            'created_at' => $material->created_at,
        ];
    }
}

