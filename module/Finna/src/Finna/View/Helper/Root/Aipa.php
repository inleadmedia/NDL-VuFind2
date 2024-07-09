<?php

/**
 * AIPA view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023-2024.
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
 * @package  View_Helpers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

namespace Finna\View\Helper\Root;

use Finna\RecordDriver\AipaLrmi;
use Finna\RecordDriver\SolrAipa;
use Laminas\View\Helper\AbstractHelper;
use NatLibFi\FinnaCodeSets\FinnaCodeSets;
use NatLibFi\FinnaCodeSets\Model\EducationalLevel\EducationalLevelInterface;
use NatLibFi\FinnaCodeSets\Model\HierarchicalProxyDataObjectInterface as HPDOInterface;
use NatLibFi\FinnaCodeSets\Utility\EducationalData;

use function count;

/**
 * AIPA view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Aipa extends AbstractHelper
{
    // Sort order for educational levels.
    protected const EDUCATIONAL_LEVEL_SORT_ORDER = [
        EducationalData::PRIMARY_SCHOOL,
        EducationalData::LOWER_SECONDARY_SCHOOL,
        EducationalLevelInterface::UPPER_SECONDARY_SCHOOL,
        EducationalLevelInterface::VOCATIONAL_EDUCATION,
        EducationalLevelInterface::HIGHER_EDUCATION,
    ];

    // Educational data rendered as educational subjects.
    protected const RENDER_EDUCATIONAL_SUBJECTS_KEYS = [
        EducationalData::LEARNING_AREAS,
        EducationalData::EDUCATIONAL_SUBJECTS,
        EducationalData::VOCATIONAL_QUALIFICATIONS,
    ];

    /**
     * Finna Code Sets library instance.
     *
     * @var FinnaCodeSets
     */
    protected FinnaCodeSets $codeSets;

    /**
     * Record driver
     *
     * @var SolrAipa|AipaLrmi
     */
    protected SolrAipa|AipaLrmi $driver;

    /**
     * Constructor
     *
     * @param FinnaCodeSets $codeSets Finna Code Sets library instance
     */
    public function __construct(FinnaCodeSets $codeSets)
    {
        $this->codeSets = $codeSets;
    }

    /**
     * Store a record driver object and return this object.
     *
     * @param SolrAipa|AipaLrmi $driver Record driver object.
     *
     * @return Aipa
     */
    public function __invoke(SolrAipa|AipaLrmi $driver): Aipa
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Render all subject headings.
     *
     * @return string
     */
    public function renderAllSubjectHeadings(): string
    {
        $headings = $this->driver->getAllSubjectHeadings();
        if (empty($headings)) {
            return '';
        }

        $recordHelper = $this->getView()->plugin('record');
        $items = [];
        foreach ($headings as $field) {
            $item = '';
            $subject = '';
            if (count($field) == 1) {
                $field = explode('--', $field[0]);
            }
            $i = 0;
            foreach ($field as $subfield) {
                $item .= ($i++ == 0) ? '' : ' &#8594; ';
                $subject = trim($subject . ' ' . $subfield);
                $item .= $recordHelper($this->driver)->getLinkedFieldElement(
                    'subject',
                    $subfield,
                    ['name' => $subject],
                    ['class' => ['backlink']]
                );
            }
            $items[] = $item;
        }

        $component = $this->getView()->plugin('component');
        return $component('finna-tag-list', [
            'title' => 'Subjects',
            'items' => $items,
            'htmlItems' => true,
        ]);
    }

    /**
     * Render educational levels and educational subjects.
     *
     * @param array $educationalData Educational data from record driver
     *
     * @return string
     */
    public function renderEducationalLevelsAndSubjects(array $educationalData): string
    {
        if (empty($educationalData)) {
            return '';
        }

        $component = $this->getView()->plugin('component');
        $langcode = $this->view->layout()->userLang;

        $html = '';
        foreach ($this->getEducationalLevelCodeValues($educationalData) as $levelCodeValue) {
            $levelData = EducationalData::getEducationalLevelData(
                $levelCodeValue,
                $educationalData,
                true
            );
            if (empty($levelData)) {
                continue;
            }

            $items = [];
            foreach (self::RENDER_EDUCATIONAL_SUBJECTS_KEYS as $key) {
                foreach ($levelData[$key] ?? [] as $subjectLevel) {
                    $items[] = $subjectLevel->getPrefLabel($langcode);
                }
            }
            $html .= $component('finna-tag-list', [
                'title' => 'Aipa::' . $levelCodeValue,
                'items' => $items,
                'translateItems' => false,
            ]);
        }
        return $html;
    }

    /**
     * Render educational data.
     *
     * @param array $educationalData Educational data from record driver
     *
     * @return string
     */
    public function renderEducationalData(array $educationalData): string
    {
        if (empty($educationalData)) {
            return '';
        }

        $component = $this->getView()->plugin('component');
        $transEsc = $this->getView()->plugin('transEsc');
        $langcode = $this->view->layout()->userLang;

        // Iterate through all educational levels.
        $html = '';
        $levelsHtml = [];
        foreach ($this->getEducationalLevelCodeValues($educationalData) as $levelCodeValue) {
            // Get educational level specific data.
            $levelData = EducationalData::getEducationalLevelData(
                $levelCodeValue,
                $educationalData,
                true
            );
            if (empty($levelData)) {
                continue;
            }

            $componentData = [];

            switch ($levelCodeValue) {
                case EducationalLevelInterface::EARLY_CHILDHOOD_EDUCATION:
                    $componentData = array_merge(
                        $componentData,
                        $this->getComponentDataForType(
                            EducationalData::LEARNING_AREAS,
                            $levelData,
                            $langcode
                        )
                    );
                    break;

                case EducationalData::PRIMARY_SCHOOL:
                case EducationalData::LOWER_SECONDARY_SCHOOL:
                case EducationalLevelInterface::UPPER_SECONDARY_SCHOOL:
                    $componentData = array_merge(
                        $componentData,
                        $this->getComponentDataForSubjects(
                            EducationalData::EDUCATIONAL_SUBJECTS,
                            $levelData,
                            $langcode,
                            $levelCodeValue
                        )
                    );
                    $componentData = array_merge(
                        $componentData,
                        $this->getComponentDataForType(
                            EducationalData::TRANSVERSAL_COMPETENCES,
                            $levelData,
                            $langcode
                        )
                    );
                    break;

                case EducationalLevelInterface::VOCATIONAL_EDUCATION:
                    $componentData = array_merge(
                        $componentData,
                        $this->getComponentDataForSubjects(
                            EducationalData::VOCATIONAL_QUALIFICATIONS,
                            $levelData,
                            $langcode,
                            $levelCodeValue
                        )
                    );
                    $componentData = array_merge(
                        $componentData,
                        $this->getComponentDataForSubjects(
                            EducationalData::VOCATIONAL_COMMON_UNITS,
                            $levelData,
                            $langcode,
                            $levelCodeValue
                        )
                    );
                    break;
            }

            if (!empty($componentData)) {
                $levelsHtml[$levelCodeValue]
                    = $component('finna-educational-level-data', [
                        'sectionHeadingLevel' => 5,
                        'educationalLeveData' => $componentData,
                    ]);
            }
        }

        // Truncate educational level data there is more than one level.
        foreach ($levelsHtml as $levelCodeValue => $levelHtml) {
            if (count($levelsHtml) > 1) {
                $html .= $component('finna-truncate', [
                    'content' => $levelHtml,
                    'element' => 'div',
                    'label' => 'Aipa::' . $levelCodeValue,
                    'topToggle' => -1,
                ]);
            } else {
                $html .= '<h4>' . $transEsc('Aipa::' . $levelCodeValue) . '</h4>';
                $html .= $levelHtml;
            }
        }

        return $html;
    }

    /**
     * Get processed educational level code values.
     *
     * @param array $educationalData Educational data from record driver
     *
     * @return array
     */
    protected function getEducationalLevelCodeValues(array $educationalData): array
    {
        // Basic education levels are mapped to primary school and lower secondary
        // school levels.
        $levelCodeValues
            = $this->codeSets->getEducationalData()->getMappedLevelCodeValues(
                $educationalData[EducationalData::EDUCATIONAL_LEVELS] ?? []
            );

        // Remove basic education root level, data should be entered at lower levels.
        unset($levelCodeValues[EducationalLevelInterface::BASIC_EDUCATION]);

        // Sort the educational levels according to defined sort order.
        usort(
            $levelCodeValues,
            function (string $a, string $b): int {
                return array_search($a, self::EDUCATIONAL_LEVEL_SORT_ORDER)
                    > array_search($b, self::EDUCATIONAL_LEVEL_SORT_ORDER)
                        ? 1 : -1;
            }
        );

        return $levelCodeValues;
    }

    /**
     * Get component data for subjects.
     *
     * @param string $type            Educational data array key
     * @param array  $educationalData Educational data from record driver
     * @param string $langcode        Language code for the data
     * @param string $levelCodeValue  Educational level code value
     *
     * @return array Component data array for subjects, if any
     */
    protected function getComponentDataForSubjects(
        string $type,
        array $educationalData,
        string $langcode,
        string $levelCodeValue
    ): array {
        $componentData = [];
        $items = [];
        foreach ($educationalData[$type] ?? [] as $subject) {
            $subjectData = EducationalData::getEducationalSubjectData(
                $subject,
                $educationalData
            );
            $items[] = [
                'title' => $subject->getPrefLabel($langcode),
                'items' => $this->getComponentDataForSubject(
                    $subjectData,
                    $langcode,
                    $levelCodeValue
                ),
            ];
        }
        if (!empty($items)) {
            $componentData[] = [
                'attributes' => [
                    'class' => [$type],
                ],
                'title' => 'Aipa::' . $type,
                'items' => $items,
            ];
        }
        return $componentData;
    }

    /**
     * Get component data for an educational subject.
     *
     * @param HPDOInterface $subjectData    Hierarchical educational subject data
     * @param string        $langcode       Language code for the data
     * @param string        $levelCodeValue Educational level code value
     *
     * @return array
     */
    protected function getComponentDataForSubject(
        HPDOInterface $subjectData,
        string $langcode,
        string $levelCodeValue
    ): array {
        $items = [];
        if (empty($children = $subjectData->getChildren())) {
            return $items;
        }

        // Place children in an educational data array by type.
        $educationalData = [];
        foreach ($children as $proxyItem) {
            $item = $proxyItem->getProxiedObject();
            $educationalData[EducationalData::getKeyForInstance($item)][]
                = $proxyItem;
        }

        // Build component data to be rendered.
        foreach (EducationalData::EDUCATIONAL_SUBJECT_LEVEL_KEYS as $key) {
            $subjectLevelItems = [];
            foreach ($educationalData[$key] ?? [] as $item) {
                $subjectLevelItems[] = [
                    'title' => $item->getProxiedObject()->getPrefLabel($langcode),
                    'items' => $this->getComponentDataForSubject(
                        $item,
                        $langcode,
                        $levelCodeValue
                    ),
                ];
            }
            if (!empty($subjectLevelItems)) {
                $items[] = [
                    'title' => 'Aipa::' . $key,
                    'items' => $subjectLevelItems,
                ];
            }
        }
        foreach (EducationalData::STUDY_DATA_KEYS as $key) {
            if (isset($educationalData[$key])) {
                $studyDataItems = [];

                // Study data is displayed by educational level.
                $dataByLevel = EducationalData::getStudyDataKeyedByEducationalLevel(
                    $educationalData[$key]
                );
                foreach ($dataByLevel as $dataLevelCodeValue => $data) {
                    $levelItems = [];
                    foreach ($data as $studyData) {
                        $levelItems[] = [
                            'title' => $studyData->getPrefLabel($langcode),
                        ];
                    }
                    if ($dataLevelCodeValue !== $levelCodeValue) {
                        $studyDataItems[] = [
                            'title' => 'Aipa::' . $dataLevelCodeValue,
                            'items' => $levelItems,
                        ];
                    } else {
                        $studyDataItems = $levelItems;
                    }
                }

                $items[] = [
                    'title' => 'Aipa::' . $key,
                    'items' => $studyDataItems,
                ];
            }
        }

        return $items;
    }

    /**
     * Get component data of the specified type.
     *
     * @param string $type            Educational data array key
     * @param array  $educationalData Educational data from record driver
     * @param string $langcode        Language code for the data
     *
     * @return array Component data of the specified type, if any
     */
    protected function getComponentDataForType(
        string $type,
        array $educationalData,
        string $langcode
    ): array {
        $componentData = [];
        $items = [];
        foreach ($educationalData[$type] ?? [] as $data) {
            $items[] = [
                'title' => $data->getPrefLabel($langcode),
            ];
        }
        if (!empty($items)) {
            $componentData[] = [
                'attributes' => ['class' => [$type]],
                'title' => 'Aipa::' . $type,
                'items' => $items,
            ];
        }
        return $componentData;
    }
}
