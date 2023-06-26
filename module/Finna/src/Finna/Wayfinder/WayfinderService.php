<?php

/**
 * Wayfinder service integration.
 *
 * PHP version 7
 *
 * @category Wayfinder
 * @package  Wayfinder
 * @author   Inlead <support@inlead.dk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://inlead.dk
 */

namespace Finna\Wayfinder;

use Finna\Wayfinder\Adapter\SampleAdapter;
use Laminas\Http\Response;
use Laminas\Log\LoggerInterface;
use VuFindHttp\HttpServiceInterface;

/**
 * Wayfinder service.
 *
 * @category Wayfinder
 * @package  Wayfinder
 * @author   Inlead <support@inlead.dk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://inlead.dk
 */
class WayfinderService
{
    /**
     * Location service configuration.
     *
     * @var array
     */
    protected array $config = [];

    /**
     * Http service.
     *
     * @var \VuFindHttp\HttpServiceInterface
     */
    protected HttpServiceInterface $httpService;

    /**
     * Logger service.
     *
     * @var \Laminas\Log\LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * Whether service has valid config.
     *
     * @var bool
     */
    private bool $isConfigured;

    /**
     * Constructor.
     *
     * @param \Laminas\Config\Config           $config      Configuration.
     * @param \VuFindHttp\HttpServiceInterface $httpService HTTP service.
     * @param \Laminas\Log\LoggerInterface     $logger      Logger service.
     */
    public function __construct(
        $config,
        HttpServiceInterface $httpService,
        LoggerInterface $logger
    ) {
        $this->config = $config->toArray();
        $this->isConfigured = $this->isValidConfig();

        $this->httpService = $httpService;
        $this->logger = $logger;
    }

    /**
     * Gets wayfinder map link.
     *
     * @param array $payload Placement information array.
     *
     * @return string
     */
    public function getMarker(
        array $payload
    ): string {
        // @TODO: Dynamically decide which plugin to use from wayfinder config.
        return $this->fetchMarker(
            (new SampleAdapter())->getLocation($payload)->toArray()
        );
    }

    /**
     * Whether service can be used, i.e. is configured.
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return $this->isConfigured;
    }

    /**
     * Fetches map link from wayfinder based on holding information.
     *
     * @param array $args Location arguments.
     *
     * @return string
     */
    private function fetchMarker(array $args): string
    {
        $args = array_map(
            function ($v) {
                return trim($v);
            },
            array_filter($args)
        );

        if (!$this->isConfigured()) {
            $this->logger->warn('[Wayfinder] Service not configured.');
            return '';
        }

        $url = $this->config['General']['url'] . '/includes';
        $response = $this->httpService->get($url, $args);

        if ($response->getStatusCode() !== Response::STATUS_CODE_200) {
            $this->logger->warn(
                '[Wayfinder] Failed to read placement marker'
                . ' from url [' . $url . '].'
                . ' Status code [' . $response->getStatusCode() . '].'
            );
            return '';
        }

        try {
            $decoded = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logger->err($exception->getMessage());
            return '';
        }

        if (empty($decoded['link'])) {
            $this->logger->warn(
                '[Wayfinder] Failed to get marker link from response'
                . ' using [' . $url . '].'
                . ' Response [' . $response->getContent() . ']'
            );
            return '';
        }

        return $this->config['General']['url'] . $decoded['link'];
    }

    /**
     * Checks for valid config url.
     *
     * @return bool
     */
    private function isValidConfig(): bool
    {
        if (empty($this->config)) {
            return false;
        }

        $url = parse_url($this->config['General']['url']);
        if (!$url) {
            return false;
        }

        if (empty(array_filter($url))) {
            return false;
        }

        return true;
    }
}
