<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\Log;

class ApiService
{
    public function onException($e)
    {
        Log::error($e->getMessage());
        return response()->json(['success' => false, 'msg' => trans('accounting::core.an_error_occurred')]);
    }

    public function onSave()
    {
        return response()->json(['success' => true, 'msg' => trans_choice("accounting::lang.successfully_saved", 1)]);
    }

    public function onUpdate()
    {
        return response()->json(['success' => true, 'msg' => trans_choice("accounting::lang.successfully_updated", 1)]);
    }

    public function onDelete()
    {
        return response()->json(['success' => true, 'msg' => trans_choice("accounting::lang.successfully_deleted", 1)]);
    }
}
