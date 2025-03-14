<?php

/**
 * Model for R2 records.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @category VuFind
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */

namespace Finna\RecordDriver;

use function in_array;

/**
 * Model for R2 records.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class R2Ead3 extends SolrEad3
{
    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $mainConfig     VuFind main configuration (omit
     * for built-in defaults)
     * @param \Laminas\Config\Config $recordConfig   Record-specific configuration
     * file (omit to use $mainConfig as $recordConfig)
     * @param \Laminas\Config\Config $searchSettings Search-specific configuration
     * file
     */
    public function __construct(
        $mainConfig = null,
        $recordConfig = null,
        $searchSettings = null
    ) {
        parent::__construct($mainConfig, $recordConfig, $searchSettings);
        $this->setSourceIdentifiers('R2');
    }

    /**
     * Does this record contain restricted metadata?
     *
     * @return bool
     */
    public function hasRestrictedMetadata()
    {
        // Recursively check the access restrictions:
        $hasAI24 = function ($elem, $checkFunc) {
            foreach ($elem->accessrestrict ?? [] as $accessrestrict) {
                if (
                    'ahaa:AI24' === (string)$accessrestrict['encodinganalog']
                    || $checkFunc($accessrestrict, $checkFunc)
                ) {
                    return true;
                }
            }
            return false;
        };

        return $hasAI24($this->getXmlRecord(), $hasAI24);
    }

    /**
     * Is restricted metadata included with the record, i.e. is the user
     * authorized to access restricted metadata?
     *
     * @return bool
     */
    public function isRestrictedMetadataIncluded()
    {
        return ($this->fields['display_restriction_id_str'] ?? false) === '10';
    }

    /**
     * Get the Hierarchy Type (false if none)
     *
     * @return string|bool
     */
    public function getHierarchyType()
    {
        return 'R2';
    }

    /**
     * Is social media sharing allowed
     *
     * @return boolean
     */
    public function socialMediaSharingAllowed()
    {
        return false;
    }

    /**
     * Indicate whether export is disabled for a particular format.
     *
     * @param string $format Export format
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function exportDisabled($format)
    {
        return !in_array($format, ['BibTeX', 'EndNote', 'RefWorks', 'RIS']);
    }

    /**
     * Returns an array of 0 or more record label constants, or null if labels
     * are not enabled in configuration.
     *
     * @return array|null
     */
    public function getRecordLabels()
    {
        if (!$this->getRecordLabelsEnabled()) {
            return null;
        }
        $labels = [];
        if ($this->hasRestrictedMetadata()) {
            $labels[] = $this->isRestrictedMetadataIncluded()
                      ? FinnaRecordLabelInterface::R2_RESTRICTED_METADATA_INCLUDED
                      : FinnaRecordLabelInterface::R2_RESTRICTED_METADATA_AVAILABLE;
        }
        return $labels;
    }
}
