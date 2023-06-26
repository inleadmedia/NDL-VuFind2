<?php

/**
 * AJAX handler to lookup Wayfinder placement link.
 *
 * PHP version 8
 *
 * @category Wayfinder
 * @package  AJAX
 * @author   Inlead <support@inlead.dk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://inlead.dk
 */

namespace VuFind\AjaxHandler;

use Finna\Wayfinder\WayfinderService;
use Laminas\Mvc\Controller\Plugin\Params;

/**
 * AJAX handler to lookup Wayfinder placement link.
 *
 * PHP version 8
 *
 * @category Wayfinder
 * @package  AJAX
 * @author   Inlead <support@inlead.dk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://inlead.dk
 */
class WayfinderPlacementLinkLookup extends AbstractBase
{
    /**
     * Wayfinder service instance.
     *
     * @var WayfinderService
     */
    protected $wayfinderService;

    /**
     * Handler constructor
     *
     * @param WayfinderService $wayfinderService Wayfinder service instance
     */
    public function __construct(WayfinderService $wayfinderService)
    {
        $this->wayfinderService = $wayfinderService;
    }

    /**
     * Handled the incoming request.
     *
     * @param Params $params Request parameters.
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $markerUrl = $this->wayfinderService->getMarker(
            json_decode($params->fromPost('placement', '[]'), true)
        );

        if (empty($markerUrl)) {
            return $this->formatResponse('wayfinder_error', self::STATUS_HTTP_UNAVAILABLE);
        }

        return $this->formatResponse([
            'marker_url' => $markerUrl,
            'status' => true,
        ]);
    }
}
