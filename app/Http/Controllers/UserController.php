<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()->orderBy('name')->paginate(25);

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        return view('users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in(['superadmin', 'operator'])],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return redirect()->route('users.index')->with('success', 'Account created.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in(['superadmin', 'operator'])],
        ]);

        if ($user->isSuperAdmin() && $validated['role'] === 'operator' && User::superAdminCount() <= 1) {
            return back()->with('error', 'Cannot demote the last Superadmin.')->withInput();
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];
        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        return redirect()->route('users.index')->with('success', 'Account updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ((int) $user->id === Workspace::ownerId()) {
            return back()->with('error', 'Cannot delete the Superadmin who owns shared domains, target domains, and WP sites.');
        }

        if ($user->isSuperAdmin() && User::superAdminCount() <= 1) {
            return back()->with('error', 'Cannot delete the last Superadmin.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Account deleted.');
    }
}
