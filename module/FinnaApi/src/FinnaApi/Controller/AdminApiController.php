<?php

/**
 * Admin Api Controller
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2017.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Riikka Kalliomäki <riikka.kalliomaki@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace FinnaApi\Controller;

use VuFind\Service\Feature\RetryTrait;

/**
 * Provides web api for different admin tasks.
 *
 * @category VuFind
 * @package  Controller
 * @author   Riikka Kalliomäki <riikka.kalliomaki@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AdminApiController extends \VuFindApi\Controller\AdminApiController
{
    use RetryTrait;

    /**
     * Clear the cache
     *
     * @return \Laminas\Http\Response
     */
    public function clearCacheAction()
    {
        $this->disableSessionWrites();
        $this->determineOutputMode();

        if ($result = $this->isAccessDenied($this->cacheAccessPermission)) {
            return $result;
        }

        try {
            $cacheList = $this->getRequest()->getQuery()->get('id')
                ?: $this->getDefaultCachesToClear();
            foreach ((array)$cacheList as $id) {
                // Try a couple of times in case we fail to remove something due to a race condition:
                $this->callWithRetry(
                    [$this->cacheManager->getCache($id), 'flush'],
                    options: [
                        'retryCount' => 50,
                        'maximumBackoff' => 0,
                    ]
                );
            }
        } catch (\Exception $e) {
            return $this->output([], self::STATUS_ERROR, 500, (string)$e);
        }

        return $this->output([], self::STATUS_OK);
    }

    /**
     * Returns available core record fields as an associative array of
     * cssClass => translated label pairs.
     *
     * @return array
     */
    public function getRecordFieldsAction()
    {
        $this->disableSessionWrites();
        $this->determineOutputMode();

        $formatter = $this->getViewRenderer()->plugin('recordDataFormatter');
        $fields = $formatter->getDefaults('core');

        $data = [];
        foreach ($fields as $key => $val) {
            if (empty($val['context']['class'])) {
                continue;
            }
            $data[] = [
                'label' => $this->translate($key),
                'class' => $val['context']['class'],
            ];
        }

        return $this->output(['fields' => $data], self::STATUS_OK);
    }

    /**
     * Returns list of organisations
     *
     * @return \Laminas\Http\Response
     */
    public function organisationListAction(): \Laminas\Http\Response
    {
        $this->disableSessionWrites();
        $this->determineOutputMode();

        $organisationInfo = $this->serviceLocator->get(
            \Finna\OrganisationInfo\OrganisationInfo::class
        );
        return $this->output(
            [
                'data' => $organisationInfo->getOrganisationsList(),
            ],
            self::STATUS_OK
        );
    }
}
