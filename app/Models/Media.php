<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'user_id', 'name', 'file_name', 'path', 'mime_type', 'size', 'disk',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Public URL to the stored file. */
    public function url(): string
    {
        return Storage::disk($this->disk ?: 'public')->url($this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /** Human-readable file size, e.g. "76.6 KB". */
    public function humanSize(): string
    {
        $bytes = (int) $this->size;
        if ($bytes <= 0) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), 1).' '.$units[$power];
    }

    /** Remove the underlying file when the record is deleted. */
    protected static function booted(): void
    {
        static::deleting(function (Media $media) {
            $disk = Storage::disk($media->disk ?: 'public');
            if ($media->path && $disk->exists($media->path)) {
                $disk->delete($media->path);
            }
        });
    }
}
