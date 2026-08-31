@extends('layouts.dashboard')
@section('title', 'Add account')
@section('page-title', 'Add account')

@section('content')
<div class="page-enter max-w-xl">
    <a href="{{ route('users.index') }}" class="text-sm text-slate-500 hover:text-orange-600 mb-4 inline-block">← Back to Accounts</a>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h2 class="text-xl font-bold text-slate-800 mb-6">Create account</h2>
        <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
            @csrf
            <div>
                <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required class="w-full rounded-lg border-slate-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                @error('name')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="w-full rounded-lg border-slate-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                @error('email')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input id="password" name="password" type="password" required class="w-full rounded-lg border-slate-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                @error('password')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="w-full rounded-lg border-slate-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
            </div>
            <div>
                <label for="role" class="block text-sm font-medium text-slate-700 mb-1">Role</label>
                <select id="role" name="role" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                    <option value="operator" @selected(old('role', 'operator') === 'operator')>Operator — campaigns, batches, WP batches only</option>
                    <option value="superadmin" @selected(old('role') === 'superadmin')>Superadmin — full access</option>
                </select>
                @error('role')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="pt-2">
                <button type="submit" class="px-4 py-2 bg-orange-500 text-white rounded-lg font-medium hover:bg-orange-600">Create account</button>
            </div>
        </form>
    </div>
</div>
@endsection
