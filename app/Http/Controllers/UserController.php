<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('Users', [
            'users' => User::where('role', 'ADMIN_KEUANGAN')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role,
                    'last_login_at' => $u->last_login_at,
                    'created_at' => $u->created_at,
                    'can_import' => $u->can_import,
                    'can_manage_accounts' => $u->can_manage_accounts,
                    'can_manage_settings' => $u->can_manage_settings,
                    'can_detect_anomalies' => $u->can_detect_anomalies,
                    'can_edit_transactions' => $u->can_edit_transactions,
                    'can_manage_cash_transactions' => $u->can_manage_cash_transactions,
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'can_import' => 'boolean',
            'can_manage_accounts' => 'boolean',
            'can_manage_settings' => 'boolean',
            'can_detect_anomalies' => 'boolean',
            'can_edit_transactions' => 'boolean',
            'can_manage_cash_transactions' => 'boolean',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'ADMIN_KEUANGAN',
            'can_import' => $request->input('can_import', true),
            'can_manage_accounts' => $request->input('can_manage_accounts', true),
            'can_manage_settings' => $request->input('can_manage_settings', true),
            'can_detect_anomalies' => $request->input('can_detect_anomalies', true),
            'can_edit_transactions' => $request->input('can_edit_transactions', true),
            'can_manage_cash_transactions' => $request->input('can_manage_cash_transactions', true),
        ]);

        return back();
    }

    public function update(Request $request, User $user)
    {
        if ($user->role === 'DIREKTUR') {
            abort(403, 'Tidak dapat mengedit akun Direktur.');
        }

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'can_import' => 'boolean',
            'can_manage_accounts' => 'boolean',
            'can_manage_settings' => 'boolean',
            'can_detect_anomalies' => 'boolean',
            'can_edit_transactions' => 'boolean',
            'can_manage_cash_transactions' => 'boolean',
        ];

        // Password is optional on update
        if ($request->filled('password')) {
            $rules['password'] = ['confirmed', Password::min(8)->mixedCase()->numbers()];
        }

        $request->validate($rules);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->can_import = $request->boolean('can_import', true);
        $user->can_manage_accounts = $request->boolean('can_manage_accounts', true);
        $user->can_manage_settings = $request->boolean('can_manage_settings', true);
        $user->can_detect_anomalies = $request->boolean('can_detect_anomalies', true);
        $user->can_edit_transactions = $request->boolean('can_edit_transactions', true);
        $user->can_manage_cash_transactions = $request->boolean('can_manage_cash_transactions', true);

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return back();
    }

    public function destroy(User $user)
    {
        if ($user->role === 'DIREKTUR') {
            abort(403, 'Tidak dapat menghapus akun Direktur.');
        }

        $user->delete();
        return back();
    }
}
