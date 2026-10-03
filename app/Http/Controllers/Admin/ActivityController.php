<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Application;
use App\Models\Endorsement;
use App\Models\HourLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/** Audit log over the append-only activity stream. */
class ActivityController extends Controller
{
    /** Filter groups => activity type prefixes. */
    public const GROUPS = [
        'admin' => ['label' => 'Admin actions', 'prefixes' => ['admin.']],
        'opportunities' => ['label' => 'Opportunities', 'prefixes' => ['opportunity.']],
        'applications' => ['label' => 'Applications & endorsements', 'prefixes' => ['application.', 'endorsement.']],
        'hours' => ['label' => 'Hours', 'prefixes' => ['hours.']],
        'users' => ['label' => 'Users & profiles', 'prefixes' => ['user.', 'profile.']],
    ];

    public function index(Request $request)
    {
        $filters = [
            'group' => array_key_exists((string) $request->query('group'), self::GROUPS) ? (string) $request->query('group') : null,
            'actor' => Str::limit(trim((string) $request->query('actor', '')), 100, ''),
            'from' => $this->date($request->query('from')),
            'to' => $this->date($request->query('to')),
        ];

        $activities = Activity::query()
            ->with([
                'user.profile',
                'subject' => fn (MorphTo $morph) => $morph->morphWith([
                    Application::class => ['opportunity'],
                    HourLog::class => ['opportunity'],
                    Endorsement::class => ['opportunity'],
                    User::class => ['profile'],
                ]),
            ])
            ->when($filters['group'], fn ($query, $group) => $query->where(function ($q) use ($group) {
                foreach (self::GROUPS[$group]['prefixes'] as $prefix) {
                    $q->orWhere('type', 'like', $prefix.'%');
                }
            }))
            ->when($filters['actor'], fn ($query, $actor) => $query->whereHas('user', fn ($u) => $u
                ->where('name', 'like', '%'.$actor.'%')
                ->orWhere('email', 'like', '%'.$actor.'%')))
            ->when($filters['from'], fn ($q, $from) => $q->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($filters['to'], fn ($q, $to) => $q->where('created_at', '<=', $to->copy()->endOfDay()))
            ->latest('created_at')
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        return view('admin.activity.index', [
            'activities' => $activities,
            'filters' => $filters,
        ]);
    }

    /** A Y-m-d query value as a date, or null when absent or malformed. */
    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }
    }
}
