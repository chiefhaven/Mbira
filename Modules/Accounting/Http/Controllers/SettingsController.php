<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\AccountingAccountType;
use Modules\Accounting\Entities\AccountingAccount;
use Modules\Accounting\Entities\AccountingAccountsTransaction;
use Modules\Accounting\Entities\AccountingBudget;
use Modules\Accounting\Entities\AccountingAccTransMapping;
use Modules\Accounting\Utils\AccountingUtil;
use App\Utils\ModuleUtil;
use App\Business;

class SettingsController extends Controller
{
    protected $accountingUtil;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct(AccountingUtil $accountingUtil, ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
        $this->accountingUtil = $accountingUtil;
    }

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        if (!(auth()->user()->can('superadmin') || 
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $account_sub_types = AccountingAccountType::where('account_type', 'sub_type')
                                    ->where(function($q) use($business_id){
                                        $q->whereNull('business_id')
                                        ->orWhere('business_id', $business_id);
                                    })
                                    ->get();

        $account_types = AccountingAccountType::accounting_primary_type();

        $accounting_settings = $this->accountingUtil->getAccountingSettings($business_id);
        $auto_posting_accounts = AccountingAccount::where('business_id', $business_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->mapWithKeys(function ($account) {
                return [$account->id => $account->name . ' (#' . $account->id . ')'];
            });

        return view('accounting::settings.index')->with(compact(
            'account_sub_types',
            'account_types',
            'accounting_settings',
            'auto_posting_accounts'
        ));
    }

    public function resetData()
    {
        $business_id = request()->session()->get('user.business_id');

        if (!(auth()->user()->can('superadmin') || 
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module'))) {
            abort(403, 'Unauthorized action.');
        }

        //check for admin
        if(!$this->accountingUtil->is_admin(auth()->user())){
            abort(403, 'Unauthorized action.');
        }

        //reset logic
        AccountingBudget::join('accounting_accounts', 'accounting_budgets.accounting_account_id', '=', 'accounting_accounts.id')
            ->where('accounting_accounts.business_id', $business_id)
            ->delete();

        AccountingAccountType::where('business_id', $business_id)
            ->delete();
        
        AccountingAccTransMapping::where('business_id', $business_id)->delete();

        AccountingAccountsTransaction::join('accounting_accounts', 'accounting_accounts_transactions.accounting_account_id', '=', 'accounting_accounts.id')
            ->where('business_id', $business_id)->delete();


        AccountingAccount::where('business_id', $business_id)->delete();

        return back();
    }

    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function create()
    {
        return view('accounting::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function saveSettings(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        if (!(auth()->user()->can('superadmin') || ($this->moduleUtil->hasThePermissionInSubscription($business_id, 
        'accounting_module')))) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'journal_entry_prefix' => 'nullable|string|max:100',
            'transfer_prefix' => 'nullable|string|max:100',
            'auto_posting_enabled' => 'nullable|boolean',
            'auto_posting_purchase_tax_recoverable' => 'nullable|boolean',
            'auto_posting_accounts' => 'nullable|array',
            'auto_posting_accounts.receivables' => 'required_if:auto_posting_enabled,1|nullable|integer',
            'auto_posting_accounts.sales' => 'required_if:auto_posting_enabled,1|nullable|integer',
            'auto_posting_accounts.payables' => 'required_if:auto_posting_enabled,1|nullable|integer',
            'auto_posting_accounts.inventory' => 'required_if:auto_posting_enabled,1|nullable|integer',
            'auto_posting_accounts.cost_of_goods_sold' => 'required_if:auto_posting_enabled,1|nullable|integer',
            'auto_posting_accounts.output_tax' => 'required_if:auto_posting_enabled,1|nullable|integer',
            'auto_posting_accounts.input_tax' => 'required_if:auto_posting_enabled,1|nullable|integer',
            'auto_posting_accounts.expenses' => 'required_if:auto_posting_enabled,1|nullable|integer',
            'auto_posting_accounts.clearing' => 'required_if:auto_posting_enabled,1|nullable|integer',
        ]);

        $autoPostingEnabled = $request->boolean('auto_posting_enabled');
        $autoPostingAccountIds = array_filter(
            array_map('intval', $validated['auto_posting_accounts'] ?? []),
            fn ($id) => $id > 0
        );

        if ($autoPostingEnabled) {
            if (!Schema::hasTable('accounting_source_postings')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'auto_posting_enabled' => 'Apply the pending Accounting source-posting migration before enabling automatic postings.',
                ]);
            }

            if (DB::connection()->getDriverName() === 'mysql') {
                $tableStatus = DB::selectOne(
                    'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                    ['accounting_source_postings']
                );
                if ($tableStatus === null || strtoupper($tableStatus->ENGINE) !== 'INNODB') {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'auto_posting_enabled' => 'Apply the pending Accounting source-posting migration before enabling automatic postings.',
                    ]);
                }
            }

            $expectedTypes = [
                'receivables' => 'asset',
                'sales' => 'income',
                'payables' => 'liability',
                'inventory' => 'asset',
                'cost_of_goods_sold' => 'expenses',
                'output_tax' => 'liability',
                'input_tax' => 'asset',
                'expenses' => 'expenses',
                'clearing' => 'asset',
            ];
            $accounts = AccountingAccount::where('business_id', $business_id)
                ->where('status', 'active')
                ->whereIn('id', $autoPostingAccountIds)
                ->get(['id', 'account_primary_type'])
                ->keyBy('id');

            foreach ($expectedTypes as $key => $type) {
                if (!isset($accounts[$autoPostingAccountIds[$key] ?? 0])
                    || $accounts[$autoPostingAccountIds[$key]]->account_primary_type !== $type) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'auto_posting_accounts.' . $key => 'Select an active account of the required type for this business.',
                    ]);
                }
            }

            if (count(array_unique($autoPostingAccountIds)) !== count($expectedTypes)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'auto_posting_accounts' => 'Each automatic posting role must use a distinct account.',
                ]);
            }
        }

        try {
            $input = array_merge($this->accountingUtil->getAccountingSettings($business_id), $request->only([
                'journal_entry_prefix',
                'transfer_prefix',
                'barcode_type',
                'repair_tc_condition',
                'job_sheet_prefix',
                'problem_reported_by_customer',
                'product_condition',
                'product_configuration',
                'job_sheet_custom_field_1',
                'job_sheet_custom_field_2',
                'job_sheet_custom_field_3',
                'job_sheet_custom_field_4',
                'job_sheet_custom_field_5',
                'default_repair_checklist',
            ]));

            $input['auto_posting_enabled'] = $autoPostingEnabled;
            $input['auto_posting_accounts'] = $autoPostingAccountIds;
            $input['auto_posting_purchase_tax_recoverable'] = $request->boolean('auto_posting_purchase_tax_recoverable');

            Business::where('id', $business_id)
                        ->update(['accounting_settings' => json_encode($input)]);

            $output = ['success' => true,
                            'msg' => __("lang_v1.updated_success")
                        ];
        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());
            
            $output = ['success' => false,
                            'msg' => __("messages.something_went_wrong")
                        ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show($id)
    {
        return view('accounting::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Response
     */
    public function edit($id)
    {
        return view('accounting::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }
}
