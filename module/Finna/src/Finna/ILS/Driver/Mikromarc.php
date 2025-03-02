<?php

/**
 * Mikromarc ILS Driver
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2017-2023.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Bjarne Beckmann <bjarne.beckmann@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace Finna\ILS\Driver;

use VuFind\Date\DateException;
use VuFind\Exception\ILS as ILSException;

use function count;
use function in_array;
use function is_array;
use function is_callable;
use function is_string;
use function strlen;

/**
 * Mikromarc ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Bjarne Beckmann <bjarne.beckmann@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Mikromarc extends \VuFind\ILS\Driver\AbstractBase implements
    \VuFindHttp\HttpServiceAwareInterface,
    \VuFind\I18n\Translator\TranslatorAwareInterface,
    \Laminas\Log\LoggerAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFind\Cache\CacheTrait;

    /**
     * Date converter object
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter;

    /**
     * Sorter
     *
     * @var \VuFind\I18n\Sorter
     */
    protected $sorter;

    /**
     * Institution settings for the order of organisations
     *
     * @var array
     */
    protected $holdingsOrganisationOrder;

    /**
     * Default pickup location
     *
     * @var string
     */
    protected $defaultPickUpLocation;

    /**
     * Mappings from fee (account line) types
     *
     * @var array
     */
    protected $feeTypeMappings = [
        -1 => 'Accrued Fine',
        1 => 'Hold Fee',
        2 => 'Arrival Notice', // Ilmoitusmaksu
        3 => 'Other', // Laina
        4 => 'Other', // Uusinta
        5 => 'Accrued Fine',
        6 => 'Overdue',
        7 => 'Processing Fee for Overdue Notice', // Kehotus,
        8 => 'Lost Item Processing',
        9 => 'Hold Fee', // Seutuvaraus
        11 => 'Lost Item Replacement',
        12 => 'Other', // Maksu
        13 => 'Other', // Poistettu summa
        14 => 'Other', // Sekalaista
        15 => 'Interlibrary Loan', // Nidekohtainen ilmoitus / Kaukolaina
        16 => 'Other', // Kaukolaina
        17 => 'Other', // Kopiotilaus (kaukolaina)
        18 => 'Hold Expired',
    ];

    /**
     * Mappings for request groups
     *
     * @var array
     */
    protected $requestGroups = [
        'normal' => 'EntireUnitBranch',
        'regional' => 'CooperatingUnits',
    ];

    /**
     * Default request group
     *
     * @var string
     */
    protected $defaultRequestGroup = 'normal';

    /**
     * Are request groups enabled
     *
     * @var boolean
     */
    protected $requestGroupsEnabled = false;

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter Date converter object
     * @param \VuFind\I18n\Sorter    $sorter        Sorter
     */
    public function __construct(
        \VuFind\Date\Converter $dateConverter,
        \VuFind\I18n\Sorter $sorter
    ) {
        $this->dateConverter = $dateConverter;
        $this->sorter = $sorter;
    }

    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
    public function init()
    {
        $this->holdingsOrganisationOrder
            = isset($this->config['Holdings']['holdingsOrganisationOrder'])
            ? explode(':', $this->config['Holdings']['holdingsOrganisationOrder'])
            : [];

        $this->defaultPickUpLocation
            = $this->config['Holds']['defaultPickUpLocation']
            ?? '';

        $this->requestGroupsEnabled
            = isset($this->config['Holds']['extraHoldFields'])
        && in_array(
            'requestGroup',
            explode(':', $this->config['Holds']['extraHoldFields'])
        );
        $this->defaultRequestGroup
            = $this->config['Holds']['defaultRequestGroup'] ?? '';
    }

    /**
     * Public Function which retrieves renew, hold and cancel settings from the
     * driver ini file.
     *
     * @param string $function The name of the feature to be checked
     * @param array  $params   Optional feature-specific parameters (array)
     *
     * @return array An array with key-value pairs.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConfig($function, $params = null)
    {
        if ('onlinePayment' === $function) {
            $config = $this->config['OnlinePayment'] ?? [];
            if (!empty($config) && !isset($config['exactBalanceRequired'])) {
                $config['exactBalanceRequired'] = false;
            }
            return $config;
        }
        if ('getMyTransactionHistory' === $function) {
            if (empty($this->config['getMyTransactionHistory']['enabled'])) {
                return false;
            }
            return [
                'sort' => [
                    'checkout desc' => 'sort_checkout_date_desc',
                    'checkout asc' => 'sort_checkout_date_asc',
                    'return desc' => 'sort_return_date_desc',
                    'return asc' => 'sort_return_date_asc',
                    'title asc' => 'sort_title',
                ],
                'default_sort' => 'checkout desc',
            ];
        }
        $functionConfig = $this->config[$function] ?? false;
        if ($functionConfig && 'Holds' === $function) {
            if (
                isset($functionConfig['titleHoldBibLevels'])
                && !is_array($functionConfig['titleHoldBibLevels'])
            ) {
                $functionConfig['titleHoldBibLevels']
                    = explode(':', $functionConfig['titleHoldBibLevels']);
            }
        }
        return $functionConfig;
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id      The record id to retrieve the holdings for
     * @param array  $patron  Patron data
     * @param array  $options Extra options
     *
     * @throws \VuFind\Exception\ILS
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($id, array $patron = null, array $options = [])
    {
        $data = $this->getItemStatusesForBiblio($id, $patron);
        if (!empty($data)) {
            $summary = $this->getHoldingsSummary($data, $id);
            $data[] = $summary;
        }
        return $data;
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @return mixed     An array with the acquisitions data on success.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPurchaseHistory($id)
    {
        return [];
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return array An associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        return $this->getItemStatusesForBiblio($id);
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @return mixed     An array of getStatus() return values on success.
     */
    public function getStatuses($ids)
    {
        $items = [];
        foreach ($ids as $id) {
            $items[] = $this->getItemStatusesForBiblio($id);
        }
        return $items;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron password
     *
     * @return mixed Associative array of patron info on successful login,
     * null on unsuccessful login.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function patronLogin($username, $password)
    {
        $request = json_encode(
            [
              'Barcode' => $username,
              'Pin' => $password,
            ]
        );
        [$code, $patronId] = $this->makeRequest(
            ['odata', 'Borrowers', 'Default.Authenticate'],
            $request,
            'POST',
            true
        );
        if (($code != 200 && $code != 403) || empty($patronId)) {
            return null;
        } elseif ($code == 403) {
            if (
                !empty($patronId['error']['code'])
                && $patronId['error']['code'] == 'Defaulted'
            ) {
                $defaultedPatron = $this->makeRequest(
                    ['odata', 'Borrowers', 'Default.AuthenticateDebtor'],
                    $request,
                    'POST',
                    false
                );
                if (!empty($defaultedPatron['BorrowerId'])) {
                    $patronId = $defaultedPatron['BorrowerId'];
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }
        $patron = [
            'cat_username' => $username,
            'cat_password' => $password,
            'id' => $patronId,
        ];

        if ($profile = $this->getMyProfile($patron)) {
            $profile['major'] = null;
            $profile['college'] = null;
        }
        return $profile;
    }

    /**
     * Check whether the patron is blocked from placing requests (holds/ILL/SRR).
     *
     * @param array $patron Patron data from patronLogin().
     *
     * @return mixed A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    public function getRequestBlocks($patron)
    {
        return $this->getPatronBlocks($patron);
    }

    /**
     * Check whether the patron has any blocks on their account.
     *
     * @param array $patron Patron data from patronLogin().
     *
     * @return mixed A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    public function getAccountBlocks($patron)
    {
        return $this->getPatronBlocks($patron);
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all unpaid fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {
        // All fines, ciAccountEntryStatus = 2
        $allFines = $this->makeRequest(
            ['BorrowerDebts', $patron['cat_username'], '2', '0']
        );
        if (empty($allFines)) {
            return [];
        }

        // All non-paid fines
        $allFines = array_filter(
            $allFines,
            function ($fine) {
                return in_array($fine['State'], ['Estimated', 'Unpaid']);
            }
        );

        $payableIds = [];
        if ($this->supportsOnlinePayment()) {
            // Payable fines, ciAccountEntryStatus = 1
            $payableFines = $this->makeRequest(
                ['BorrowerDebts', $patron['cat_username'], '1', '0']
            );
            $payableIds = array_map(
                function ($fine) {
                    return $fine['Id'];
                },
                $payableFines
            );
        }
        $paymentConfig = $this->getConfig('onlinePayment');
        $blockedTypes = $paymentConfig['nonPayable'] ?? [];

        $fines = [];
        foreach ($allFines as $entry) {
            $createDate = !empty($entry['DebtDate'])
                ? $this->dateConverter->convertToDisplayDate(
                    'U',
                    strtotime($entry['DebtDate'])
                )
                : '';
            $typeCode = $entry['AccountCodeId'] ?? null;
            $type = $this->feeTypeMappings[$typeCode] ?? $entry['AccountCodeName']
                ?? '';
            $fineId = $entry['Id'] ?? null;
            $balance = $entry['Remainder'] * 100;
            $payable = $fineId && in_array($fineId, $payableIds)
                && !in_array($typeCode, $blockedTypes)
                && $balance >= 1;
            $fine = [
                'amount' => $entry['Amount'] * 100,
                'balance' => $balance,
                'fine' => $type,
                'createdate' => $createDate,
                'checkout' => '',
                'item_id' => $entry['ItemId'],
                // Append payment information
                'payableOnline' => $payable,
                'fineId' => $fineId,
                'fine_id' => $fineId,
                'organization' => $entry['LocalUnitId'] ?? '',
            ];
            $recordId = $entry['MarcRecordId'] ?? null;
            if ($recordId) {
                $fine['id'] = $recordId;
            }

            if (!empty($entry['MarcRecordTitle'])) {
                $fine['title'] = $entry['MarcRecordTitle'];
            }
            $fines[] = $fine;
        }
        return $fines;
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @throws ILSException
     * @return array        Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        $cacheKey = $this->getPatronCacheKey($patron, 'profile');
        if ($profile = $this->getCachedData($cacheKey)) {
            return $profile;
        }
        $result = $this->makeRequest(['odata', 'Borrowers(' . $patron['id'] . ')']);
        $expirationDate = !empty($result['Expires'])
            ? $this->dateConverter->convertToDisplayDate(
                'Y-m-d',
                $result['Expires']
            ) : '';

        $name = explode(',', $result['Name'], 2);
        $messagingConf = $this->config['messaging'] ?? null;

        $messagingSettings = [];

        $type = 'dueDateNotice';
        $dueDateNoticeActive = !$result['RefuseReminderMessages'];
        $messagingSettings[$type] = [
           'type' => $type,
           'settings' => [
              'digest' => [
                 'type' => 'boolean',
                 'readonly' => false,
                 'active' => $dueDateNoticeActive,
                 'label' => 'messaging_settings_option_' .
                    ($dueDateNoticeActive ? 'active' : 'inactive'),
              ],
           ],
        ];

        if (!empty($messagingConf['checkoutNotice'])) {
            $checkoutNoticeFormat = $result['ReceiptMessageFormat'];
            $type = 'checkoutNotice';
            $options = [];
            foreach ($messagingConf['checkoutNotice'] as $option) {
                [$key, $label] = explode(':', $option);
                $options[$key] = [
                   'name' => $this->translate("messaging_settings_option_$label"),
                   'value' => $key,
                   'active' => $checkoutNoticeFormat == $key,
                ];
            }
            $messagingSettings[$type] = [
               'type' => $type,
               'settings' => [
                  'transport_types' => [
                     'type' => 'select',
                     'value' => $checkoutNoticeFormat,
                     'options' => $options,
                  ],
               ],
            ];
        }

        if (!empty($messagingConf['notifications'])) {
            $type = 'notifications';
            $map = ['Email' => 'LettersByEmail', 'SMS' => 'LettersBySMS'];
            $options = [];
            foreach ($messagingConf['notifications'] as $option) {
                [$key, $label] = explode(':', $option);
                $options[$key] = [
                   'name' => $this->translate("messaging_settings_option_$label"),
                   'value' => $key,
                   'active' => $result[$map[$key]],
                ];
            }
            $messagingSettings[$type] = [
               'type' => $type,
               'settings' => [
                  'transport_types' => [
                     'type' => 'multiselect',
                     'options' => $options,
                  ],
               ],
            ];
        }

        $profile = [
            'firstname' => trim($name[1] ?? ''),
            'lastname' => ucfirst(trim($name[0])),
            'phone' => !empty($result['MainPhone'])
                ? $result['MainPhone'] : $result['Mobile'],
            'email' => $result['MainEmail'],
            'address1' => $result['MainAddrLine1'],
            'address2' => $result['MainAddrLine2'],
            'zip' => $result['MainZip'],
            'city' => $result['MainPlace'],
            'expiration_date' => $expirationDate,
            'messagingServices' => $messagingSettings,
            'blocked' => !empty($result['Defaulted']),
        ];

        if (isset($this->config['updateTransactionHistoryState']['method'])) {
            $profile['loan_history'] = $result['StoreBorrowerHistory'];
        }

        $profile = array_merge($patron, $profile);
        $this->putCachedData($cacheKey, $profile);
        return $profile;
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($patron)
    {
        $result = $this->makeRequest(
            ['odata', 'BorrowerLoans'],
            ['$filter' => 'BorrowerId eq' . ' ' . $patron['id']]
        );
        if (empty($result)) {
            return [];
        }
        $renewLimit = $this->config['Loans']['renewalLimit'];

        // Create a timestamp for calculating the due / overdue status
        $now = time();

        $transactions = [];
        foreach ($result as $entry) {
            $renewalCount = $entry['RenewalCount'];
            $dueDateTimeStr = $entry['DueTime'];
            if (strlen($dueDateTimeStr) === 10) {
                $dueDateTimeStr .= ' 23:59:59';
            }
            $dueDate = strtotime($dueDateTimeStr);
            if ($now > $dueDate) {
                $dueStatus = 'overdue';
            } elseif (($dueDate - $now) < 86400) {
                $dueStatus = 'due';
            } else {
                $dueStatus = false;
            }

            $transaction = [
                'id' => $entry['MarcRecordId'],
                'checkout_id' => $entry['Id'],
                'item_id' => $entry['ItemId'],
                'duedate' => $this->dateConverter->convertToDisplayDate(
                    'U',
                    $dueDate
                ),
                'dueStatus' => $dueStatus,
                'renew' => $renewalCount,
                'renewLimit' => $renewLimit,
                'renewable' => ($renewLimit - $renewalCount) > 0,
                'message' => $entry['Notes'],
            ];
            if (!empty($entry['MarcRecordTitle'])) {
                $transaction['title'] = $entry['MarcRecordTitle'];
            }
            $transactions[] = $transaction;
        }
        return $transactions;
    }

    /**
     * Get Renew Details
     *
     * @param array $checkOutDetails An array of item data
     *
     * @return string Data for use in a form field
     */
    public function getRenewDetails($checkOutDetails)
    {
        return $checkOutDetails['checkout_id'];
    }

    /**
     * Renew My Items
     *
     * Function for attempting to renew a patron's items. The data in
     * $renewDetails['details'] is determined by getRenewDetails().
     *
     * @param array $renewDetails An array of data required for renewing items
     * including the Patron ID and an array of renewal IDS
     *
     * @return array An array of renewal information keyed by item ID
     */
    public function renewMyItems($renewDetails)
    {
        $details = [];
        $blocks = [];
        foreach ($renewDetails['details'] as $id) {
            $checkedOutId = $id;
            [$code, $result] = $this->makeRequest(
                ['odata', "BorrowerLoans($checkedOutId)", 'Default.RenewLoan'],
                false,
                'POST',
                true
            );
            if ($code != 200 || $result['ServiceCode'] != 'LoanRenewed') {
                $currentResult = $this->convertError($code, $result);
                $currentResult['item_id'] = $checkedOutId;
                $details[$checkedOutId] = $currentResult;
                if (
                    $code > 204
                    && !in_array($currentResult['sysMessage'], $blocks)
                ) {
                    $blocks[] = $currentResult['sysMessage'];
                }
            } else {
                $newDate = $this->dateConverter->convertToDisplayDate(
                    'U',
                    strtotime($result['DueTime'])
                );
                $details[$checkedOutId] = [
                    'item_id' => $checkedOutId,
                    'success' => true,
                    'new_date' => $newDate,
                ];
                $this->putCachedData(
                    $this->getPatronCacheKey(
                        $renewDetails['patron'],
                        'transactionHistory'
                    ),
                    null
                );
            }
        }
        return compact('details', 'blocks');
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's holds on success.
     */
    public function getMyHolds($patron)
    {
        $request = [
            '$filter' => 'BorrowerId eq ' . $patron['id'],
            '$orderby' => 'DeliverAtLocalUnitId',
        ];
        $result = $this->makeRequest(
            ['odata', 'BorrowerReservations'],
            $request
        );
        if (!isset($result)) {
            return [];
        }
        $holds = [];
        foreach ($result as $entry) {
            $available = $entry['ServiceCode'] === 'ReservationArrived'
                || $entry['ServiceCode'] === 'ReservationNoticeSent';
            $inProcess = $entry['ServiceCode'] === 'Picked';
            $frozen = !$entry['ResActiveToday'] && !$available && !$inProcess;
            $frozenThrough = '';
            if (
                $frozen && $entry['ResPausedTo']
                && $entry['ResPausedTo'] != $entry['ResValidUntil']
            ) {
                $frozenThrough = $this->dateConverter->convertToDisplayDate(
                    'U',
                    strtotime($entry['ResPausedTo'])
                );
            }

            $updateDetails = !$available && !$inProcess
                ? $entry['Id'] . '|' . $entry['ResValidUntil']
                : '';
            $hold = [
                'id' => $entry['MarcRecordId'],
                'item_id' => $entry['Id'],
                'location' =>
                    $this->getLibraryUnitName($entry['DeliverAtLocalUnitId']),
                'create' => $this->dateConverter->convertToDisplayDate(
                    'U',
                    strtotime($entry['ResTime'])
                ),
                'expire' => $this->dateConverter->convertToDisplayDate(
                    'U',
                    strtotime($entry['ResValidUntil'])
                ),
                'position' => $inProcess ? $this->translate('hold_in_process')
                    : $entry['NumberInQueue'],
                'available' => $available,
                'reqnum' => $entry['Id'],
                'frozen' => $frozen,
                'frozenThrough' => $frozenThrough,
                'requestGroup' => $this->requestGroupsEnabled &&
                    isset($entry['Scope']) ?
                    'mikromarc_' . $this->getRequestGroupKey($entry['Scope'])
                    : '',
                'cancel_details' => $updateDetails,
                'updateDetails' => $updateDetails,
            ];
            if (!empty($entry['ResHeldUntil'])) {
                $hold['last_pickup_date']
                    = $this->dateConverter->convertToDisplayDate(
                        'U',
                        strtotime($entry['ResHeldUntil'])
                    );
            }
            if (!empty($entry['MarcRecordTitle'])) {
                $hold['title'] = $entry['MarcRecordTitle'];
            }
            $holds[] = $hold;
        }
        return $holds;
    }

    /**
     * Place Hold
     *
     * Attempts to place a hold or recall on a particular item and returns
     * an array with result details or throws an exception on failure of support
     * classes
     *
     * @param array $holdDetails An array of item and patron data
     *
     * @throws ILSException
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function placeHold($holdDetails)
    {
        $patron = $holdDetails['patron'];
        $pickUpLocation = !empty($holdDetails['pickUpLocation'])
            ? $holdDetails['pickUpLocation'] : $this->defaultPickUpLocation;
        $scope = $this->requestGroups[
            $holdDetails['requestGroupId'] ?? 'regional'
        ];

        // Make sure pickup location is valid
        if (!$this->pickUpLocationIsValid($pickUpLocation, $patron, $holdDetails)) {
            return $this->convertError(0, 'hold_invalid_pickup');
        }
        $request = [
            'BorrowerId' =>  $patron['id'],
            'MarcId' => $holdDetails['id'],
            'DeliverAtUnitId' => $pickUpLocation,
            'Scope' => $scope,
        ];

        [$code, $result] = $this->makeRequest(
            ['odata', 'BorrowerReservations', 'Default.Create'],
            json_encode($request),
            'POST',
            true
        );
        if ($code >= 300) {
            return $this->convertError($code, $result);
        }

        if ($holdDetails['startDateTS']) {
            $requestId = $result['Id'];
            // Suspend until the previous day from start date:
            $updateRequest = [
                'ResPausedFrom' => date('Y-m-d'),
                'ResPausedTo' => \DateTime::createFromFormat(
                    'U',
                    $holdDetails['startDateTS']
                )->modify('-1 DAY')->format('Y-m-d'),
            ];
            [$code, $result] = $this->makeRequest(
                ['odata','BorrowerReservations(' . $requestId . ')'],
                json_encode($updateRequest),
                'PATCH',
                true
            );
            if ($code >= 300) {
                // Report a success since the hold was created, but include a message
                // about the modification failure:
                return [
                    'success' => true,
                    'warningMessage' => 'hold_error_update_failed',
                ];
            }
        }

        return ['success' => true];
    }

    /**
     * Get request group key with a value from mapping array
     *
     * @param string $value Value to get the key for
     *
     * @return string
     */
    protected function getRequestGroupKey(string $value): string
    {
        $found = array_search($value, $this->requestGroups);
        return $found ?: $value;
    }

    /**
     * Cancel Holds
     *
     * Attempts to Cancel a hold. The data in $cancelDetails['details'] is determined
     * by getCancelHoldDetails().
     *
     * @param array $cancelDetails An array of item and patron data
     *
     * @return array               An array of data on each request including
     * whether or not it was successful and a system message (if available)
     */
    public function cancelHolds($cancelDetails)
    {
        $count = 0;
        $response = [];
        foreach ($cancelDetails['details'] as $details) {
            [$requestId] = explode('|', $details);
            [$resultCode] = $this->makeRequest(
                ['odata', 'BorrowerReservations(' . $requestId . ')'],
                false,
                'DELETE',
                true
            );
            if ($resultCode != 204) {
                $response[$requestId] = [
                    'success' => false,
                    'status' => 'hold_cancel_fail',
                    'sysMessage' => false,
                ];
            } else {
                $response[$requestId] = [
                    'success' => true,
                    'status' => 'hold_cancel_success',
                ];
                ++$count;
            }
        }
        return ['count' => $count, 'items' => $response];
    }

    /**
     * Update holds
     *
     * This is responsible for changing the status of hold requests
     *
     * @param array $holdsDetails The details identifying the holds
     * @param array $fields       An associative array of fields to be updated
     * @param array $patron       Patron array
     *
     * @return array Associative array of the results
     */
    public function updateHolds(
        array $holdsDetails,
        array $fields,
        array $patron
    ): array {
        $results = [];
        foreach ($holdsDetails as $details) {
            [$requestId, $resValidUntil] = explode('|', $details);
            $updateRequest = [];

            if (isset($fields['frozen'])) {
                if ($fields['frozen']) {
                    $updateRequest['ResPausedFrom'] = date('Y-m-d');
                    if (isset($fields['frozenThroughTS'])) {
                        $updateRequest['ResPausedTo']
                            = date('Y-m-d', $fields['frozenThroughTS']);
                    } else {
                        $updateRequest['ResPausedTo'] = $resValidUntil;
                    }
                } else {
                    $updateRequest['ResPausedFrom'] = null;
                    $updateRequest['ResPausedTo'] = null;
                }
            }
            if (isset($fields['pickUpLocation'])) {
                $updateRequest['DeliverAtLocalUnitId'] = $fields['pickUpLocation'];
            }

            [$code, $result] = $this->makeRequest(
                ['odata','BorrowerReservations(' . $requestId . ')'],
                json_encode($updateRequest),
                'PATCH',
                true
            );
            if ($code >= 300) {
                $holdError = $this->convertError($code, $result);
                $results[$requestId] = [
                    'success' => false,
                    'status' => $holdError['sysMessage'],
                ];
            } else {
                $results[$requestId] = [
                    'success' => true,
                ];
            }
        }
        return $results;
    }

    /**
     * Get Pick Up Locations
     *
     * This is responsible for gettting a list of valid library locations for
     * holds / recall retrieval
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the pickup options
     * or may be ignored. The driver must not add new options to the return array
     * based on this data or other areas of VuFind may behave incorrectly.
     *
     * @throws ILSException
     * @return array        An array of associative arrays with locationID and
     * locationDisplay keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
        $excluded = isset($this->config['Holds']['excludePickupLocations'])
            ? explode(':', $this->config['Holds']['excludePickupLocations']) : [];

        $units = $this->getLibraryUnits();
        $locations = [];
        foreach ($units as $key => $val) {
            if (in_array($key, $excluded) || $val['department']) {
                continue;
            }
            $locations[] = [
                'locationID' => $key,
                'locationDisplay' => $val['name'],
            ];
        }

        // Do we need to sort pickup locations? If the setting is false, don't
        // bother doing any more work. If it's not set at all, default to
        // alphabetical order.
        $orderSetting = $this->config['Holds']['pickUpLocationOrder'] ?? 'default';
        if (count($locations) > 1 && !empty($orderSetting)) {
            $locationOrder = $orderSetting === 'default'
                ? [] : array_flip(explode(':', $orderSetting));
            $sortFunction = function ($a, $b) use ($locationOrder) {
                $aLoc = $a['locationID'];
                $bLoc = $b['locationID'];
                if (isset($locationOrder[$aLoc])) {
                    if (isset($locationOrder[$bLoc])) {
                        return $locationOrder[$aLoc] - $locationOrder[$bLoc];
                    }
                    return -1;
                }
                if (isset($locationOrder[$bLoc])) {
                    return 1;
                }
                return strcasecmp($a['locationDisplay'], $b['locationDisplay']);
            };
            usort($locations, $sortFunction);
        }
        return $locations;
    }

    /**
     * Get Default Pick Up Location
     *
     * Returns the default pick up location
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the pickup options
     * or may be ignored.
     *
     * @return false|string      The default pickup location for the patron or false
     * if the user has to choose.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        return $this->defaultPickUpLocation;
    }

    /**
     * Get Patron Transaction History
     *
     * This is responsible for retrieving all historical transactions
     * (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     * @param array $params Retrieval params that may contain the following keys:
     *   sort   Sorting order with date ascending or descending
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactionHistory($patron, $params)
    {
        // Do not fetch loan history if it is false or not set
        if (!($patron['loan_history'] ?? false)) {
            return [
                'count' => 0,
                'transactions' => [],
            ];
        }

        $request = [
            '$filter' => 'BorrowerId eq ' . $patron['id'],
        ];
        $result = $this->makeRequest(
            ['odata', 'BorrowerServiceHistories'],
            $request
        );
        $serviceCodeMap = [
            'Returned' => 'returnDate',
            'OnLoan' => 'checkoutDate',
            'LoanRenewed' => 'checkoutDate',
        ];
        $transactions = [];
        foreach ($result as $entry) {
            if (!($dateField = $serviceCodeMap[$entry['ServiceCode']] ?? '')) {
                continue;
            }

            $id = $entry['ServiceId'];

            if (!isset($transactions[$id])) {
                $transactions[$id] = [
                    'id' => $entry['MarcRecordId'],
                    'title' => $entry['MarcRecordTitle'] ?? '',
                ];
            }

            $actionTimestamp = strtotime($entry['ServiceTime']);
            $actionTime = $this->dateConverter->convertToDisplayDate(
                'U',
                $actionTimestamp
            );
            $transactions[$id][$dateField] = $actionTime;
            // Dates for sorting:
            $transactions[$id]["_$dateField"] = $actionTimestamp;
        }

        // Sort the list:
        $parts = explode(' ', $params['sort'] ?? '');
        $sort = $parts[0] ?? 'checkout';
        $asc = ($parts[1] ?? 'asc') === 'asc';
        usort(
            $transactions,
            function ($a, $b) use ($sort, $asc) {
                $res = 0;
                if (in_array($sort, ['checkout', 'return'])) {
                    $key = "_{$sort}Date";
                    $res = ($a[$key] ?? 0) <=> ($b[$key] ?? 0);
                }
                if (0 === $res) {
                    $res = $this->sorter
                        ->compare($a['title'] ?? '', $b['title'] ?? '');
                }
                return $asc ? $res : -$res;
            }
        );

        return [
            'count' => count($transactions),
            'transactions' => $transactions,
        ];
    }

    /**
     * Update patron's phone number
     *
     * @param array  $patron Patron array
     * @param string $phone  Phone number
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updatePhone($patron, $phone)
    {
        $code = $this->updatePatronInfo(
            $patron,
            ['MainPhone' => $phone, 'Mobile' => $phone]
        );
        if ($code !== 200) {
            return  [
                'success' => false,
                'status' => 'Changing the email address failed',
            ];
        }
        return [
            'success' => true,
            'status' => 'request_change_accepted',
        ];
    }

    /**
     * Update patron's email address
     *
     * @param array  $patron Patron array
     * @param String $email  Email address
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updateEmail($patron, $email)
    {
        $code = $this->updatePatronInfo($patron, ['MainEmail' => $email]);
        if ($code !== 200) {
            return  [
                'success' => false,
                'status' => 'Changing the email address failed',
            ];
        }
        return [
            'success' => true,
            'status' => 'request_change_accepted',
        ];
    }

    /**
     * Update patron contact information
     *
     * @param array $patron  Patron array
     * @param array $details Associative array of patron contact information
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updateAddress($patron, $details)
    {
        $map = [
            'address1' => 'MainAddrLine1',
            'address2' => 'MainAddrLine2',
            'zip' => 'MainZip',
            'city' => 'MainPlace',
        ];

        $request = [];
        foreach ($details as $field => $val) {
            if (!isset($map[$field])) {
                continue;
            }
            $field = $map[$field];
            $request[$field] = $val;
        }

        $code = $this->updatePatronInfo($patron, $request);

        if ($code != 200) {
            $message = 'An error has occurred';
            return [
                'success' => false, 'status' => $message,
            ];
        }
        $this->putCachedData($this->getPatronCacheKey($patron, 'profile'), null);
        return ['success' => true, 'status' => 'request_change_done'];
    }

    /**
     * Update Patron Transaction History State
     *
     * Enable or disable patron's transaction history
     *
     * @param array $patron The patron array from patronLogin
     * @param mixed $state  Any of the configured values
     *
     * @return array Associative array of the results
     */
    public function updateTransactionHistoryState($patron, $state)
    {
        $code = $this->updatePatronInfo(
            $patron,
            ['StoreBorrowerHistory' => $state == 1]
        );

        if ($code !== 200) {
            return  [
                'success' => false,
                'status' => 'Changing the checkout history state failed',
            ];
        }
        $this->putCachedData($this->getPatronCacheKey($patron, 'profile'), null);

        return [
            'success' => true,
            'status' => 'request_change_accepted',
            'sys_message' => '',
        ];
    }

    /**
     * Update patron messaging settings
     *
     * @param array $patron  Patron array
     * @param array $details Associative array of messaging settings
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updateMessagingSettings($patron, $details)
    {
        $settings = [];
        if (!empty($details['dueDateNotice'])) {
            $settings['RefuseReminderMessages']
                = !$details['dueDateNotice']['settings']['digest']['active'];
        }
        if (isset($details['checkoutNotice'])) {
            $settings['ReceiptMessageFormat']
                = $details['checkoutNotice']['settings']['transport_types']['value'];
        }
        if (isset($details['notifications'])) {
            $options
                = $details['notifications']['settings']['transport_types']['options']
            ;
            if (!empty($options['SMS'])) {
                $settings['LettersBySMS'] = $options['SMS']['active'];
            }
            if (!empty($options['Email'])) {
                $settings['LettersByEmail'] = $options['Email']['active'];
            }
        }

        $code = $this->updatePatronInfo($patron, $settings);

        if ($code !== 200) {
            return  [
                'success' => false,
                'status' => 'Changing the preferences failed',
            ];
        }
        $this->putCachedData($this->getPatronCacheKey($patron, 'profile'), null);

        return [
            'success' => true,
            'status' => 'request_change_accepted',
            'sys_message' => '',
        ];
    }

    /**
     * Support method for getMyFines that augments the fines with
     * extra information. The driver may also append the information
     * in getMyFines implement markOnlinePayableFines as a stub.
     *
     * The following keys are appended to each fine:
     * - payableOnline <boolean> May the fine be payed online?
     *
     * The following keys are appended when required:
     * - blockPayment <boolean> True if the fine prevents starting
     * the payment process.
     *
     * @param array $fines Processed fines.
     *
     * @return array $fines Fines.
     */
    public function markOnlinePayableFines($fines)
    {
        return $fines;
    }

    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver. Required method for any smart drivers.
     *
     * @param array $patron Patron array
     * @param array $info   Array of new profile key => value pairs
     *
     * @return int result HTTP code
     */
    protected function updatePatronInfo($patron, $info)
    {
        [$code, $result] = $this->makeRequest(
            ['odata',
             'Borrowers(' . $patron['id'] . ')'],
            json_encode($info),
            'PATCH',
            true
        );
        return $code;
    }

    /**
     * Change Password
     *
     * Attempts to change patron password (PIN code)
     *
     * @param array $details An array of patron id and old and new password:
     *
     * 'patron'      The patron array from patronLogin
     * 'oldPassword' Old password
     * 'newPassword' New password
     *
     * @return array An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function changePassword($details)
    {
        $request = [
            'NewPin' => $details['newPassword'],
            'OldPin' => $details['oldPassword'],
        ];

        [$code, $result] = $this->makeRequest(
            ['odata',
             'Borrowers(' . $details['patron']['id'] . ')',
             'Default.ChangePinCode'],
            json_encode($request),
            'POST',
            true
        );

        if ($code != 204) {
            return [
                'success' => false,
                'status' => 'authentication_error_invalid_attributes',
            ];
        }
        return ['success' => true, 'status' => 'change_password_ok'];
    }

    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver. Required method for any smart drivers.
     *
     * @param string $method The name of the called method.
     * @param array  $params Array of passed parameters
     *
     * @return bool True if the method can be called with the given parameters,
     * false otherwise.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function supportsMethod($method, $params)
    {
        if ($method == 'markFeesAsPaid') {
            return $this->supportsOnlinePayment();
        }

        // Special case: change password is only available if properly configured.
        if ($method == 'changePassword') {
            return isset($this->config['changePassword']);
        }
        return is_callable([$this, $method]);
    }

    /**
     * Return details on fees payable online.
     *
     * @param array  $patron          Patron
     * @param array  $fines           Patron's fines
     * @param ?array $selectedFineIds Selected fines
     *
     * @throws ILSException
     * @return array Associative array of payment details,
     * false if an ILSException occurred.
     */
    public function getOnlinePaymentDetails($patron, $fines, ?array $selectedFineIds)
    {
        if (empty($fines)) {
            return [
                'payable' => false,
                'amount' => 0,
                'reason' => 'online_payment_minimum_fee',
            ];
        }

        $nonPayableReason = false;
        $amount = 0;
        $allowPayment = true;
        foreach ($fines as $fine) {
            if (!$fine['payableOnline']) {
                $nonPayableReason
                    = 'online_payment_fines_contain_nonpayable_fees';
            } else {
                $amount += $fine['balance'];
            }
            if ($allowPayment && !empty($fine['blockPayment'])) {
                $allowPayment = false;
            }
        }
        $config = $this->getConfig('onlinePayment');
        if (
            !$nonPayableReason && !empty($config['minimumFee'])
            && $amount < $config['minimumFee']
        ) {
            $nonPayableReason = 'online_payment_minimum_fee';
        }

        $res = [
            'payable' => $allowPayment,
            'amount' => $amount,
        ];
        if (!$allowPayment && $nonPayableReason) {
            $res['reason'] = $nonPayableReason;
        }

        return $res;
    }

    /**
     * Mark fees as paid.
     *
     * This is called after a successful online payment.
     *
     * @param array  $patron            Patron
     * @param int    $amount            Amount to be registered as paid
     * @param string $transactionId     Transaction ID
     * @param int    $transactionNumber Internal transaction number
     * @param ?array $fineIds           Fine IDs to mark paid or null for bulk
     *
     * @throws ILSException
     * @return true|string True on success, error description on error
     */
    public function markFeesAsPaid(
        $patron,
        $amount,
        $transactionId,
        $transactionNumber,
        $fineIds = null
    ) {
        $userId = $patron['id'];

        $paymentConfig = $this->getConfig('onlinePayment');
        $fines = $this->getMyFines($patron);
        $payableFines = array_filter(
            $fines,
            function ($fine) {
                return $fine['payableOnline'];
            }
        );
        $total = array_reduce(
            $payableFines,
            function ($carry, $fine) {
                $carry += $fine['balance'];
                return $carry;
            }
        );

        if (
            $total < $amount
            || (!empty($paymentConfig['exactBalanceRequired']) && $total != $amount)
        ) {
            return 'fines_updated';
        }

        $amountLeft = $amount;
        foreach ($payableFines as $fine) {
            if ($amountLeft == 0) {
                break;
            }
            $fineId = $fine['fineId'];
            $payAmount = min($fine['balance'], $amountLeft);
            $amountLeft -= $payAmount;
            // We can only relay the internal transaction number since Mikromarc
            // only seems to accept a number.
            $request = [
                'Amount' => $payAmount / 100.0,
                'DibsTransactionId' => $transactionNumber,
                'DibsPaymentDate' => date(DATE_RFC3339_EXTENDED),
            ];

            [$code, $result] = $this->makeRequest(
                ['BorrowerDebts', $patron['cat_username'], $fineId],
                json_encode($request),
                'POST',
                true
            );
            if ($code !== 200) {
                $error = "Registration error for fine $fineId, user"
                    . " $userId (HTTP status $code): $result";
                $this->logError($error);
                throw new ILSException($error);
            }
        }

        return true;
    }

    /**
     * Check if online payment is supported and enabled
     *
     * @return bool
     */
    protected function supportsOnlinePayment()
    {
        $config = $this->getConfig('onlinePayment');
        return $config['enabled'] ?? false;
    }

    /**
     * Get request groups
     *
     * @param integer $bibId       BIB ID
     * @param array   $patronId    Patron information returned by the patronLogin
     * method.
     * @param array   $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the request group
     * options or may be ignored.
     *
     * @return array
     */
    public function getRequestGroups(
        int $bibId,
        array $patronId,
        array $holdDetails = null
    ): array {
        return [
            [
                'id'   => 'normal',
                'name' => 'mikromarc_normal',
            ],
            [
                'id'   => 'regional',
                'name' => 'mikromarc_regional',
            ],
        ];
    }

    /**
     * Get Default Request Group
     *
     * Returns the default request group
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.
     * May be used to limit the request group options or may be ignored.
     *
     * @return string       The default request group for the patron.
     */
    public function getDefaultRequestGroup(
        array $patron = [],
        array $holdDetails = null
    ): string {
        return $this->defaultRequestGroup;
    }

    /**
     * Get Item Statuses
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron information, if available
     *
     * @return array An associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    protected function getItemStatusesForBiblio($id, $patron = null)
    {
        $result = $this->makeRequest(
            ['odata', 'CatalogueItems'],
            ['$filter' => "MarcRecordId eq $id"]
        );

        if (empty($result)) {
            return [];
        }

        $statuses = [];
        $organisationTotal = [];
        foreach ($result as $i => $item) {
            $statusCode = $this->getItemStatusCode($item);
            if ($statusCode === 'Withdrawn') {
                continue;
            }

            $unitId = $item['BelongToUnitId'];
            if (!$unit = $this->getLibraryUnit($unitId)) {
                continue;
            }
            $locationName = $this->translate(
                'location_' . $unit['name'],
                null,
                $unit['name']
            );

            $available = $item['ItemStatus'] === 'AvailableForLoan';
            $organisationTotal[$unit['branch']] = [
               'reservations' => $item['ReservationQueueLength'],
            ];
            $duedate = isset($item['DueDate'])
                ? $this->formatDate(
                    $item['DueDate']
                )
                : '';
            $unit = $this->getLibraryUnit($unitId);
            $number = '';
            $shelf = $item['Shelf'];

            // Special case: detect if Shelf field has issue number information
            // (e.g. 2018:4) and put the info into number field instead
            if ($shelf && preg_match('/^\d{4}:\d+$/', $shelf) === 1) {
                $number = $shelf;
                $shelf = '';
            }
            $entry = [
                'id' => $id,
                'item_id' => $item['Id'],
                'parentId' => $unit['parent'],
                'holdings_id' => $unit['organisation'],
                'location' => $locationName,
                'organisation_id' => $unit['organisation'],
                'branch_id' => $unit['branch'],
                'availability' => $available,
                'status' => $statusCode,
                'reserve' => 'N',
                'callnumber' => $shelf,
                'duedate' => $duedate,
                'barcode' => $item['Barcode'],
                'item_notes' => [$item['notes'] ?? null],
                'number' => $number,
            ];

            if (!empty($item['LocationId'])) {
                $entry['department'] = $this->getDepartment($item['LocationId']);
            }

            if ($this->itemHoldAllowed($item) && $item['PermitLoan']) {
                $entry['is_holdable'] = true;
                if ($patron) {
                    $entry['level'] = 'copy';
                    $entry['addLink'] = !empty(
                        $this->config['Holds']['ShowLinkOnCopy']
                    );
                }
            } else {
                $entry['is_holdable'] = false;
                $entry['status'] = 'On Reference Desk';
            }

            $statuses[] = $entry;
        }

        if ($statuses) {
            foreach ($statuses as &$status) {
                $status['availabilityInfo']
                    = array_merge(
                        ['displayText' => $status['status']],
                        $organisationTotal[$status['branch_id']]
                    );
            }

            usort($statuses, [$this, 'statusSortFunction']);
        }
        return $statuses;
    }

    /**
     * Return summary of holdings items.
     *
     * @param array  $holdings Parsed holdings items
     * @param string $id       Record id
     *
     * @return array summary
     */
    protected function getHoldingsSummary($holdings, $id)
    {
        $holdable = false;
        $titleHold = true;
        $availableTotal = $itemsTotal = $orderedTotal = $reservationsTotal = 0;
        $locations = [];
        foreach ($holdings as $item) {
            if (!empty($item['availability'])) {
                $availableTotal++;
            }
            if (isset($item['availabilityInfo']['ordered'])) {
                $orderedTotal += $item['availabilityInfo']['ordered'];
            }

            $reservationsTotal = $item['availabilityInfo']['reservations'];
            $locations[$item['location']] = true;

            if ($item['is_holdable']) {
                $holdable = true;
            }
            if (!empty($item['number'])) {
                $titleHold = false;
            }
            $itemsTotal++;
        }

        // Since summary data is appended to the holdings array as a fake item,
        // we need to add a few dummy-fields that VuFind expects to be
        // defined for all elements.
        return [
            'id' => $id,
            'available' => $availableTotal,
            'ordered' => $orderedTotal,
            'total' => $itemsTotal,
            'reservations' => $reservationsTotal,
            'locations' => count($locations),
            'holdable' => $holdable,
            'availability' => null,
            'callnumber' => '',
            'location' => '__HOLDINGSSUMMARYLOCATION__',
            'groupBranches' => false,
            'titleHold' => $titleHold,
        ];
    }

    /**
     * Map Mikromarc status to VuFind.
     *
     * @param array $item Item from Mikromarc.
     *
     * @return string Status
     */
    protected function getItemStatusCode($item)
    {
        $map = [
           'AvailableForLoan' => 'Available',
           'InCourseOfAcquisition' => 'Ordered',
           'OnLoan' => 'Charged',
           'InProcess' => 'In Process',
           'Recalled' => 'Recall Request',
           'WaitingOnReservationShelf' => 'On Holdshelf',
           'AwaitingReplacing' => 'In Repair',
           'InTransitBetweenLibraries' => 'In Transit',
           'ClaimedReturnedOrNeverBorrowed' => 'Claims Returned',
           'Lost' => 'Lost--Library Applied',
           'MissingBeingTraced' => 'Lost--Library Applied',
           'AtBinding' => 'In Repair',
           'UnderRepair' => 'In Repair',
           'AwaitingTransfer' => 'In Transit',
           'MissingOverdue' => 'Overdue',
           'Withdrawn' => 'Withdrawn',
           'Discarded' => 'Withdrawn',
           'Other' => 'Not Available',
           'Unknown' => 'No information available',
           'OrderedFromAnotherLibrary' => 'In Transit',
           'DeletedInMikromarc1' => 'Withdrawn',
           'Reserved' => 'On Hold',
           'ReservedInTransitBetweenLibraries' => 'In Transit On Hold',
           'ToAcquisition' => 'In Process',
        ];

        return $map[$item['ItemStatus']] ?? 'No information available';
    }

    /**
     * Get the list of library units.
     *
     * @return array Associative array of library unit id => name pairs.
     */
    protected function getLibraryUnits()
    {
        $cacheKey = implode(
            '|',
            [
               'mikromarc', 'libraryUnits',
               $this->config['Catalog']['base'], $this->config['Catalog']['unit'],
            ]
        );

        $units = $this->getCachedData($cacheKey);

        if ($units !== null) {
            return $units;
        }

        $result = $this->makeRequest(['odata', 'LibraryUnits']);

        $units = [];
        foreach ($result as $unit) {
            $id = $unit['Id'];
            $units[$id] = [
                'id' => $id,
                'name' => $unit['Name'],
                'parent' => $unit['ParentUnitId'],
                'department' => $unit['IsDepartment'],
            ];
        }

        foreach ($units as $key => &$unit) {
            $parent = !empty($units[$unit['parent']])
                ? $units[$unit['parent']] : null;

            // Branch and organisation
            $unit['branch'] = $key;
            $organisationId = 1;
            $organisationName = null;
            if (!empty($this->config['Holdings']['organisationId'])) {
                $organisationId = $this->config['Holdings']['organisationId'];
                $organisationName
                    = $this->translator->translate("source_$organisationId");
            } elseif ($parent && $parent['department']) {
                $organisationId = $parent['parent'];
                $organisationName = $this->getLibraryUnit($parent['id'])['name'];
            }

            $unit['organisation'] = $organisationId;
            $unit['organisationName'] = $organisationName;

            if (!$unit['department'] || !$parent) {
                continue;
            }

            // Prepend parent name to department names
            $parentName = $parent['name'];
            $unitName = $unit['name'];
            if (str_starts_with(trim($unitName), trim($parentName))) {
                continue;
            }
            $unit['name'] = "$parentName - $unitName";
        }
        $this->putCachedData($cacheKey, $units, 3600);
        return $units;
    }

    /**
     * Return library unit information..
     *
     * @param int $id Unit id.
     *
     * @return array|null
     */
    protected function getLibraryUnit($id)
    {
        $units = $this->getLibraryUnits();
        return $units[$id] ?? null;
    }

    /**
     * Return library unit name.
     *
     * @param int $id Unit id.
     *
     * @return string|null
     */
    protected function getLibraryUnitName($id)
    {
        $unit = $this->getLibraryUnit($id);
        return $unit ? $unit['name'] : null;
    }

    /**
     * Get patron's blocks, if any
     *
     * @param array $patron Patron
     *
     * @return mixed        A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    protected function getPatronBlocks($patron)
    {
        if (!empty($patron['blocked'])) {
            return ['Borrowing Block Message'];
        }
        return false;
    }

    /**
     * Get cache key for patron profile.
     *
     * @param array  $patron Patron
     * @param string $action Action calling
     *
     * @return string
     */
    protected function getPatronCacheKey($patron, $action)
    {
        return "mikromarc|$action|"
            . md5(implode('|', [$patron['cat_username'], $patron['cat_password']]));
    }

    /**
     * Create a HTTP client
     *
     * @param string $url Request URL
     *
     * @return \Laminas\Http\Client
     */
    protected function createHttpClient($url)
    {
        $client = $this->httpService->createClient($url);

        if (
            isset($this->config['Http']['ssl_verify_peer_name'])
            && !$this->config['Http']['ssl_verify_peer_name']
        ) {
            $adapter = $client->getAdapter();
            if ($adapter instanceof \Laminas\Http\Client\Adapter\Socket) {
                $context = $adapter->getStreamContext();
                $res = stream_context_set_option(
                    $context,
                    'ssl',
                    'verify_peer_name',
                    false
                );
                if (!$res) {
                    throw new \Exception('Unable to set sslverifypeername option');
                }
            } elseif ($adapter instanceof \Laminas\Http\Client\Adapter\Curl) {
                $adapter->setCurlOption(CURLOPT_SSL_VERIFYHOST, false);
            }
        }

        // Set options
        $timeout = $this->config['Catalog']['http_timeout'] ?? 30;
        $connectTimeout = $this->config['Catalog']['http_connect_timeout'] ?? 10;
        $client->setOptions(
            [
                'timeout' => $timeout,
                'connect_timeout' => $connectTimeout,
                'useragent' => 'VuFind',
            ]
        );

        // Set Accept header
        $client->getRequest()->getHeaders()->addHeaderLine(
            'Accept',
            'application/json'
        );

        return $client;
    }

    /**
     * Check if an item is holdable
     *
     * @param array $item Item
     *
     * @return bool
     */
    protected function itemHoldAllowed($item)
    {
        $notAllowedForHold = isset($this->config['Holds']['notAllowedForHold'])
            ? explode(':', $this->config['Holds']['notAllowedForHold'])
            : [
                'ClaimedReturnedOrNeverBorrowed', 'Lost',
                'SuppliedReturnNotRequired', 'MissingOverDue', 'Withdrawn',
                'Discarded', 'Other',
            ];
        return in_array($item['ItemStatus'], $notAllowedForHold) ? false : true;
    }

    /**
     * Is the selected pickup location valid for the hold?
     *
     * @param string $pickUpLocation Selected pickup location
     * @param array  $patron         Patron information returned by the patronLogin
     * method.
     * @param array  $holdDetails    Details of hold being placed
     *
     * @return bool
     */
    protected function pickUpLocationIsValid($pickUpLocation, $patron, $holdDetails)
    {
        $pickUpLibs = $this->getPickUpLocations($patron, $holdDetails);
        foreach ($pickUpLibs as $location) {
            if ($location['locationID'] == $pickUpLocation) {
                return true;
            }
        }
        return false;
    }

    /**
     * Convert error message into a translation key
     *
     * @param int   $code   HTTP Result Code
     * @param array $result API Response
     *
     * @return array
     */
    protected function convertError($code, $result)
    {
        $message = 'hold_error_fail';
        if (!empty($result['error']['message'])) {
            $message = $result['error']['message'];
        } elseif (!empty($result['error']['code'])) {
            $message = $result['error']['code'];
        }

        $map = [
           'BorrowerDefaulted' => 'authentication_error_account_locked',
           'DuplicateReservationExists' => 'hold_error_already_held',
           'NoItemsAvailableByTerm' => 'hold_error_denied',
           'NoItemAvailable' => 'hold_error_denied',
           'NoTermsPermitLoanOrReservation' => 'hold_error_not_holdable',
           'ReservedForOtherBorrower' => 'renew_item_requested',
           'TermsDoNotAllowRenewal' => 'hold_error_not_holdable',
        ];

        if (isset($map[$message])) {
            $message = $map[$message];
        }

        return [
            'success' => false,
            'sysMessage' => $message,
        ];
    }

    /**
     * Make Request
     *
     * Makes a request to the Mikromarc REST API
     *
     * @param array  $hierarchy  Array of values to embed in the URL path of
     * the request
     * @param array  $params     A keyed array of query data
     * @param string $method     The http request method to use (Default is GET)
     * @param bool   $returnCode If true, returns HTTP status code in addition to
     * the result
     *
     * @throws ILSException
     * @return mixed JSON response decoded to an associative array or null on
     * authentication error
     */
    protected function makeRequest(
        $hierarchy,
        $params = false,
        $method = 'GET',
        $returnCode = false
    ) {
        // Set up the request
        $conf = $this->config['Catalog'];
        $apiUrl = $conf['host'];
        $apiUrl .= '/' . urlencode($conf['base']);
        $apiUrl .= '/' . urlencode($conf['unit']);

        // Add hierarchy
        foreach ($hierarchy as $value) {
            $apiUrl .= '/' . urlencode($value);
        }

        // Create proxy request
        $client = $this->createHttpClient($apiUrl);
        $client->setAuth($conf['username'], $conf['password']);

        // Add params
        if (false !== $params) {
            if ($method == 'GET') {
                $client->setParameterGet($params);
            } else {
                if (is_string($params)) {
                    $client->getRequest()->setContent($params);
                    $client->getRequest()->getHeaders()
                        ->addHeaderLine('Content-Type', 'application/json');
                } else {
                    $client->setParameterPost($params);
                }
            }
        } else {
            $client->setHeaders(['Content-length' => 0]);
        }

        // Send request and retrieve response
        $startTime = microtime(true);
        $client->setMethod($method);

        $page = 0;
        $data = [];
        do {
            $client->setUri($apiUrl);
            try {
                $response = $client->send();
            } catch (\Exception $e) {
                $this->logError(
                    "$method request for '$apiUrl' failed: " . $e->getMessage()
                );
                throw new ILSException('Problem with Mikromarc API.');
            }
            $result = $response->getBody();
            $this->debug(
                '[' . round(microtime(true) - $startTime, 4) . 's]'
                . " $method request $apiUrl" . PHP_EOL . 'response (status code '
                . $response->getStatusCode()
                . '): ' . PHP_EOL
                . $result
            );
            // Handle errors as complete failures only if the API call didn't return
            // valid JSON that the caller can handle
            $decodedResult = json_decode($result, true);
            if (
                !$response->isSuccess()
                && (null === $decodedResult || !empty($decodedResult['error'])
                || !empty($decodedResult['ExceptionTime']))
                && !$returnCode
            ) {
                $params = $method == 'GET'
                   ? $client->getRequest()->getQuery()->toString()
                    : $client->getRequest()->getPost()->toString();
                $this->logError(
                    "$method request for '$apiUrl' with params"
                    . "'$params' and contents '"
                    . $client->getRequest()->getContent() . "' failed: "
                    . $response->getStatusCode() . ': '
                    . $response->getReasonPhrase()
                    . ', response content: ' . $response->getBody()
                );
                throw new ILSException('Problem with Mikromarc REST API.');
            }

            $resultData = $decodedResult['value'] ?? $decodedResult;
            if ($page === 0) {
                $data = $resultData;
            } else {
                $data = array_merge($data, $resultData);
            }

            // More results available?
            $nextLink = $decodedResult['@odata.nextLink'] ?? '';
            if (!$nextLink) {
                break;
            }
            // Fix http => https
            if (
                strncmp($apiUrl, 'https:', 6) === 0
                && strncmp($nextLink, 'http:', 5) === 0
            ) {
                $nextLink = 'https:' . substr($nextLink, 5);
            }

            // At least with LibraryUnits, Mikromarc may repeat the same link over
            // and over again. Try to fix.
            if ($apiUrl === $nextLink) {
                if (is_array($data)) {
                    $nextLink = preg_replace(
                        '/\$skip=(\d+)/',
                        '$skip=' . count($data),
                        $nextLink
                    );
                }
                if ($apiUrl === $nextLink) {
                    $this->logError('Could not rewrite $skip parameter');
                    break;
                }
            }

            $client->setParameterGet([]);
            $client->setParameterPost([]);
            $apiUrl = $nextLink;
            $page++;
        } while ($page < 100); // safety valve

        return $returnCode ? [$response->getStatusCode(), $data] : $data;
    }

    /**
     * Status item sort function
     *
     * @param array $a First status record to compare
     * @param array $b Second status record to compare
     *
     * @return int
     */
    protected function statusSortFunction($a, $b)
    {
        $key = 'parentId';

        $sortOrder = $this->holdingsOrganisationOrder;
        $orderA = in_array($a[$key], $sortOrder)
            ? array_search($a[$key], $sortOrder) : null;
        $orderB = in_array($b[$key], $sortOrder)
            ? array_search($b[$key], $sortOrder) : null;

        if ($orderA !== null) {
            if ($orderB !== null) {
                $posA = array_search($a[$key], $sortOrder);
                $posB = array_search($b[$key], $sortOrder);
                return $posA - $posB;
            }
            return -1;
        }
        if ($orderB !== null) {
            return 1;
        }
        return strcmp($a['location'], $b['location']);
    }

    /**
     * Fetch name of the department where the shelf is located
     *
     * @param int $locationId Id of the shelf
     *
     * @return string
     */
    public function getDepartment($locationId)
    {
        static $cacheDepartment = [];
        if (!isset($cacheDepartment[$locationId])) {
            $request = [
                '$filter' => "Id eq $locationId",
            ];
            $cacheDepartment[$locationId] = $this->makeRequest(
                ['odata', 'CatalogueItemLocations'],
                $request
            );
        }
        return $cacheDepartment[$locationId][0]['Name'];
    }

    /**
     * Format date
     *
     * @param string $dateString Date as a string
     *
     * @return string Formatted date
     */
    protected function formatDate($dateString)
    {
        // Ignore timezone and time, otherwise CatalogueItems
        // and BorrowerLoans api calls give different due dates for
        // the same item
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $dateString, $matches)) {
            return $this->dateConverter->convertToDisplayDate(
                'Y-m-d',
                $matches[1]
            );
        }

        return $this->dateConverter->convertToDisplayDate('Y-m-d', $dateString);
    }

    /**
     * Check if request is valid
     *
     * This is responsible for determining if an item is requestable
     *
     * @param string $id     The Bib ID
     * @param array  $data   An Array of item data
     * @param patron $patron An array of patron data
     *
     * @return bool True if request is valid, false if not
     */
    public function checkRequestIsValid($id, $data, $patron)
    {
        if ('title' === $data['level']) {
            $holding = $this->getHolding($id);
            $summary = array_pop($holding);
            if (
                (isset($summary['titleHold']) && $summary['titleHold'] === false)
                || (isset($summary['holdable']) && !$summary['holdable'])
            ) {
                return false;
            }
        }
        return true;
    }
}
