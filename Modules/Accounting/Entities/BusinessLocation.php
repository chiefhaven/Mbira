<?php

namespace Modules\Accounting\Entities;

use App\BusinessLocation as AppBusinessLocation;

class BusinessLocation extends AppBusinessLocation
{
    public static function getDropdownCollection($business_id)
    {
        $locations = collect([]);

        foreach (BusinessLocation::forDropdown($business_id) as $id => $name) {
            $location = (object)['id' => $id, 'name' => $name];
            $locations->push($location);
        }

        return $locations;
    }
}
