<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;

class WorkStatus extends Model
{
    public static function getStatuses()
    {
        return [
            'employee' => trans_choice('contact.employee', 1),
            'owner' => trans_choice('contact.owner', 1),
            'student' => trans_choice('contact.student', 1),
            'government_employee' => trans_choice('contact.government_employee', 1),
            'private_employee' => trans_choice('contact.private_employee', 1),
            'unemployed' => trans_choice('contact.unemployed', 1),
            'overseas_worker' => trans_choice('contact.overseas_worker', 1),
            'pensioner' => trans_choice('contact.pensioner', 1),
        ];
    }
}
