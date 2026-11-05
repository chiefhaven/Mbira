<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminSidebarMenu;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Nwidart\Menus\Facades\Menu;
use Nwidart\Menus\MenuItem;
use Nwidart\Modules\Collection as ModuleCollection;
use Nwidart\Modules\Facades\Module;
use Tests\TestCase;

class AdminSidebarMenuTest extends TestCase
{
    public function testRestoredLinksUseTheCorrectRoutesAndOrder(): void
    {
        $menu = $this->sellMenu(['direct_sell.access', 'so.view_all'], ['add_sale'], true);

        $this->assertNotNull($menu);
        $this->assertSame(
            [__('lang_v1.sales_order'), __('lang_v1.all_sales'), __('sale.add_sale')],
            array_map(fn ($item) => $item->title, array_slice($menu->getChilds(), 0, 3))
        );
        $this->assertSame('/sales-order', parse_url($this->child($menu, __('lang_v1.sales_order'))->url, PHP_URL_PATH));
        $this->assertSame('/sells', parse_url($this->child($menu, __('lang_v1.all_sales'))->url, PHP_URL_PATH));
        $this->assertSame('/sells/create', parse_url($this->child($menu, __('sale.add_sale'))->url, PHP_URL_PATH));

        $html = Menu::render('admin-sidebar-menu', 'adminltecustom');
        foreach ([__('lang_v1.sales_order'), __('lang_v1.all_sales'), __('sale.add_sale')] as $title) {
            $this->assertStringContainsString('href="'.$this->child($menu, $title)->getUrl().'"', $html);
            $this->assertStringContainsString('<span>'.$title.'</span>', $html);
        }
    }

    /**
     * @dataProvider addSaleAvailability
     */
    public function testAddSaleRequiresTheModuleAndPermission(array $permissions, array $modules, bool $visible): void
    {
        $menu = $this->sellMenu($permissions, $modules, false);

        $this->assertNotNull($menu);
        $this->assertSame($visible, $this->child($menu, __('sale.add_sale')) !== null);
    }

    public static function addSaleAvailability(): array
    {
        return [
            'module and permission enabled' => [['sell.view', 'direct_sell.access'], ['add_sale'], true],
            'module disabled' => [['sell.view', 'direct_sell.access'], [], false],
            'permission missing' => [['sell.view'], ['add_sale'], false],
        ];
    }

    /**
     * @dataProvider salesOrderOnlyPermissions
     */
    public function testSalesOrderOnlyPermissionsCanReachTheMenu(string $permission, bool $enabled): void
    {
        $menu = $this->sellMenu([$permission], [], $enabled);

        if (!$enabled) {
            $this->assertNull($menu);

            return;
        }

        $this->assertNotNull($menu);
        $this->assertNotNull($this->child($menu, __('lang_v1.sales_order')));
        $this->assertNull($this->child($menu, __('lang_v1.all_sales')));
        $this->assertNull($this->child($menu, __('sale.add_sale')));
    }

    public static function salesOrderOnlyPermissions(): array
    {
        return [
            'view own enabled' => ['so.view_own', true],
            'view all enabled' => ['so.view_all', true],
            'create enabled' => ['so.create', true],
            'view own disabled' => ['so.view_own', false],
            'view all disabled' => ['so.view_all', false],
            'create disabled' => ['so.create', false],
        ];
    }

    public function testSalesOrderRequiresPermissionWhenEnabled(): void
    {
        $menu = $this->sellMenu(['sell.view'], [], true);

        $this->assertNotNull($menu);
        $this->assertNull($this->child($menu, __('lang_v1.sales_order')));
    }

    public function testAdminCanSeeSalesOrdersWhenEnabled(): void
    {
        $menu = $this->sellMenu([], [], true, '/', true);

        $this->assertNotNull($menu);
        $this->assertNotNull($this->child($menu, __('lang_v1.sales_order')));
    }

    /**
     * @dataProvider activeRoutes
     */
    public function testActiveStateMatchesTheCurrentSaleRoute(
        string $uri,
        bool $allSalesActive,
        bool $addSaleActive,
        bool $salesOrderActive
    ): void {
        $menu = $this->sellMenu(['direct_sell.access', 'so.view_all'], ['add_sale'], true, $uri);

        $this->assertNotNull($menu);
        $this->assertSame($allSalesActive, $this->child($menu, __('lang_v1.all_sales'))->isActive());
        $this->assertSame($addSaleActive, $this->child($menu, __('sale.add_sale'))->isActive());
        $this->assertSame($salesOrderActive, $this->child($menu, __('lang_v1.sales_order'))->isActive());
    }

    public static function activeRoutes(): array
    {
        return [
            'all sales' => ['/sells', true, false, false],
            'add sale' => ['/sells/create', false, true, false],
            'draft' => ['/sells/create?status=draft', false, false, false],
            'quotation' => ['/sells/create?status=quotation', false, false, false],
            'sales orders' => ['/sales-order', false, false, true],
            'add sales order' => ['/sells/create?sale_type=sales_order', false, false, true],
        ];
    }

    private function sellMenu(
        array $permissions,
        array $modules,
        bool $salesOrdersEnabled,
        string $uri = '/',
        bool $isAdmin = false
    ): ?MenuItem {
        session()->start();
        session([
            'business.id' => 1,
            'business.enabled_modules' => $modules,
            'business.pos_settings' => json_encode(['enable_sales_order' => $salesOrdersEnabled]),
        ]);

        $request = Request::create($uri);
        $request->setLaravelSession(session()->driver());
        $this->app->instance('request', $request);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->withAnyArgs()->andReturn($isAdmin);
        $user->shouldReceive('can')->withAnyArgs()
            ->andReturnUsing(fn ($permission) => in_array($permission, $permissions, true));
        $user->shouldReceive('hasAnyPermission')->withAnyArgs()
            ->andReturnUsing(fn ($required) => count(array_intersect((array) $required, $permissions)) > 0);
        Auth::shouldReceive('user')->andReturn($user);
        Module::shouldReceive('toCollection')->once()->andReturn(new ModuleCollection());

        (new AdminSidebarMenu())->handle($request, fn ($request) => $request);

        return Menu::instance('admin-sidebar-menu')->whereTitle(__('sale.sale'));
    }

    private function child(MenuItem $menu, string $title): ?MenuItem
    {
        return collect($menu->getChilds())->first(fn ($item) => $item->title === $title);
    }
}
