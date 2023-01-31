<?php
/**
 * Wayfinder service integration.
 *
 * @author Inlead
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link https://inlead.dk
 */

namespace Finna\Wayfinder;

use Finna\Wayfinder\DTO\WayfinderMarker;
use Laminas\Http\Response;
use Laminas\Log\LoggerInterface;
use VuFindHttp\HttpServiceInterface;

/**
 * Wayfinder service.
 *
 * @category Wayfinder
 * @package  Wayfinder
 * @author Inlead
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link https://inlead.dk
 */
class WayfinderService extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Location service configuration.
     *
     * @var \Laminas\Config\Config
     */
    protected $config = null;

    /**
     * Http service.
     *
     * @var \VuFindHttp\HttpServiceInterface
     */
    protected $httpService = null;

    /**
     * @var \Laminas\Log\LoggerInterface
     */
    protected $logger = null;

    /**
     * Constructor.
     *
     * @param \Laminas\Config\Config $config Configuration
     * @param \VuFindHttp\HttpServiceInterface $httpService HTTP service
     * @param \Laminas\Log\LoggerInterface $logger Logger service
     */
    public function __construct($config, HttpServiceInterface $httpService, LoggerInterface $logger)
    {
        $this->config = $config->toArray();
        $this->httpService = $httpService;
        $this->logger = $logger;
    }

    /**
     * Gets wayfinder map link.
     *
     * @param string $source
     * @param string $location
     * @param string $callnumber
     *
     * @return string
     */
    public function getMarker($source, $location, $callnumber): string {
        [$department, $location] = explode('-', $location);

        $wayfinderDto = (new WayfinderMarker())
            // TODO: The source/branch should be a live one.
//            ->setBranch($source)
            ->setBranch('Vanamo-kirjastot')
            ->setDepartment($department)
            ->setLocation($location)
            ->setDk5($callnumber);

        $markerUrl = $this->fetchMarker($wayfinderDto->toArray());

        return $this->getView()->render('Wayfinder/marker.phtml', [
            'marker_url' => $markerUrl,
            'marker_label' => 'Wayfinder',
        ]);
    }

    /**
     * Fetches map link from wayfinder based on holding information.
     *
     * @param array $args Location arguments.
     *
     * @return string
     */
    protected function fetchMarker(array $args): string {
        $args = array_map(function ($v) {
            return trim($v);
        }, $args);
        $params = array_filter($args);

        if (empty($this->config) || parse_url($this->config['General']['url'] ?? '') === false) {
            $this->logger->warn('[Wayfinder] Failed to parse or empty service url.');
            return '';
        }

        $url = $this->config['General']['url'] . '/includes';
        $response = $this->httpService->get($url, $params);

        if ($response->getStatusCode() !== Response::STATUS_CODE_200) {
            $this->logger->warn(sprintf("[Wayfinder] Failed to get placement marker from url [%s]. Status code [%d].", $url, $response->getStatusCode()));
            return '';
        }

        $decoded = json_decode($response->getContent(), true);
        if (empty($decoded['link'])) {
            $this->logger->warn(sprintf("[Wayfinder] Failed to decode response from url [%s]. Response [%s]", $url, $response->getContent()));
            return '';
        }

        return $this->config['General']['url'] . $decoded['link'];
    }
}
