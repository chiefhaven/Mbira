<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\Log;

class FlashService
{
    public function onException($e, $message = null)
    {
        $message = $message ?: trans_choice('accounting::core.an_error_occurred', 1);
        Log::error("File:" . $e->getFile() . " Line:" . $e->getLine() . " Message:" . $e->getMessage());
        request()->session()->flash('error', $message);
        return $this;
    }

    public function onWarning($message)
    {
        request()->session()->flash('warning', $message);
        return $this;
    }

    public function onSuccess($message)
    {
        request()->session()->flash('success', $message);
        return $this;
    }

    public function onSave()
    {
        request()->session()->flash('status.success', 1);
        request()->session()->flash('status.msg', trans_choice("accounting::lang.successfully_saved", 1));
        return $this;
    }

    public function onUpdate()
    {
        request()->session()->flash('status.success', 1);
        request()->session()->flash('status.msg', trans_choice("accounting::lang.successfully_updated", 1));
        return $this;
    }

    public function onDelete()
    {
        request()->session()->flash('status.success', 1);
        request()->session()->flash('status.msg', trans_choice("accounting::lang.successfully_deleted", 1));
        return $this;
    }

    public function redirectBackWithInput()
    {
        return redirect()->back()->withInput();
    }
}
