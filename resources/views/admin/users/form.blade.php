@extends('layouts.admin')
@section('title', $user->exists ? 'Edit user' : 'New user')
@section('heading', $user->exists ? 'Edit user' : 'New user')

@section('content')
    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="max-w-2xl">
        @csrf
        @if ($user->exists) @method('PUT') @endif

        <div class="card space-y-5 p-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="name">Name <span class="text-red-500">*</span></label>
                    <input id="name" name="name" value="{{ old('name', $user->name) }}" required class="input">
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <label class="label" for="email">Email <span class="text-red-500">*</span></label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="input">
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="password">{{ $user->exists ? 'New password' : 'Password' }} @unless($user->exists)<span class="text-red-500">*</span>@endunless</label>
                    <input id="password" name="password" type="password" @unless($user->exists) required @endunless class="input">
                    @if ($user->exists)<p class="mt-1 text-xs text-warm-gray">Leave blank to keep current password.</p>@endif
                    <x-input-error :messages="$errors->get('password')" class="mt-1" />
                </div>
                <div>
                    <label class="label" for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="input">
                </div>
            </div>

            <div>
                <p class="label">Usergroups</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($roles as $role)
                        <label class="flex items-center gap-2 rounded-lg border border-light-gray px-3 py-2">
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}" @checked(in_array($role->name, old('roles', $assigned))) class="rounded border-light-gray text-brand focus:ring-brand">
                            <span class="text-sm font-medium text-warmer-gray">{{ $role->name }}</span>
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('roles')" class="mt-1" />
            </div>

            <div class="border-t border-light-gray pt-5">
                <p class="label">Membership</p>
                <label class="flex items-start gap-3 rounded-lg border border-light-gray px-3 py-3">
                    <input type="checkbox" name="active_member" value="1" @checked(old('active_member', $user->active_member)) class="mt-0.5 rounded border-light-gray text-brand focus:ring-brand">
                    <span>
                        <span class="block text-sm font-medium text-warmer-gray">Active paid member</span>
                        <span class="block text-xs text-warm-gray">Tick if this person holds active membership on the external membership portal. A future portal sync will set this automatically. This is separate from the "Member" usergroup, which only grants a free account.</span>
                    </span>
                </label>
                <x-input-error :messages="$errors->get('active_member')" class="mt-1" />
            </div>

            <div class="flex gap-2 border-t border-light-gray pt-5">
                <button class="btn-primary">{{ $user->exists ? 'Update' : 'Create' }} user</button>
                <a href="{{ route('admin.users.index') }}" class="btn-ghost">Cancel</a>
            </div>
        </div>
    </form>
@endsection
