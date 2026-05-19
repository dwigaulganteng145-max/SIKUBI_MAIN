<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\ClassificationRule;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function categories(Request $request)
    {
        $accountId = $request->input('account_id');

        // Only show accounts that actually have imported transactions
        $accounts = BankAccount::whereHas('transactions')->get();

        // If no accounts have data, show empty state
        if ($accounts->isEmpty()) {
            return Inertia::render('Settings/Categories', [
                'categories' => collect([]),
                'accounts' => $accounts,
                'filters' => ['account_id' => $accountId],
                'hasData' => false,
            ]);
        }

        // If no filter selected, default to first account with data
        $effectiveAccountId = $accountId ?: $accounts->first()?->id;

        $query = Category::withCount('transactions', 'classificationRules')
            ->with('bankAccount:id,bank_name,account_alias');

        if ($effectiveAccountId) {
            $query->where(function ($q) use ($effectiveAccountId) {
                $q->where('bank_account_id', $effectiveAccountId)
                  ->orWhereNull('bank_account_id');
            });
        }

        return Inertia::render('Settings/Categories', [
            'categories' => $query->orderBy('type')->orderBy('sort_order')->get(),
            'accounts' => $accounts,
            'filters' => ['account_id' => $effectiveAccountId],
            'hasData' => true,
        ]);
    }

    public function storeCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:DEBIT,CREDIT',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:50',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
        ]);

        Category::create($request->only(['name', 'type', 'color', 'icon', 'bank_account_id']));
        return back();
    }

    public function destroyCategory(Category $category)
    {
        $category->delete();
        return back();
    }

    public function rules(Request $request)
    {
        $accountId = $request->input('account_id');

        // Only show accounts that actually have imported transactions
        $accounts = BankAccount::whereHas('transactions')->get();

        // If no accounts have data, show empty state
        if ($accounts->isEmpty()) {
            return Inertia::render('Settings/Rules', [
                'rules' => collect([]),
                'categories' => collect([]),
                'accounts' => $accounts,
                'filters' => ['account_id' => $accountId],
                'hasData' => false,
            ]);
        }

        // If no filter selected, default to first account with data
        $effectiveAccountId = $accountId ?: $accounts->first()?->id;

        $query = ClassificationRule::with('category:id,name,type,color', 'bankAccount:id,bank_name,account_alias');

        if ($effectiveAccountId) {
            $query->where(function ($q) use ($effectiveAccountId) {
                $q->where('bank_account_id', $effectiveAccountId)
                  ->orWhereNull('bank_account_id');
            });
        }

        // Categories also filtered by selected bank
        $catQuery = Category::orderBy('type')->orderBy('name');
        if ($effectiveAccountId) {
            $catQuery->where(function ($q) use ($effectiveAccountId) {
                $q->where('bank_account_id', $effectiveAccountId)
                  ->orWhereNull('bank_account_id');
            });
        }

        return Inertia::render('Settings/Rules', [
            'rules' => $query->orderBy('priority')->get(),
            'categories' => $catQuery->get(),
            'accounts' => $accounts,
            'filters' => ['account_id' => $effectiveAccountId],
            'hasData' => true,
        ]);
    }

    public function storeRule(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'pattern' => 'required|string|max:255',
            'match_type' => 'required|in:EXACT,CONTAINS,REGEX',
            'priority' => 'nullable|integer|min:1',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
        ]);

        ClassificationRule::create($request->only(['category_id', 'pattern', 'match_type', 'priority', 'bank_account_id']));
        return back();
    }

    public function destroyRule(ClassificationRule $rule)
    {
        $rule->delete();
        return back();
    }

    /**
     * Approve a suggested category (convert to regular).
     */
    public function approveCategory(Category $category)
    {
        $category->update([
            'is_suggested' => false,
        ]);
        return back();
    }
}
