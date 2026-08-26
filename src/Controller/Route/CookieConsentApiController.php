<?php

namespace Concrete\Package\KosCookieConsent\Src\Controller\Route;

use Concrete\Core\Application\Application;
use Concrete\Package\KosCookieConsent\Src\Service\CookieConsentService;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;

class CookieConsentApiController
{
    const TOKEN_ACTION = 'kos_cookie_consent_frontend';
    const CONSENT_ID_COOKIE = 'kos_cookie_consent_id';

    protected $app;
    protected $cookieConsent;

    public function __construct(Application $app, CookieConsentService $cookieConsent)
    {
        $this->app = $app;
        $this->cookieConsent = $cookieConsent;
    }

    public function save()
    {
        $data = $this->getRequestData();
        $errors = $this->validateToken($data);
        $consentId = $this->resolveConsentId($data);
        $cookieMaxAge = $this->cookieConsent->getConsentCookieLifetimeSeconds();

        if (!empty($errors)) {
            return $this->jsonResponse(['message' => t('Validation error.'), 'error' => $errors], 400);
        }

        try {
            $payload = $this->buildPayload($data, null, $consentId);
            if (!$this->cookieConsent->DBInsertLog($payload)) {
                return $this->jsonResponse(['message' => t('Save failed.'), 'error' => [t('Cookie consent cannot save.')]], 500);
            }

            $response = $this->jsonResponse([
                'status' => 1,
                'message' => t('Saved.'),
                'consent_id' => $consentId,
                'cookie_max_age' => $cookieMaxAge,
            ], 200);

            return $this->addConsentCookies($response, $consentId);
        } catch (\Throwable $e) {
            return $this->jsonResponse(['message' => t('Server error.'), 'exception' => $e->getMessage()], 500);
        }
    }

    public function saveV2()
    {
        $data = $this->getRequestData();
        $errors = $this->validateToken($data);
        $siteId = $this->getSiteId($data);
        $consentId = $this->resolveConsentId($data);

        if ($siteId <= 0) {
            $errors[] = t('Site is required.');
        }

        if (!empty($errors)) {
            return $this->jsonResponse(['message' => t('Validation error.'), 'error' => $errors], 400);
        }

        try {
            $consentVersion = $this->cookieConsent->getConsentVersion($siteId);
            $cookieMaxAge = $this->cookieConsent->getConsentCookieLifetimeSeconds();
            $managedCookieCleanup = $this->cookieConsent->getManagedCookieCleanupMap($siteId);
            $payload = $this->buildPayload($data, $siteId, $consentId);
            $payload['consent_version'] = $consentVersion;
            if (!$this->cookieConsent->DBInsertLogV2($payload)) {
                return $this->jsonResponse(['message' => t('Save failed.'), 'error' => [t('Cookie consent cannot save.')]], 500);
            }

            $code = [
                'header' => [],
                'body' => [],
                'footer' => [],
            ];
            $log = $this->cookieConsent->DBSelectLogByConsentIdV2($payload['consent_id'], $siteId, $consentVersion);
            if (!empty($log['code'])) {
                $code['header'] = !empty($log['code']['header']) ? $log['code']['header'] : [];
                $code['body'] = !empty($log['code']['body']) ? $log['code']['body'] : [];
                $code['footer'] = !empty($log['code']['footer']) ? $log['code']['footer'] : [];
            }

            $response = $this->jsonResponse([
                'status' => 1,
                'message' => t('Saved.'),
                'saved' => !empty($log['saved']),
                'consent_id' => $payload['consent_id'],
                'version' => $consentVersion,
                'consent' => !empty($log['code']['id']) ? array_map('intval', $log['code']['id']) : [],
                'code' => $code,
                'cookie_max_age' => $cookieMaxAge,
                'managed_cookie_cleanup' => $managedCookieCleanup,
            ], 200);

            return $this->addConsentCookies($response, $payload['consent_id'], $consentVersion, !empty($log['saved']));
        } catch (\Throwable $e) {
            return $this->jsonResponse(['message' => t('Server error.'), 'exception' => $e->getMessage()], 500);
        }
    }

