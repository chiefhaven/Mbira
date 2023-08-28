<?php

// use App\Http\Controllers\Modules;
// use Illuminate\Support\Facades\Route;

Route::middleware('web', 'authh', 'SetSessionData', 'auth', 'language', 'timezone', 'ContactSidebarMenu', 'CheckContactLogin')->prefix('contact')->group(function () {
    Route::resource('contact-dashboard', 'Modules\Crm\Http\Controllers\DashboardController');
    Route::get('contact-profile', [Modules\Crm\Http\Controllers\ManageProfileController::class, 'getProfile']);
    Route::post('contact-password-update', [Modules\Crm\Http\Controllers\ManageProfileController::class, 'updatePassword']);
    Route::post('contact-profile-update', [Modules\Crm\Http\Controllers\ManageProfileController::class, 'updateProfile']);
    Route::get('contact-purchases', [Modules\Crm\Http\Controllers\PurchaseController::class, 'getPurchaseList']);
    Route::get('contact-sells', [Modules\Crm\Http\Controllers\SellController::class, 'getSellList']);
    Route::get('contact-ledger', [Modules\Crm\Http\Controllers\LedgerController::class, 'index']);
    Route::get('contact-get-ledger', [Modules\Crm\Http\Controllers\LedgerController::class, 'getLedger']);
    Route::resource('bookings', 'Modules\Crm\Http\Controllers\ContactBookingController');
    Route::resource('order-request', 'Modules\Crm\Http\Controllers\OrderRequestController');
    Route::get('products/list', [\App\Http\Controllers\ProductController::class, 'getProducts']);
    Route::get('order-request/get_product_row/{variation_id}/{location_id}', [Modules\Crm\Http\Controllers\OrderRequestController::class, 'getProductRow']);
});

Route::middleware('web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin')->prefix('crm')->group(function () {
    Route::get('commissions', [Modules\Crm\Http\Controllers\ContactLoginController::class, 'commissions']);
    Route::get('all-contacts-login', [Modules\Crm\Http\Controllers\ContactLoginController::class, 'allContactsLoginList']);
    Route::resource('contact-login', 'Modules\Crm\Http\Controllers\ContactLoginController')->except(['show']);
    Route::resource('follow-ups', 'Modules\Crm\Http\Controllers\ScheduleController')->except(['show']);
    Route::get('todays-follow-ups', [Modules\Crm\Http\Controllers\ScheduleController::class, 'getTodaysSchedule']);
    Route::get('lead-follow-ups', [Modules\Crm\Http\Controllers\ScheduleController::class, 'getLeadSchedule']);
    Route::get('get-invoices', [Modules\Crm\Http\Controllers\ScheduleController::class, 'getInvoicesForFollowUp']);
    Route::get('get-followup-groups', [Modules\Crm\Http\Controllers\ScheduleController::class, 'getFollowUpGroups']);
    Route::get('all-users-call-logs', [Modules\Crm\Http\Controllers\CallLogController::class, 'allUsersCallLog']);

    Route::resource('follow-up-log', 'Modules\Crm\Http\Controllers\ScheduleLogController');

    Route::get('install', [Modules\Crm\Http\Controllers\InstallController::class, 'index']);
    Route::post('install', [Modules\Crm\Http\Controllers\InstallController::class, 'install']);
    Route::get('install/uninstall', [Modules\Crm\Http\Controllers\InstallController::class, 'uninstall']);
    Route::get('install/update', [Modules\Crm\Http\Controllers\InstallController::class, 'update']);

    Route::resource('leads', 'Modules\Crm\Http\Controllers\LeadController');
    Route::get('lead/{id}/convert', [Modules\Crm\Http\Controllers\LeadController::class, 'convertToCustomer']);
    Route::get('lead/{id}/post-life-stage', [Modules\Crm\Http\Controllers\LeadController::class, 'postLifeStage']);

    Route::get('{id}/send-campaign-notification', [Modules\Crm\Http\Controllers\CampaignController::class, 'sendNotification']);
    Route::resource('campaigns', 'Modules\Crm\Http\Controllers\CampaignController');
    Route::get('dashboard', [Modules\Crm\Http\Controllers\CrmDashboardController::class, 'index']);

    Route::get('reports', [Modules\Crm\Http\Controllers\ReportController::class, 'index']);
    Route::get('follow-ups-by-user', [Modules\Crm\Http\Controllers\ReportController::class, 'followUpsByUser']);
    Route::get('follow-ups-by-contact', [Modules\Crm\Http\Controllers\ReportController::class, 'followUpsContact']);
    Route::get('lead-to-customer-report', [Modules\Crm\Http\Controllers\ReportController::class, 'leadToCustomerConversion']);
    Route::get('lead-to-customer-details/{user_id}', [Modules\Crm\Http\Controllers\ReportController::class, 'showLeadToCustomerConversionDetails']);
    Route::get('call-log', [Modules\Crm\Http\Controllers\CallLogController::class, 'index'], ['only' => ['index']]);
    Route::post('mass-delete-call-log', [Modules\Crm\Http\Controllers\CallLogController::class, 'massDestroy']);

    Route::get('edit-proposal-template', [Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'getEdit']);
    Route::post('update-proposal-template', [Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'postEdit']);
    Route::get('view-proposal-template', [Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'getView']);
    Route::get('send-proposal', [Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'send']);
    Route::delete('delete-proposal-media/{id}', [Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'deleteProposalMedia']);
    Route::resource('proposal-template', 'Modules\Crm\Http\Controllers\ProposalTemplateController')->except(['show', 'edit', 'update', 'destroy']);
    Route::resource('proposals', 'Modules\Crm\Http\Controllers\ProposalController')->except(['create', 'edit', 'update', 'destroy']);
    Route::get('settings', [Modules\Crm\Http\Controllers\CrmSettingsController::class, 'index']);
    Route::post('update-settings', [Modules\Crm\Http\Controllers\CrmSettingsController::class, 'updateSettings']);
    Route::get('order-request', [Modules\Crm\Http\Controllers\OrderRequestController::class, 'listOrderRequests']);
    Route::get('b2b-marketplace', [Modules\Crm\Http\Controllers\CrmMarketplaceController::class, 'index']);
    Route::post('save-marketplace', [Modules\Crm\Http\Controllers\CrmMarketplaceController::class, 'save']);
    Route::get('import-leads', [Modules\Crm\Http\Controllers\CrmMarketplaceController::class, 'importLeads']);
});
