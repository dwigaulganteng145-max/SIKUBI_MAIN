<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BankAccountController extends Controller
{
    public function index()
    {
        return Inertia::render('Accounts', [
            'accounts' => BankAccount::withCount('transactions')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'account_alias' => 'nullable|string|max:100',
            'currency' => 'nullable|string|max:10',
        ]);

        BankAccount::create($request->only(['bank_name', 'account_number', 'account_alias', 'currency']));
        return back();
    }

    public function destroy(BankAccount $account)
    {
        $account->delete();
        return back();
    }

    public function update(Request $request, BankAccount $account)
    {
        $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'account_alias' => 'nullable|string|max:100',
            'currency' => 'nullable|string|max:10',
        ]);

        $account->update($request->only(['bank_name', 'account_number', 'account_alias', 'currency']));
        return back();
    }
}
