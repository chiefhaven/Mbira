<?php

namespace Modules\Accounting\Services;

use Spatie\Permission\Models\Permission;

trait PermissionService
{

    public function getPermissions($module)
    {
        $permissions = Permission::where('name', 'like', $module . '.%')
            ->selectRaw('name as value, display_name as label')
            ->get();

        return $permissions->map(function ($item) {
            $item['default'] = false;
            return $item;
        });
    }
}
