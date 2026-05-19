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
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'ADMIN_KEUANGAN',
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
        ];

        // Password is optional on update
        if ($request->filled('password')) {
            $rules['password'] = ['confirmed', Password::min(8)->mixedCase()->numbers()];
        }

        $request->validate($rules);

        $user->name = $request->name;
        $user->email = $request->email;

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
