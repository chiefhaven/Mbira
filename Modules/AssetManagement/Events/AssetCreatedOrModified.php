<?php

namespace Modules\AssetManagement\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\AssetManagement\Entities\Asset;

class AssetCreatedOrModified
{
    use Dispatchable, SerializesModels;

    public $asset;
    public $isDeleted;

    public function __construct(Asset $asset, bool $isDeleted = false)
    {
        $this->asset = $asset;
        $this->isDeleted = $isDeleted;
    }
}
