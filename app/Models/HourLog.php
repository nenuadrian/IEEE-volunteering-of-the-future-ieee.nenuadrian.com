<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hours a volunteer reports against an opportunity; owners approve them. */
class HourLog extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $fillable = [
        'application_id', 'user_id', 'opportunity_id', 'worked_on', 'hours', 'description',
        'status', 'reviewed_by', 'reviewed_at', 'created_at', 'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'worked_on' => 'date',
            'hours' => 'float',
            'reviewed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class)->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
