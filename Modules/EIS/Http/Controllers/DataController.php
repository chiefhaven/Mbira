<?php

namespace Modules\EIS\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;
use Modules\EIS\Services\EisSubscriptionAccess;

class DataController extends Controller
{
    public function superadmin_package(): array
    {
        return [
            [
                'name' => 'eis_module',
                'label' => __('eis::lang.eis_module'),
                'default' => false,
            ],
        ];
    }

    public function modifyAdminMenu(): void
    {
        $user = auth()->user();
        $businessId = session()->get('user.business_id');
        if (!$user || !$businessId
            || (!$user->can('superadmin')
                && !app(EisSubscriptionAccess::class)->allows((int) $businessId))) {
            return;
        }

        $canManage = $user->can('business_settings.access');
        $canReconcile = $canManage || $user->can('stock_adjustment.view');
        $canUseOutbound = $canManage
            || $user->can('stock_adjustment.create')
            || $user->can('stock_transfer.create')
            || $user->can('purchase.create');
        if (!$canManage && !$canReconcile && !$canUseOutbound) {
            return;
        }

        $homeRoute = $canManage
            ? 'eis.terminals.page'
            : ($canReconcile ? 'eis.stock.index' : 'eis.stock.outbound.index');

        Menu::modify('admin-sidebar-menu', function ($menu) use ($homeRoute) {
            $menu->url(
                route($homeRoute),
                __('eis::lang.eis'),
                [
                    'icon' => 'fas fa-file-invoice-dollar',
                    'active' => request()->segment(1) === 'eis',
                ]
            )->order(88);
        });
    }
}
