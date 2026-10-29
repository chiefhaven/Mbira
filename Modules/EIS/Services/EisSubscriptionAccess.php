<?php

namespace Modules\EIS\Services;

use App\Utils\ModuleUtil;
use Modules\EIS\Exceptions\EisSubscriptionRequiredException;
use Modules\Superadmin\Entities\Subscription;

class EisSubscriptionAccess
{
    private ?bool $superadminInstalled = null;

    public function allows(int $businessId): bool
    {
        if ($businessId <= 0) {
            return false;
        }

        if (app()->bound('auth')) {
            $user = auth()->user();
            if ($user && $user->can('superadmin')) {
                return true;
            }
        }

        if (!class_exists('Module') || !is_callable(['Module', 'has'])) {
            return true;
        }

        $this->superadminInstalled ??= app(ModuleUtil::class)->isSuperadminInstalled();
        if (!$this->superadminInstalled) {
            return true;
        }

        $subscription = Subscription::active_subscription($businessId);

        return $subscription !== null && !empty($subscription->package_details['eis_module']);
    }

    public function assertAllowed(int $businessId): void
    {
        if (!$this->allows($businessId)) {
            throw new EisSubscriptionRequiredException();
        }
    }
}
