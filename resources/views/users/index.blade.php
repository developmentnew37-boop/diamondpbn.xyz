@extends('layouts.dashboard')
@section('title', 'Accounts')
@section('page-title', 'Accounts')

@section('content')
<div class="page-enter">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Accounts</h2>
            <p class="text-slate-500 mt-1">Create staff logins. Operators can only manage campaigns, batches, and WP batches they create.</p>
        </div>
        <a href="{{ route('users.create') }}" class="inline-flex items-center px-4 py-2 bg-orange-500 text-white rounded-lg text-sm font-medium hover:bg-orange-600 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Add account
        </a>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($users as $account)
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-6 py-4 font-medium text-slate-800">{{ $account->name }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $account->email }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-0.5 text-xs font-medium rounded-full {{ $account->isSuperAdmin() ? 'bg-orange-100 text-orange-700' : 'bg-slate-100 text-slate-600' }}">{{ $account->isSuperAdmin() ? 'Superadmin' : 'Operator' }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('users.edit', $account) }}" class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-orange-600" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    @if($account->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.destroy', $account) }}" class="inline" onsubmit="return confirm('Delete this account? Their campaigns, batches, and WP batches will also be deleted.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-2 rounded-lg text-slate-500 hover:bg-red-50 hover:text-red-600" title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-slate-500">No accounts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <div class="px-6 py-4 border-t border-slate-200">{{ $users->links() }}</div>
        @endif
    </div>
</div>
@endsection
