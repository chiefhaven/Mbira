<?php

namespace Modules\Cms\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    /**
     * Adds cms menus
     *
     * @return null
     */
    public function modifyAdminMenu()
    {
        $module_util = new ModuleUtil();

        $business_id = session()->get('user.business_id');

        if (auth()->user()->can('superadmin')) {
            Menu::modify('admin-sidebar-menu', function ($menu) {
                $menu->url(
                    action([\Modules\Cms\Http\Controllers\CmsPageController::class, 'index'], ['type' => 'page']),
                    __('cms::lang.cms'),
                    ['icon' => 'fas fa-window-restore fa', 'style' => config('app.env') == 'demo' ? 'background-color: #9E458B !important;' : '', 'active' => request()->segment(1) == 'cms']
                )->order(100);
            });
        }
    }
}
