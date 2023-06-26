<?php

namespace Finna\Wayfinder\Adapter;

use Finna\Wayfinder\DTO\WayfinderMarker;

interface LocationAdapterInterface
{
    public function getLocation(array $data): WayfinderMarker;
}
