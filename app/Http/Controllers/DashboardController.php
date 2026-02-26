<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use App\Models\TransactionType;
use App\Models\Bank;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // 1. GENEL KASA TOPLAMLARI (Artık Blade'de en üste koyacağız)
        $totals = BankAccount::selectRaw('currency, SUM(balance) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency');
        
        // 2. SOL MENÜ VE FİLTRELER
        $banks = Bank::with('bankAccounts')->orderBy('bank_name')->get(); 
        $transactionTypes = TransactionType::orderBy('name')->get(); 

        // 3. SEÇİLİ BANKA VE ANA SORGU
        $activeBank = null;
        $activeBankSummary = [];
        $activeBankAccounts = [];

        if ($request->filled('bank_id')) {
            $activeBank = Bank::with('bankAccounts')->find($request->bank_id);
            
            if ($activeBank) {
                $query = $activeBank->transactions()->with(['bankAccount.bank', 'transactionType']);
                $activeBankAccounts = $activeBank->bankAccounts;
                $activeBankSummary = $activeBank->bankAccounts->groupBy('currency')->map(function ($accounts) {
                    return [
                        'count' => $accounts->count(),
                        'total' => $accounts->sum('balance')
                    ];
                });
            } else {
                $query = Transaction::with(['bankAccount.bank', 'transactionType']); 
            }
        } else {
            $query = Transaction::with(['bankAccount.bank', 'transactionType']); 
        }

        // ========================================================================
        // 4. DİĞER DİNAMİK FİLTRELER (YENİ: Hesap Filtresi Eklendi)
        // ========================================================================
        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('currency')) {
            $query->whereHas('bankAccount', function ($q) use ($request) {
                $q->where('currency', $request->currency);
            });
        }
        if ($request->filled('type_code')) {
            $query->where('transaction_type_code', $request->type_code);
        }
        // YENİ EKLENEN KISIM: Kullanıcı "Hesap No: 6" gibi bir hesaba tıkladıysa:
        if ($request->filled('account_id')) {
            $query->where('bank_account_id', $request->account_id);
        }

        // ========================================================================
        // 4.5. DİNAMİK GELİR/GİDER HESABI (Bağımsız Kurye)
        // ========================================================================
        $summaryQuery = Transaction::join('bank_accounts', 'transactions.bank_account_id', '=', 'bank_accounts.id');

        if ($request->filled('bank_id')) {
            $summaryQuery->where('bank_accounts.bank_id', $request->bank_id);
        }
        if ($request->filled('search')) {
            $summaryQuery->where('transactions.description', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('currency')) {
            $summaryQuery->where('bank_accounts.currency', $request->currency);
        }
        if ($request->filled('type_code')) {
            $summaryQuery->where('transactions.transaction_type_code', $request->type_code);
        }
        // YENİ EKLENEN KISIM: Hesap makinesi de sadece o hesabın parasını saysın
        if ($request->filled('account_id')) {
            $summaryQuery->where('transactions.bank_account_id', $request->account_id);
        }

        $filteredSummaries = $summaryQuery
            ->selectRaw('bank_accounts.currency, 
                         SUM(CASE WHEN transactions.amount > 0 THEN transactions.amount ELSE 0 END) as total_income,
                         SUM(CASE WHEN transactions.amount < 0 THEN transactions.amount ELSE 0 END) as total_expense')
            ->groupBy('bank_accounts.currency')
            ->get();

        // 5. SAYFALAMA
        $transactions = $query->orderBy('transaction_date', 'desc')
                              ->paginate(20)
                              ->withQueryString();

        return view('dashboard', compact(
            'transactions', 'totals', 'transactionTypes', 'banks', 
            'filteredSummaries', 'activeBank', 'activeBankSummary', 'activeBankAccounts'
        ));
    }
}