    public function stateV2()
    {
        $data = $this->getRequestData();
        $siteId = $this->getSiteId($data);

        if ($siteId <= 0) {
            return $this->jsonResponse(['message' => t('Validation error.'), 'error' => [t('Site is required.')]], 400);
        }

        $code = [
            'header' => [],
            'body' => [],
            'footer' => [],
        ];
        $consentVersion = $this->cookieConsent->getConsentVersion($siteId);
        $cookieMaxAge = $this->cookieConsent->getConsentCookieLifetimeSeconds();
        $managedCookieCleanup = $this->cookieConsent->getManagedCookieCleanupMap($siteId);
        $requestedConsentId = $this->getConsentId($data);
        $consentId = $requestedConsentId ?: $this->generateConsentId();
        $log = $this->cookieConsent->DBSelectLogByConsentIdV2($consentId, $siteId, $consentVersion);

        if (empty($log['saved']) && $this->isFreshConsentId($data)) {
            $legacyLog = $this->cookieConsent->adoptLegacyLogByConsentIdV2($consentId, $this->getIPAddress(), $siteId, $consentVersion);
            if (!empty($legacyLog)) {
                $log = $legacyLog;
            }
        }

        if (!empty($log['code'])) {
            $code['header'] = !empty($log['code']['header']) ? $log['code']['header'] : [];
            $code['body'] = !empty($log['code']['body']) ? $log['code']['body'] : [];
            $code['footer'] = !empty($log['code']['footer']) ? $log['code']['footer'] : [];
        }

        $response = $this->jsonResponse([
            'status' => 1,
            'saved' => !empty($log['saved']),
            'consent_id' => $consentId,
            'version' => $consentVersion,
            'consent' => !empty($log['code']['id']) ? array_map('intval', $log['code']['id']) : [],
            'token' => $this->app->make('token')->generate(self::TOKEN_ACTION),
            'code' => $code,
            'cookie_max_age' => $cookieMaxAge,
            'managed_cookie_cleanup' => $managedCookieCleanup,
        ], 200);

        return $this->addConsentCookies($response, $consentId, !empty($log['saved']) ? $consentVersion : null, !empty($log['saved']));
    }

    protected function getRequestData()
    {
        $request = $this->app->make('request');
        $query = $request->query->all();
        $post = $request->request->all();
        $rawQuery = [];
        $rawBody = [];

        parse_str((string) $request->getQueryString(), $rawQuery);
        parse_str((string) $request->getContent(), $rawBody);

        return array_merge(
            is_array($query) ? $query : [],
            is_array($rawQuery) ? $rawQuery : [],
            is_array($rawBody) ? $rawBody : [],
            is_array($post) ? $post : []
        );
    }

    protected function validateToken(array $data)
    {
        $token = $this->app->make('token');
        $tokenValue = '';
        if (isset($data['_token']) && is_scalar($data['_token'])) {
            $tokenValue = trim((string) $data['_token']);
        } elseif (isset($data['ccm_token']) && is_scalar($data['ccm_token'])) {
            $tokenValue = trim((string) $data['ccm_token']);
        }

        foreach ([self::TOKEN_ACTION, 'consent_bar', 'consent_setting'] as $action) {
            if ($tokenValue !== '' && $token->validate($action, $tokenValue)) {
                return [];
            }
        }

        if ($this->isSameOriginFrontendRequest()) {
            return [];
        }

        return [$token->getErrorMessage() ?: t('Missing token.')];
    }

    protected function buildPayload(array $data, $siteId = null, $consentId = null)
    {
        $consent = $this->normalizeConsentData($data);
        $payload = [
            'consent_id' => $consentId ?: $this->resolveConsentId($data),
            'ip' => $this->getIPAddress(),
            'data' => [],
        ];

        if ($siteId !== null) {
            $payload['site_id'] = (int) $siteId;
        }

        $cookies = $siteId !== null ? $this->cookieConsent->getActiveCookiesBySite($siteId) : $this->cookieConsent->DBSelectCookieConsent();
        foreach ($cookies as $cookie) {
            $cookieId = (int) $cookie['id'];
            if (!empty($cookie['always']) || (isset($consent[$cookieId]) && (string) h($consent[$cookieId]) === '1')) {
                $payload['data'][] = $cookieId;
            }
        }

        return $payload;
    }

