<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EditorUploadController extends Controller
{
    /**
     * Handle EditorJS image-tool uploads (byFile endpoint).
     *
     * The tool POSTs multipart/form-data with the file under `image` and only
     * inspects the `success` flag, so failures return 200 with success: 0.
     */
    public function image(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);

        try {
            $request->validate([
                // No SVG: it is an XSS vector and not needed for content images.
                'image' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:8192'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => 0], 200);
        }

        $path = $request->file('image')->store('editor', 'public');

        return response()->json([
            'success' => 1,
            'file' => [
                'url' => \Illuminate\Support\Facades\Storage::disk('public')->url($path),
            ],
        ]);
    }
}
