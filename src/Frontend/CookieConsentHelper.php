<?php

namespace Concrete\Package\KosCookieConsent\Src\Frontend;

use Concrete\Core\Application\Application;
use Concrete\Package\KosCookieConsent\Src\Service\CookieConsentService;

class CookieConsentHelper
{
    const CONSENT_ID_COOKIE = 'kos_cookie_consent_id';

    protected $app;
    protected $cookieConsent;

    public function __construct(Application $app, CookieConsentService $cookieConsent)
    {
        $this->app = $app;
        $this->cookieConsent = $cookieConsent;
    }

    public function getIPAddress()
    {
        $ip = $this->app->make('ip');

        return $ip->getRequestIPAddress()->toString();
    }

    public function getCookieList($id = null)
    {
        return $this->cookieConsent->DBSelectCookieConsent($id);
    }

    public function getConsentId()
    {
        $request = $this->app->make('request');
        $consentId = $request->cookies->get(self::CONSENT_ID_COOKIE);
        if (!is_scalar($consentId)) {
            return null;
        }

        $consentId = trim((string) $consentId);

        return $consentId !== '' ? $consentId : null;
    }

    public function getPopupCopy(array $defaults = [])
    {
        return $this->cookieConsent->getPopupCopy($defaults);
    }

    public function getCurrentSiteId()
    {
        $section = $this->cookieConsent->getCurrentSection();
        if (!$section) {
            return null;
        }

        $sectionId = (int) $section->getCollectionID();
        foreach ($this->cookieConsent->getSiteLanguage() as $language) {
            if ((int) $language['page_id'] === $sectionId) {
                return (int) $language['site_id'];
            }
        }

        return null;
    }

    public function getCookieLog($consentId = null, $siteid = null, $consentVersion = null)
    {
        if ($consentId === null) {
            $consentId = $this->getConsentId();
        }

        $consentId = trim((string) $consentId);
        if ($consentId === '') {
            return [];
        }

        if ($siteid === null) {
            return $this->cookieConsent->DBSelectLogByConsentId($consentId, $consentVersion);
        }

        if ($consentVersion === null) {
            $consentVersion = $this->cookieConsent->getConsentVersion($siteid);
        }

        return $this->cookieConsent->DBSelectLogByConsentIdV2($consentId, $siteid, $consentVersion);
    }

    public function getTackingCode($siteid = null)
    {
        $consentId = $this->getConsentId();
        $result = $this->getCookieLog($consentId, $siteid);

        return !empty($result['code']) ? $result['code'] : null;
    }

    public function getTrackingCode($siteid = null)
    {
        return $this->getTackingCode($siteid);
    }

    public function getTrackingCodeByPosition($siteid = null)
    {
        $trackingCode = $this->getTrackingCode($siteid);
        $result = [
            'header' => [],
            'body' => [],
            'footer' => [],
        ];

        foreach (array_keys($result) as $position) {
            if (empty($trackingCode[$position]) || !is_array($trackingCode[$position])) {
                continue;
            }

            foreach ($trackingCode[$position] as $code) {
                $code = trim((string) $code);
                if ($code !== '') {
                    $result[$position][] = $code;
                }
            }
        }

        return $result;
    }

    public function hasSavedConsent($siteid = null)
    {
        $log = $this->getCookieLog($this->getConsentId(), $siteid);

        return !empty($log['saved']);
    }

    public function getConsentCookieLifetimeDays()
    {
        return $this->cookieConsent->getConsentCookieLifetimeDays();
    }

    public function getConsentCookieLifetimeSeconds()
    {
        return $this->cookieConsent->getConsentCookieLifetimeSeconds();
    }

    public function generateToken($name = null)
    {
        if ($name === null) {
            return null;
        }

        return $this->app->make('token')->generate($name);
    }

    public function outputToken($name = null)
    {
        if ($name === null) {
            return '';
        }

        return '<input type="hidden" name="_token" value="' . h($this->generateToken($name)) . '">';
    }
}
