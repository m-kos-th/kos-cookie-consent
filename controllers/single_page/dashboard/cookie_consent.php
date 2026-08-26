<?php

namespace Concrete\Package\KosCookieConsent\Controller\SinglePage\Dashboard;

use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Package\KosCookieConsent\Src\Service\CookieConsentService;

defined('C5_EXECUTE') or die('Access Denied.');

class CookieConsent extends DashboardPageController
{
    public $kosdesign;
    public $db;

    public function on_start()
    {
        parent::on_start();

        $this->kosdesign = $this->app->make(CookieConsentService::class);
        $this->db = $this->kosdesign->getConnection();
        $this->kosdesign->setLink();
    }

    public function view()
    {
        return $this->redirect('/dashboard/cookie_consent/logs');
    }
}
