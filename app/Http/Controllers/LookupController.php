<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Small JSON lookups for typeahead fields (co-owner picker). */
class LookupController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $users = User::query()
            ->active()
            ->with('profile')
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', $term))
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'meta' => $u->profile?->headline ?: trim(($u->profile?->section ? $u->profile->section.' Section' : '').' '.($u->profile?->country ?? '')),
                'initials' => $u->initials(),
            ]);

        return response()->json($users);
    }
}
