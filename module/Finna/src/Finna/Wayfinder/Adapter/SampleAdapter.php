<?php

/**
 * Sample placement data adapter implementation.
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
 * Sample placement data adapter implementation.
 *
 * PHP version 8
 *
 * @category Wayfinder
 * @package  Wayfinder
 * @author   Inlead <support@inlead.dk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://inlead.dk
 */
class SampleAdapter implements LocationAdapterInterface
{
    /**
     * Gets the placement location DTO.
     *
     * @param array $data Placement payload.
     *
     * @return WayfinderMarker Marker DTO.
     */
    public function getLocation(array $data): WayfinderMarker
    {
        return (new WayfinderMarker())
            ->setBranch('sample')
            ->setDepartment($data['branch'] ?? '')
            ->setLocation($data['department'] ?? '')
            ->setDk5($data['callnumber'] ?? '');
    }
}
