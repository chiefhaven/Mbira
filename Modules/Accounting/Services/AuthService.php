<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\Auth;

trait AuthService
{
    public function isAdmin()
    {
        return Auth::user()->hasRole('admin');
    }
}
