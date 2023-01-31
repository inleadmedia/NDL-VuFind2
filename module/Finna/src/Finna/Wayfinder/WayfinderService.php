<?php

namespace Finna\Wayfinder;

use Finna\Wayfinder\DTO\WayfinderMarker;
use Laminas\Http\Response;
use Laminas\Log\LoggerInterface;
use VuFindHttp\HttpServiceInterface;

/**
 * Wayfinder Service.
 *
 * @category VuFind
 * @package  Content
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
     * @param \VuFindHttp\HttpServiceInterface HTTP service
     * @param \Laminas\Log\LoggerInterface Logger service
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
     * @param string $branch
     * @param string $department
     * @param string $location
     * @param string $author
     * @param string $dk5
     *
     * @return string
     */
    public function getMarker($source, $location, $callnumber): string {
        [$department, $location] = explode('-', $location);

        $wayfinderDto = (new WayfinderMarker())
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
     * @param string $branch
     * @param string $department
     * @param string $location
     * @param string $author
     * @param string $dk5
     *
     * @return string
     */
    protected function fetchMarker(array $args): string {
        $args = array_map(function ($v) {
            return trim($v);
        }, $args);
        $params = array_filter($args);

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
