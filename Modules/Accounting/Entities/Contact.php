<?php

namespace Modules\Accounting\Entities;

use App\Contact as AppContact;
use Modules\EmployeeLoan\Entities\EmployeeLoan;
use Modules\Loan\Entities\Loan;

class Contact extends AppContact
{
    protected $appends = ['full_name'];    

    public function loans()
    {
        return $this->hasMany(Loan::class, 'contact_id', 'id');
    }

    public function employeeloans()
    {
        return $this->hasMany(EmployeeLoan::class, 'contact_id', 'id');
    }

    public function scopeForBusiness($query, $business_id = null)
    {
        return $query->where('contacts.business_id', $business_id ?? session('business.id'));
    }

    public function scopeForDropdown($query)
    {
        return $query->select('id', 'first_name', 'middle_name', 'last_name', 'type');
    }
}
