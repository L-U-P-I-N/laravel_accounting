<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\Customer;
use App\Models\Employee;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        
        if (!$user->company_id) {
            return redirect()->route('setup.company');
        }

        $company = $user->company;
        $today = now();
        $monthStart = $today->copy()->startOfMonth();

        // Statistics
        $stats = [
            'total_revenue_month' => Invoice::where('company_id', $company->id)
                ->whereIn('status', ['sent', 'partial', 'paid'])
                ->where('invoice_date', '>=', $monthStart)
                ->sum('total'),

            'total_expenses_month' => Purchase::where('company_id', $company->id)
                ->whereIn('status', ['approved', 'partial', 'paid'])
                ->where('purchase_date', '>=', $monthStart)
                ->sum('total'),

            'outstanding_receivables' => Invoice::where('company_id', $company->id)
                ->whereIn('status', ['sent', 'partial', 'overdue'])
                ->sum('balance_due'),

            'outstanding_payables' => Purchase::where('company_id', $company->id)
                ->whereIn('status', ['approved', 'partial'])
                ->sum('balance_due'),

            'total_customers' => Customer::where('company_id', $company->id)
                ->where('is_active', true)
                ->count(),

            'total_invoices_month' => Invoice::where('company_id', $company->id)
                ->where('invoice_date', '>=', $monthStart)
                ->count(),

            'overdue_invoices' => Invoice::where('company_id', $company->id)
                ->where('status', 'overdue')
                ->count(),

            'total_employees' => Employee::where('company_id', $company->id)
                ->where('status', 'active')
                ->count(),
        ];

        // Recent invoices
        $recentInvoices = Invoice::where('company_id', $company->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Recent purchases
        $recentPurchases = Purchase::where('company_id', $company->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Chart data (last 6 months)
        $chartData = [
            'labels' => [],
            'revenue' => [],
            'expenses' => []
        ];

        for ($i = 5; $i >= 0; $i--) {
            $date = $today->copy()->subMonths($i);
            $monthName = $date->format('M');
            $chartData['labels'][] = $monthName;

            $mStart = $date->copy()->startOfMonth();
            $mEnd = $date->copy()->endOfMonth();

            $revenue = Invoice::where('company_id', $company->id)
                ->whereIn('status', ['sent', 'partial', 'paid'])
                ->whereBetween('invoice_date', [$mStart, $mEnd])
                ->sum('total');

            $expenses = Purchase::where('company_id', $company->id)
                ->whereIn('status', ['approved', 'partial', 'paid'])
                ->whereBetween('purchase_date', [$mStart, $mEnd])
                ->sum('total');

            $chartData['revenue'][] = (float) $revenue;
            $chartData['expenses'][] = (float) $expenses;
        }

        return view('dashboard', compact(
            'stats',
            'recentInvoices',
            'recentPurchases',
            'chartData',
            'company'
        ));
    }
}