    protected function getConsentId(array $data = [])
    {
        $request = $this->app->make('request');

        foreach ([
            $request->query->get('consent_id'),
            $request->query->get('consentId'),
            $request->request->get('consent_id'),
            $request->request->get('consentId'),
            isset($_GET['consent_id']) ? $_GET['consent_id'] : null,
            isset($_GET['consentId']) ? $_GET['consentId'] : null,
            isset($_POST['consent_id']) ? $_POST['consent_id'] : null,
            isset($_POST['consentId']) ? $_POST['consentId'] : null,
        ] as $candidate) {
            $normalized = $this->normalizeConsentId($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        foreach (['consent_id', 'consentId'] as $key) {
            if (isset($data[$key])) {
                $normalized = $this->normalizeConsentId($data[$key]);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        $cookieValue = $request->cookies->get(self::CONSENT_ID_COOKIE);

        return $this->normalizeConsentId($cookieValue);
    }

    protected function resolveConsentId(array $data = [])
    {
        $consentId = $this->getConsentId($data);
        if ($consentId !== null) {
            return $consentId;
        }

        return $this->generateConsentId();
    }

    protected function normalizeConsentId($consentId)
    {
        if (!is_scalar($consentId)) {
            return null;
        }

        $consentId = trim((string) $consentId);
        if ($consentId === '' || !preg_match('/^[A-Za-z0-9_-]{16,128}$/', $consentId)) {
            return null;
        }

        return $consentId;
    }

    protected function isFreshConsentId(array $data = [])
    {
        foreach (['consent_id_fresh', 'consentIdFresh'] as $key) {
            if (!isset($data[$key]) || !is_scalar($data[$key])) {
                continue;
            }

            return (string) $data[$key] === '1';
        }

        return false;
    }

    protected function generateConsentId()
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            return substr(sha1(uniqid((string) mt_rand(), true)), 0, 32);
        }
    }

    protected function jsonResponse(array $data, $status = 200)
    {
        $response = new JsonResponse($data, (int) $status);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    protected function addConsentCookies(JsonResponse $response, $consentId = null, $version = null, $markSaved = false)
    {
        $request = $this->app->make('request');
        $maxAge = $this->cookieConsent->getConsentCookieLifetimeSeconds();
        $expiresAt = time() + $maxAge;
        $secure = $request->isSecure();

        $consentId = $this->normalizeConsentId($consentId);
        if ($consentId !== null) {
            $response->headers->setCookie(new Cookie(self::CONSENT_ID_COOKIE, $consentId, $expiresAt, '/', null, $secure, false, false, Cookie::SAMESITE_LAX));
        }

        if ($markSaved) {
            $response->headers->setCookie(new Cookie('consent', 'true', $expiresAt, '/', null, $secure, false, false, Cookie::SAMESITE_LAX));

            $version = is_scalar($version) ? trim((string) $version) : '';
            if ($version !== '') {
                $response->headers->setCookie(new Cookie('consent_version', $version, $expiresAt, '/', null, $secure, false, false, Cookie::SAMESITE_LAX));
            }
        }

        return $response;
    }

    protected function normalizeConsentData(array $data)
    {
        $consent = [];

        if (isset($data['consent']) && is_array($data['consent'])) {
            foreach ($data['consent'] as $id => $value) {
                if ((string) $value === '1') {
                    $consent[(int) $id] = '1';
                }
            }
        }

        foreach ($data as $key => $value) {
            if (!is_scalar($key) || !is_scalar($value)) {
                continue;
            }

            if (preg_match('/^consent\[(\d+)\]$/', (string) $key, $matches) && (string) $value === '1') {
                $consent[(int) $matches[1]] = '1';
            }
        }

        return $consent;
    }

    protected function getSiteId(array $data = [])
    {
        foreach (['site_id', 'siteId'] as $key) {
            if (isset($data[$key]) && is_scalar($data[$key]) && (int) $data[$key] > 0) {
                return (int) $data[$key];
            }
        }

        $request = $this->app->make('request');
        $siteId = $request->get('site_id');
        if (is_scalar($siteId) && (int) $siteId > 0) {
            return (int) $siteId;
        }

        if (isset($_REQUEST['site_id']) && is_scalar($_REQUEST['site_id']) && (int) $_REQUEST['site_id'] > 0) {
            return (int) $_REQUEST['site_id'];
        }

        $section = $this->cookieConsent->getCurrentSection();
        if (!$section) {
            return 0;
        }

        $sectionId = (int) $section->getCollectionID();
        foreach ($this->cookieConsent->getSiteLanguage() as $language) {
            if ((int) $language['page_id'] === $sectionId) {
                return (int) $language['site_id'];
            }
        }

        return 0;
    }

    protected function isSameOriginFrontendRequest()
    {
        $request = $this->app->make('request');
        $host = parse_url((string) $request->getSchemeAndHttpHost(), PHP_URL_HOST);

        foreach (['Origin', 'Referer'] as $header) {
            $value = (string) $request->headers->get($header);
            if ($value === '') {
                continue;
            }

            $headerHost = parse_url($value, PHP_URL_HOST);
            if ($host !== null && $headerHost !== null && strtolower($host) === strtolower($headerHost)) {
                return true;
            }

            return false;
        }

        return true;
    }

    protected function getIPAddress()
    {
        $ip = $this->app->make('ip');

        return $ip->getRequestIPAddress()->toString();
    }
}
