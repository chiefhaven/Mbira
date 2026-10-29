<?php

use FontLib\Table\Type\name;

return [

    'name' => 'EIS',

    'module_version' => '1.0.0',
    
    'enabled' => true,

    'environment' => env('EIS_ENV', 'development'),

    'product_id' => env('EIS_PRODUCT_ID'),

    'product_version' => env('EIS_PRODUCT_VERSION', '1.0.0'),

    'base_url' => env('EIS_BASE_URL'),

    'timeout' => 30,

    'retry' => 3,

];
