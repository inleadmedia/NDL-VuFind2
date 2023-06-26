<?php

namespace VuFind\AjaxHandler;

use Finna\Wayfinder\WayfinderService;
use Laminas\Mvc\Controller\Plugin\Params;

class WayfinderPlacementLinkLookup extends AbstractBase
{
  protected $wayfinderService;

  public function __construct(WayfinderService $wayfinderService)
  {
    $this->wayfinderService = $wayfinderService;
  }

  public function handleRequest(Params $params)
  {
    return $this->formatResponse('OK');
  }
}
