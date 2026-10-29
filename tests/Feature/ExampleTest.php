<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function testConfiguredAdminUsernamesAreReturned()
    {
        config()->set('constants.administrator_usernames', 'haven, thejazz,  admin');

        $controller = app(\Modules\Connector\Http\Controllers\Api\UserController::class);
        $response = $controller->adminUsernames();

        $this->assertSame(['haven', 'thejazz', 'admin'], $response['data']['usernames']);
    }

    public function testNewSellDoesNotCrashWhenOnlyOverduePermissionIsGranted()
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 1, 'business_id' => 1, 'name' => 'Test User']);
        $user->shouldReceive('hasRole')->withAnyArgs()->andReturn(false);
        $user->shouldReceive('hasAnyPermission')->withAnyArgs()->andReturnUsing(function ($permissions) {
            $permissions = is_array($permissions) ? $permissions : [$permissions];

            return in_array('sell.view', $permissions, true) || in_array('direct_sell.access', $permissions, true);
        });
        $user->shouldReceive('permitted_locations')->with(1)->andReturn('all');
        $user->shouldReceive('can')->withAnyArgs()->andReturnUsing(function ($permission) {
            return $permission === 'view_overdue_sells_only';
        });

        Auth::shouldReceive('user')->andReturn($user);

        $request = Request::create('/connector/api/new_sell', 'GET', ['per_page' => 1]);
        $request->setUserResolver(fn () => $user);

        $controller = app(\Modules\Connector\Http\Controllers\Api\ProductSellController::class);
        $response = $controller->newSell();

        $this->assertNotNull($response);
        $this->assertIsObject($response);
    }

    public function testAddAccountTransactionSkipsMissingTransactionType()
    {
        $transactionPayment = Mockery::mock(\App\TransactionPayment::class)->makePartial();
        $transactionPayment->method = 'cash';
        $transactionPayment->business_id = 1;
        $transactionPayment->payment_type = null;
        $transactionPayment->paid_on = '2026-05-10 15:48:43';
        $transactionPayment->created_by = 3;
        $transactionPayment->transaction_id = 6;
        $transactionPayment->id = 5;

        $moduleUtil = Mockery::mock(\App\Utils\ModuleUtil::class);
        $moduleUtil->shouldReceive('isModuleEnabled')->with('account', 1)->andReturn(true);

        $transactionUtil = Mockery::mock(\App\Utils\TransactionUtil::class);
        $listener = new \App\Listeners\AddAccountTransaction($transactionUtil, $moduleUtil);

        $event = new \App\Events\TransactionPaymentAdded($transactionPayment, ['account_id' => 8, 'amount' => 2000]);

        $this->assertTrue($listener->handle($event));
    }
}
