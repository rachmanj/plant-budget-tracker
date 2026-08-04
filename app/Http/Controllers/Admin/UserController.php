<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::with('roles')->orderBy('name')->get()->map(function (User $user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee_no' => $user->employee_no,
                'division' => $user->division,
                'project_code_scope' => $user->project_code_scope,
                'is_active' => $user->is_active,
                'roles' => $user->roles->map(fn ($role) => [
                    'name' => $role->name,
                    'project_code' => $role->pivot->project_code ?? '',
                ]),
            ];
        });

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'roles' => Role::orderBy('name')->pluck('name'),
            'divisions' => ['plant', 'aml', 'procurement', 'finance', 'directorate', 'it'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'employee_no' => ['nullable', 'string', 'unique:users,employee_no'],
            'division' => ['nullable', 'string'],
            'project_code_scope' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ]);

        $data['password'] = Hash::make($data['password']);
        User::create($data);

        return back()->with('success', 'User berhasil dibuat.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'employee_no' => ['nullable', 'string', 'unique:users,employee_no,'.$user->id],
            'division' => ['nullable', 'string'],
            'project_code_scope' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ]);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return back()->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return back()->with('success', 'User berhasil dihapus.');
    }

    public function assignRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*.name' => ['required', 'string'],
            'roles.*.project_code' => ['nullable', 'string', 'max:20'],
        ]);

        $user->roles()->detach();

        foreach ($data['roles'] as $roleData) {
            $projectCode = $roleData['project_code'] ?? '';
            setPermissionsTeamId($projectCode);
            $user->assignRole($roleData['name']);
        }

        return back()->with('success', 'Role user berhasil diperbarui.');
    }
}
