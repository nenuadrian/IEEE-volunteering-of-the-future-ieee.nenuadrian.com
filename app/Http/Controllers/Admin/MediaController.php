<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    /**
     * File extensions allowed in the library. SVG is deliberately excluded
     * because it can carry embedded scripts (stored-XSS vector).
     */
    private const ALLOWED = 'pdf,jpg,jpeg,png,gif,webp,doc,docx,ppt,pptx,xls,xlsx,csv,txt,md,zip';

    public function index()
    {
        $media = Media::with('uploader')->latest()->get();

        return view('admin.media.index', compact('media'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:'.self::ALLOWED, 'max:20480'], // 20 MB
            'name' => ['nullable', 'string', 'max:150'],
        ]);

        $file = $request->file('file');
        $original = $file->getClientOriginalName();

        // Stable, collision-proof, human-readable stored name.
        $base = Str::slug(pathinfo($original, PATHINFO_FILENAME)) ?: 'file';
        $fileName = $base.'-'.Str::lower(Str::random(6)).'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('media', $fileName, 'public');

        Media::create([
            'user_id' => $request->user()?->id,
            'name' => $data['name'] ?: $original,
            'file_name' => $fileName,
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'disk' => 'public',
        ]);

        return back()->with('status', 'File uploaded.');
    }

    public function destroy(Media $medium)
    {
        $medium->delete(); // model event removes the underlying file

        return back()->with('status', 'File deleted.');
    }
}
