<?php

/**
 * VuFind Bootstrapper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2022.
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
 * @package  Bootstrap
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna;

use Laminas\Mvc\MvcEvent;

use function in_array;

/**
 * VuFind Bootstrapper
 *
 * @category VuFind
 * @package  Bootstrap
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Bootstrapper
{
    /**
     * Main VuFind configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config = null;

    /**
     * Current MVC event
     *
     * @var MvcEvent
     */
    protected $event;

    /**
     * Event manager
     *
     * @var \Laminas\EventManager\EventManagerInterface
     */
    protected $events;

    /**
     * Constructor
     *
     * @param MvcEvent $event Laminas MVC Event object
     */
    public function __construct(MvcEvent $event)
    {
        $this->event = $event;
        $this->events = $event->getApplication()->getEventManager();
        $sm = $this->event->getApplication()->getServiceManager();
        $this->config = $sm->get(\VuFind\Config\PluginManager::class)->get('config');
    }

    /**
     * Bootstrap all necessary resources.
     *
     * @return void
     */
    public function bootstrap()
    {
        // automatically call all methods starting with "init":
        $methods = get_class_methods($this);
        foreach ($methods as $method) {
            if (substr($method, 0, 4) == 'init') {
                $this->$method();
            }
        }
    }

    /**
     * Set up bot check that disallows access to some functions from bots
     *
     * @return void
     */
    protected function initBotCheck()
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $callback = function ($event) {
            // Check User-Agent
            $headers = $event->getRequest()->getHeaders();
            if (!$headers->has('User-Agent')) {
                return;
            }
            $agent = $headers->get('User-Agent')->getFieldValue();
            $crawlerDetect = new \Jaybizzle\CrawlerDetect\CrawlerDetect();
            if (!$crawlerDetect->isCrawler($agent)) {
                return;
            }
            // Check if the action should be prevented
            $ajaxAllowed = [
                'onlinepaymentnotify',
                'systemstatus',
            ];
            $routeMatch = $event->getRouteMatch();
            $controller = strtolower($routeMatch->getParam('controller'));
            $action = strtolower($routeMatch->getParam('action'));
            $request = $event->getRequest();
            $view = $request->getPost('view') ?? $request->getQuery('view');
            if (
                ($controller == 'ajax' && !in_array($action, $ajaxAllowed))
                || ($controller == 'browse')
                || ($controller == 'browsesearch')
                || ($controller == 'l1' && $action == 'results' && $view !== 'rss')
                || ($controller == 'l1record' && $action == 'ajaxtab')
                || ($controller == 'myresearch')
                || ($controller == 'record' && $action == 'ajaxtab')
                || ($controller == 'record' && $action == 'holdings')
                || ($controller == 'record' && $action == 'details')
                || ($controller == 'record' && $action == 'downloadfile')
                || ($controller == 'record' && $action == 'map')
                || ($controller == 'record' && $action == 'usercomments')
                || ($controller == 'record' && $action == 'similar')
                || ($controller == 'record2' && $action == 'ajaxtab')
                || ($controller == 'qrcode')
                || ($controller == 'oai')
                || ($controller == 'authority' && $action == 'search')
                || ($controller == 'search' && $action == 'results' && $view !== 'rss')
                || ($controller == 'search2' && $action == 'results' && $view !== 'rss')
                || ($controller == 'pci' && $action == 'search')
                || ($controller == 'primo' && $action == 'search')
                || ($controller == 'primorecord')
                || ($controller == 'eds' && $action == 'search')
                || ($controller == 'edsrecord')
                || ($controller == 'blender' && $action == 'results')
                || ($controller == 'cover' && $action == 'download')
            ) {
                $response = $event->getResponse();
                $response->setStatusCode(403);
                $response->setContent('Forbidden');
                $event->stopPropagation(true);
                return $response;
            }
        };

        // Attach with a high priority
        $this->events->attach('dispatch', $callback, 12000);
    }

    /**
     * Set up Suomifi login listener.
     *
     * @return void
     */
    protected function initSuomifiLoginListener()
    {
        if (!$this->isR2Enabled()) {
            return;
        }
        $sm = $this->event->getApplication()->getServiceManager();
        $callback = function ($event) use ($sm) {
            // Open REMS registration form after Suomifi login
            $lightboxUrl = ($sm->get('ViewHelperManager')->get('url'))(
                'r2feedback-form',
                ['id' => 'R2Register']
            );

            $followup = $sm->get(\Laminas\Mvc\Controller\PluginManager::class)
                ->get(\VuFind\Controller\Plugin\Followup::class);

            $followup->store(
                ['postLoginLightbox' => $lightboxUrl],
                $followup->retrieve('url', '')
            );
        };

        $sm->get('SharedEventManager')->attach(
            'Finna\Auth\Suomifi',
            \Finna\Auth\Suomifi::EVENT_LOGIN,
            $callback
        );
    }

    /**
     * Set up Suomifi logout listener.
     *
     * @return void
     */
    protected function initSuomifiLogoutListener()
    {
        if (!$this->isR2Enabled()) {
            return;
        }
        $sm = $this->event->getApplication()->getServiceManager();
        $callback = function ($event) use ($sm) {
            $rems = $sm->get(\Finna\Service\RemsService::class);
            $rems->onLogout();
        };

        $sm->get('SharedEventManager')->attach(
            'Finna\Auth\Suomifi',
            \Finna\Auth\Suomifi::EVENT_LOGOUT,
            $callback
        );
    }

    /**
     * Set up REMS registration listener.
     *
     * @return void
     */
    protected function initRemsRegistrationListener()
    {
        if (!$this->isR2Enabled()) {
            return;
        }
        $sm = $this->event->getApplication()->getServiceManager();
        $callback = function ($event) use ($sm) {
            $params = $event->getParams();
            if ($remsUserId = ($params['user'] ?? null)) {
                $table = $sm->get(\VuFind\Db\Table\PluginManager::class)
                    ->get('ExternalSession');
                $sessionId
                    = $sm->get(\Laminas\Session\SessionManager::class)->getId();
                $table->addSessionMapping("{$sessionId}REMS", $remsUserId);
            }
        };

        $sm->get('SharedEventManager')->attach(
            'Finna\Service\RemsService',
            \Finna\Service\RemsService::EVENT_USER_REGISTERED,
            $callback
        );
    }

    /**
     * Set up REMS session expiration warning listener.
     *
     * @return void
     */
    protected function initRemsSessionExpirationWarningListener()
    {
        if (!$this->isR2Enabled()) {
            return;
        }
        $sm = $this->event->getApplication()->getServiceManager();
        $callback = function ($event) use ($sm) {
            $session = new \Laminas\Session\Container(
                \Finna\View\Helper\Root\SystemMessages::SESSION_NAME,
                $sm->get(\Laminas\Session\SessionManager::class)
            );
            $messages = $session['messages'] ?? [];

            $key = 'R2_session_expiring';
            unset($session->messages[$key]);

            $expirationTime
                = $sm->get(\Finna\Service\RemsService::class)
                ->getSessionExpirationTime();
            if ($expirationTime) {
                // Add warning to session variable.
                // The message is displayed by SystemMessages
                $format = 'H:i';
                $time = $sm->get(\VuFind\Date\Converter::class)
                    ->convertToDisplayDateAndTime(
                        $format,
                        date($format, $expirationTime->getTimeStamp())
                    );
                $messages[$key] = ['%%expire%%' => $time];
                $session->messages = $messages;
            }
        };

        $this->events->attach('dispatch', $callback, 9000);
    }

    /**
     * Set up statistics event handler
     *
     * N.B. The event handler may have already been created by the database row
     * session factory to ensure proper hookup before session events.
     *
     * @return void
     */
    protected function initStatisticsEventHandler()
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $sm = $this->event->getApplication()->getServiceManager();
        $callback = function ($event) use ($sm) {
            if (!($routeMatch = $event->getRouteMatch())) {
                return;
            }
            $controller = strtolower($routeMatch->getParam('controller'));
            $action = strtolower($routeMatch->getParam('action'));
            if (!in_array($controller, ['cover', 'qrcode'])) {
                if ('ajax' === $controller) {
                    if ('json' !== $action || !($request = $event->getRequest())) {
                        return;
                    }
                    $method = $request->getPost('method')
                        ?? $request->getQuery('method');
                    if (!in_array($method, ['getImageInformation'])) {
                        return;
                    }
                    $action .= "/$method";
                }
                if ('ajaxtab' === $action && $request = $event->getRequest()) {
                    $tab = $request->getPost('tab') ?? $request->getQuery('tab');
                    if ($tab) {
                        $action .= "/$tab";
                    }
                }
                $sm->get(\Finna\Statistics\EventHandler::class)
                    ->pageView($controller, $action);
            }
        };
        $this->events->attach('dispatch', $callback, 9000);
    }

    /**
     * Check if R2 search is enabled.
     *
     * @return bool
     */
    protected function isR2Enabled()
    {
        $sm = $this->event->getApplication()->getServiceManager();
        $r2Config = $sm->get(\VuFind\Config\PluginManager::class)->get('R2');
        return $r2Config->R2->enabled ?? false;
    }
}
