<?php

namespace Modules\Woocommerce\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class WoocommerceDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        //$this->call([AddDummySyncLogTableSeeder::class]);
    }
}
