<?php

namespace Modules\Accounting\Http\Controllers;

use Modules\Accounting\Services\FlashService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Accounting\Entities\AccountDetailType;
use Modules\Accounting\Entities\AccountSubtype;
use Modules\Accounting\Entities\AccountType;

class AccountingSettingsController extends Controller
{
    public function detail_types()
    {
        $account_detail_types = AccountDetailType::forBusiness()->with('account_subtype')->get();

        $account_subtypes = AccountSubtype::forBusiness()->active()->get();

        return view('accounting::settings.detail_types.index', compact('account_detail_types', 'account_subtypes'));
    }

    public function store_detail_types(Request $request)
    {
        $validated = $request->validate([
            'account_subtype_id' => ['required'],
            'name' => ['required'],
            'description' => ['nullable'],
        ]);

        $validated['active'] = $request->has('active');
        $validated['business_id'] = session('business.id');
        try {
            //ensure $validated attributes are in fillable array 
            AccountDetailType::create($validated);
        } catch (\Exception $e) {
            return (new FlashService())->onException($e)->redirectBackWithInput();
        }

        (new FlashService())->onSave();
        return back();
    }

    public function edit_detail_types($id)
    {
        $account_detail_type = AccountDetailType::forBusiness()->findOrfail($id);

        $account_subtypes = AccountSubtype::forBusiness()->active()->get();

        return view('accounting::settings.detail_types.edit', compact('account_detail_type', 'account_subtypes'));
    }

    public function update_detail_types(Request $request, $id)
    {
        $validated = $request->validate([
            'account_subtype_id' => ['required'],
            'name' => ['required'],
            'description' => ['nullable'],
        ]);

        $validated['active'] = $request->has('active');

        try {
            AccountDetailType::forBusiness()->findOrFail($id)->update($validated);
        } catch (\Exception $e) {
            return (new FlashService())->onException($e)->redirectBackWithInput();
        }

        (new FlashService())->onSave();

        return redirect('accounting/settings/detail_types');
    }

    public function destroy_detail_type($id)
    {
        try {
            AccountDetailType::destroy($id);
        } catch (\Exception $e) {
            return (new FlashService())->onException($e);
        }

        (new FlashService())->onDelete();

        return redirect()->back();
    }

    public function account_subtypes()
    {
        $account_subtypes = AccountSubtype::forBusiness()->orderBy('account_type')->get();

        $account_types = AccountType::getTypes();

        return view('accounting::settings.account_subtypes.index', compact('account_subtypes', 'account_types'));
    }

    public function store_account_subtypes(Request $request)
    {
        $validated = $request->validate([
            'account_type' => ['required'],
            'name' => ['required'],
            'description' => ['nullable'],
        ]);

        $validated['active'] = $request->has('active');
        $validated['business_id'] = session('business.id');

        try {
            //ensure $validated attributes are in fillable array
            AccountSubtype::create($validated);
        } catch (\Exception $e) {
            return (new FlashService())->onException($e)->redirectBackWithInput();
        }

        (new FlashService())->onSave();
        return back();
    }

    public function edit_account_subtypes($id)
    {
        $account_subtype = AccountSubtype::forBusiness()->findOrfail($id);

        $account_types = AccountType::getTypes();

        return view('accounting::settings.account_subtypes.edit', compact('account_subtype', 'account_types'));
    }

    public function update_account_subtypes(Request $request, $id)
    {
        $validated = $request->validate([
            'account_type' => ['required'],
            'name' => ['required'],
            'description' => ['nullable'],
        ]);

        $validated['active'] = $request->has('active');

        try {
            AccountSubtype::forBusiness()->findOrFail($id)->update($validated);
        } catch (\Exception $e) {
            return (new FlashService())->onException($e)->redirectBackWithInput();
        }

        (new FlashService())->onSave();

        return redirect('accounting/settings/account_subtypes');
    }

    public function destroy_account_subtype($id)
    {
        try {
            AccountSubtype::destroy($id);
        } catch (\Exception $e) {
            return (new FlashService())->onException($e);
        }

        (new FlashService())->onDelete();

        return redirect()->back();
    }
}
