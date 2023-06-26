<?php

/**
 * Placement source data adapter.
 *
 * PHP version 8
 *
 * @category Wayfinder
 * @package  Wayfinder
 * @author   Inlead <support@inlead.dk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://inlead.dk
 */

namespace Finna\Wayfinder\Adapter;

use Finna\Wayfinder\DTO\WayfinderMarker;

/**
 * Placement source data adapter.
 *
 * PHP version 8
 *
 * @category Wayfinder
 * @package  Wayfinder
 * @author   Inlead <support@inlead.dk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://inlead.dk
 */
interface LocationAdapterInterface
{
    /**
     * Gets the placement location DTO.
     *
     * @param array $data Placement payload.
     *
     * @return WayfinderMarker Marker DTO.
     */
    public function getLocation(array $data): WayfinderMarker;
}
