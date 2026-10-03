<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\StreamsCsv;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Endorsement;
use App\Models\HourLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    use StreamsCsv;

    /** Sort keys => column (approved_hours is a withSum alias). */
    public const SORTS = [
        'joined' => 'users.created_at',
        'last_login' => 'users.last_login_at',
        'name' => 'users.name',
        'hours' => 'approved_hours',
    ];

    public function index(Request $request)
    {
        $filters = $this->filters($request);

        return view('admin.users.index', [
            'users' => $this->query($filters)->paginate(25)->withQueryString(),
            'filters' => $filters,
            'totals' => [
                'all' => User::query()->count(),
                'admins' => User::query()->admins()->count(),
                'suspended' => User::query()->whereNotNull('suspended_at')->count(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $users = $this->query($this->filters($request));

        return $this->streamCsv('ieee-volunteering-users-'.now()->format('Y-m-d').'.csv', function ($row) use ($users) {
            $row(['ID', 'Name', 'Email', 'Role', 'Status', 'Region', 'Section', 'Country', 'Membership grade', 'Joined', 'Last login', 'Email verified', 'Applications', 'Approved hours']);

            foreach ($users->lazy(500) as $user) {
                $row([
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->role,
                    $user->isSuspended() ? 'suspended' : 'active',
                    $user->profile?->region,
                    $user->profile?->section,
                    $user->profile?->country,
                    $user->profile?->membership_grade,
                    $user->created_at?->toDateTimeString(),
                    $user->last_login_at?->toDateTimeString(),
                    $user->email_verified_at ? 'yes' : 'no',
                    $user->applications_count,
                    round((float) $user->approved_hours, 1),
                ]);
            }
        });
    }

    public function show(User $user)
    {
        $user->load(['profile', 'skills']);

        $applicationCounts = $user->applications()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $hours = $user->hourLogs()->selectRaw('status, SUM(hours) as total')->groupBy('status')->pluck('total', 'status');

        $owned = $user->ownedOpportunities()
            ->withCount(['applications', 'owners'])
            ->latest('opportunities.created_at')
            ->get();

        return view('admin.users.show', [
            'user' => $user,
            'completeness' => $user->profile?->completeness(),
            'applicationCounts' => $applicationCounts,
            'approvedHours' => round((float) ($hours[HourLog::APPROVED] ?? 0), 1),
            'pendingHours' => round((float) ($hours[HourLog::PENDING] ?? 0), 1),
            'applications' => $user->applications()->with('opportunity')->latest()->take(8)->get(),
            'owned' => $owned,
            'soleOwned' => $owned->filter(fn ($o) => $o->owners_count === 1)->count(),
            'endorsementsReceived' => $user->endorsementsReceived()->count(),
            'endorsementsGiven' => Endorsement::query()->where('endorser_id', $user->id)->count(),
            'activities' => $user->activities()->with('subject')->latest('created_at')->latest('id')->take(15)->get(),
            'guards' => $this->guards($user),
        ]);
    }

    public function role(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::in(array_keys(User::ROLES))]]);

        if ($data['role'] === $user->role) {
            return back()->with('status', $user->name.' is already '.Str::lower(User::ROLES[$data['role']]).'.');
        }

        if ($error = $this->guards($user)['role']) {
            return back()->withErrors(['role' => $error]);
        }

        $from = $user->role;
        $user->forceFill(['role' => $data['role']])->save();

        Activity::record('admin.role_changed', $user, [
            'name' => $user->name, 'email' => $user->email, 'from' => $from, 'to' => $user->role,
        ]);

        return back()->with('status', $user->name.' is now '.($user->isAdmin() ? 'an admin' : 'a regular user').'.');
    }

    /** Toggle suspension. Suspended users are signed out on their next request. */
    public function suspend(User $user): RedirectResponse
    {
        if ($user->isSuspended()) {
            $user->forceFill(['suspended_at' => null])->save();
            Activity::record('admin.user_unsuspended', $user, ['name' => $user->name, 'email' => $user->email]);

            return back()->with('status', $user->name.' has been reinstated and can sign in again.');
        }

        if ($error = $this->guards($user)['suspend']) {
            return back()->withErrors(['suspend' => $error]);
        }

        $user->forceFill(['suspended_at' => now()])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        Activity::record('admin.user_suspended', $user, ['name' => $user->name, 'email' => $user->email]);

        return back()->with('status', $user->name.' has been suspended and signed out.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($error = $this->guards($user)['delete']) {
            return back()->withErrors(['delete' => $error]);
        }

        $request->validate(
            ['confirm_email' => ['required', 'string', function ($attribute, $value, $fail) use ($user) {
                if (Str::lower(trim($value)) !== Str::lower($user->email)) {
                    $fail('Type the user’s email address exactly to confirm the deletion.');
                }
            }]],
        );

        $name = $user->name;

        DB::transaction(function () use ($user) {
            Activity::record('admin.user_deleted', $user, ['name' => $user->name, 'email' => $user->email]);

            if ($path = $user->profile?->avatar_path) {
                Storage::disk('public')->delete($path);
            }

            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        return redirect()->route('admin.users.index')->with('status', $name.'’s account and volunteer history were deleted.');
    }

    // ----- Helpers -------------------------------------------------------

    /**
     * Why each destructive action is blocked for $user (null = allowed):
     * admins can't act on themselves, and the last admin can't be removed.
     *
     * @return array{role: string|null, suspend: string|null, delete: string|null}
     */
    private function guards(User $user): array
    {
        $self = $user->is(auth()->user());
        $lastAdmin = $user->isAdmin() && User::query()->admins()->count() <= 1;
        $lastActiveAdmin = $user->isAdmin() && ! $user->isSuspended()
            && User::query()->admins()->active()->count() <= 1;

        return [
            'role' => match (true) {
                $self => 'You can’t change your own role — ask another admin.',
                $lastAdmin => 'This is the only admin. Promote someone else first.',
                default => null,
            },
            'suspend' => match (true) {
                $self => 'You can’t suspend your own account.',
                $lastActiveAdmin => 'This is the only active admin. Promote someone else first.',
                default => null,
            },
            'delete' => match (true) {
                $self => 'You can’t delete your own account from the admin panel.',
                $lastAdmin => 'This is the only admin. Promote someone else first.',
                default => null,
            },
        ];
    }

    /** Whitelisted filters and sort from the query string. */
    private function filters(Request $request): array
    {
        $pick = fn (string $key, array $allowed, ?string $default = null) => in_array($value = (string) $request->query($key, ''), $allowed, true) ? $value : $default;

        return [
            'q' => Str::limit(trim((string) $request->query('q', '')), 100, ''),
            'role' => $pick('role', array_keys(User::ROLES)),
            'status' => $pick('status', ['active', 'suspended']),
            'region' => $pick('region', array_keys(config('volunteering.regions'))),
            'sort' => $pick('sort', array_keys(self::SORTS), 'joined'),
            'dir' => $pick('dir', ['asc', 'desc'], 'desc'),
        ];
    }

    private function query(array $filters): Builder
    {
        return User::query()
            ->with('profile')
            ->withCount('applications')
            ->withSum(['hourLogs as approved_hours' => fn ($q) => $q->where('status', HourLog::APPROVED)], 'hours')
            ->when($filters['q'], fn ($query, $term) => $query->where(fn ($q) => $q
                ->where('users.name', 'like', '%'.$term.'%')
                ->orWhere('users.email', 'like', '%'.$term.'%')))
            ->when($filters['role'], fn ($q, $role) => $q->where('users.role', $role))
            ->when($filters['status'] === 'active', fn ($q) => $q->whereNull('users.suspended_at'))
            ->when($filters['status'] === 'suspended', fn ($q) => $q->whereNotNull('users.suspended_at'))
            ->when($filters['region'], fn ($q, $region) => $q->whereHas('profile', fn ($p) => $p->where('region', $region)))
            ->orderBy(self::SORTS[$filters['sort']], $filters['dir'])
            ->orderBy('users.id', $filters['dir']);
    }
}
