<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('dashboard.admin.users.index', compact('users'));
    }

    public function edit(User $user)
    {
        $roles = Role::cases();
        $statuses = UserStatus::cases();

        return view('dashboard.admin.users.edit', compact('user', 'roles', 'statuses'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'role' => ['required', Rule::enum(Role::class)],
            'status' => ['required', Rule::enum(UserStatus::class)],
        ]);

        $requestedRole = Role::from($request->input('role'));
        $requestedStatus = UserStatus::from($request->input('status'));

        $user->update([
            'role' => $requestedRole,
            'status' => $requestedStatus,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Dane użytkownika zostały zaktualizowane.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->is($user)) {
            return redirect()
                ->route('admin.users.index')
                ->withErrors(['user' => 'Nie możesz usunąć własnego konta.']);
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Użytkownik został usunięty.');
    }
}
