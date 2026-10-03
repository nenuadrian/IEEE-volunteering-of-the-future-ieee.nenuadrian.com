@extends('layouts.admin')
@section('title', 'Users')
@section('heading', 'Users')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <form method="GET" class="flex gap-2">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name or email…" class="input">
            <button class="btn-secondary">Search</button>
        </form>
        <a href="{{ route('admin.users.create') }}" class="btn-primary">+ New user</a>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-warm-white font-ui text-xs uppercase tracking-wide text-warm-gray">
                <tr><th class="px-6 py-3">Name</th><th class="px-6 py-3">Email</th><th class="px-6 py-3">Usergroups</th><th class="px-6 py-3 text-right">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-light-gray">
                @foreach ($users as $user)
                    <tr>
                        <td class="px-6 py-4 font-medium text-ink">{{ $user->name }}</td>
                        <td class="px-6 py-4 text-warm-gray">{{ $user->email }}</td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-1">
                                @forelse ($user->roles as $role)<span class="chip">{{ $role->name }}</span>@empty <span class="text-warm-gray">-</span>@endforelse
                                @if ($user->active_member)<span class="chip bg-accent-green-light/50 text-accent-green-dark" title="Active paid member">★ Member</span>@endif
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.users.edit', $user) }}" class="font-ui text-sm font-medium text-accent-blue hover:text-accent-blue-dark">Edit</a>
                                @if ($user->id !== auth()->id())
                                    <x-admin.delete :action="route('admin.users.destroy', $user)" confirm="Delete this user?" />
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $users->links() }}</div>
@endsection
