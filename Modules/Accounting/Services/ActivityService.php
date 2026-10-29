<?php

namespace Modules\Accounting\Services;

use Carbon\Carbon;
use Spatie\Activitylog\Models\Activity;

class ActivityService
{
    public static function noUsersLoggedInToday($business_id, array $user_types)
    {
        return Activity::where('activity_log.business_id', $business_id)
            ->join('users', 'users.id', '=', 'activity_log.causer_id')
            ->where('description', 'Login')
            ->whereIn('users.user_type', $user_types)
            ->where('activity_log.created_at', '>=', Carbon::now()->startOfDay())
            ->groupBy('causer_id')
            ->select('users.id')
            ->get()
            ->count();
    }
}
