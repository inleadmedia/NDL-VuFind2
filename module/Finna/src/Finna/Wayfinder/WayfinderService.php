<?php

/**
 * Wayfinder service integration.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
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
    protected array $config;

    /**
     * Http service.
     *
     * @var HttpServiceInterface
     */
    protected HttpServiceInterface $httpService;

    /**
     * Logger service.
     *
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * Whether service has valid config.
     *
     * @var bool
     */
    protected bool $isConfigured;

    /**
     * Constructor.
     *
     * @param array                            $config      Configuration.
     * @param HttpServiceInterface $httpService HTTP service.
     * @param LoggerInterface $logger      Logger service.
     */
    public function __construct(
        array $config,
        HttpServiceInterface $httpService,
        LoggerInterface $logger
    ) {
        $this->config = $config;
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
    public function getMarker(array $payload): string {
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
    protected function fetchMarker(array $args): string
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
    protected function isValidConfig(): bool
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
