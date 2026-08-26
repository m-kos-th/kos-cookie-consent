<?php

namespace Concrete\Package\KosCookieConsent\Controller\SinglePage\Dashboard\CookieConsent;

use Concrete\Core\Editor\LinkAbstractor;
use Concrete\Core\User\EditResponse as UserEditResponse;
use Concrete\Package\KosCookieConsent\Controller\SinglePage\Dashboard\CookieConsent;
use Concrete\Package\KosCookieConsent\Src\Service\CookieConsentService;
use Exception;

defined('C5_EXECUTE') or die('Access Denied.');

class Setting extends CookieConsent
{
    public function view()
    {
        $data = $this->request->query->all();
        $siteid = !empty($data['site_id']) ? $data['site_id'] : null;
        $max = 20;
        $current = 1;
        $count = $this->kosdesign->DBCountCookieV2($siteid);
        $limit = (int) ceil($count / $max);

        if (!empty($data['page']) && $data['page'] <= 1) {
            $current = 1;
        } elseif (!empty($data['page']) && $limit > 0 && $data['page'] >= $limit) {
            $current = $limit;
        } elseif (!empty($data['page'])) {
            $current = (int) $data['page'];
        }

        $result = $this->kosdesign->DBSelectCookieV2($current, $max, $siteid);

        $this->set('_post', $data);
        $this->set('result', $result);
        $this->set('pages', $limit);
        $this->set('current', $current);
        $this->set('_tokenSort', $this->token->generate('cookie_consent_sort'));
        $this->set('_tokenDelete', $this->token->generate('cookie_consent_delete'));
        $this->set('_tokenSettings', $this->token->generate('cookie_consent_settings'));
        $this->set('_consentCookieSettings', $this->kosdesign->getConsentCookieSettings());
    }

    public function save_preferences()
    {
        $token = $this->post('token');
        if (!$this->token->validate('cookie_consent_settings', $token)) {
            $this->flash('error', $this->token->getErrorMessage());

            return $this->redirect('/dashboard/cookie_consent/setting');
        }

        $lifetimeDays = trim((string) $this->post('consent_cookie_lifetime_days'));
        if (!preg_match('/^\d+$/', $lifetimeDays)) {
            $this->flash('error', t('Consent cookie retention must be a whole number of days.'));

            return $this->redirect('/dashboard/cookie_consent/setting');
        }

        $lifetimeDays = (int) $lifetimeDays;
        if ($lifetimeDays < CookieConsentService::MIN_CONSENT_COOKIE_LIFETIME_DAYS || $lifetimeDays > CookieConsentService::MAX_CONSENT_COOKIE_LIFETIME_DAYS) {
            $this->flash(
                'error',
                t(
                    'Consent cookie retention must be between %s and %s days.',
                    CookieConsentService::MIN_CONSENT_COOKIE_LIFETIME_DAYS,
                    CookieConsentService::MAX_CONSENT_COOKIE_LIFETIME_DAYS
                )
            );

            return $this->redirect('/dashboard/cookie_consent/setting');
        }

        try {
            $this->kosdesign->saveConsentCookieLifetimeDays($lifetimeDays);
            $this->flash('success', t('Consent cookie retention saved.'));
        } catch (\Throwable $e) {
            $this->flash('error', t('Unable to save consent cookie retention right now.'));
        }

        return $this->redirect('/dashboard/cookie_consent/setting');
    }

    public function save_sort_order()
    {
        $ids = $this->post('ids');
        $siteId = (int) $this->post('site_id');
        $token = $this->post('token');

        if (!$this->token->validate('cookie_consent_sort', $token)) {
            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'status' => 0,
                'message' => $this->token->getErrorMessage(),
            ], 400);
        }

        if (!is_array($ids) || count($ids) === 0) {
            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'status' => 0,
                'message' => t('No rows to sort.'),
            ], 400);
        }

        $normalizedIds = [];
        foreach ($ids as $id) {
            $normalizedId = (int) $id;
            if ($normalizedId > 0) {
                $normalizedIds[] = $normalizedId;
            }
        }

        if (count($normalizedIds) === 0) {
            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'status' => 0,
                'message' => t('No valid rows to sort.'),
            ], 400);
        }

        if ($this->kosdesign->DBUpdateCookieSortOrder($normalizedIds, $siteId > 0 ? $siteId : null)) {
            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'status' => 1,
                'message' => t('Sort order updated.'),
            ]);
        }

        return new \Symfony\Component\HttpFoundation\JsonResponse([
            'status' => 0,
            'message' => t('Unable to update sort order.'),
        ], 500);
    }

    public function add()
    {
        $this->set('_type', 'add');
        $this->set('_token', $this->token->generate('cookie_consent_add'));
    }

    public function edit($id = null)
    {
        $this->set('_type', 'edit');
        $this->set('_token', $this->token->generate('cookie_consent_edit'));

        if ($id === null) {
            return $this->redirect($this->kosdesign->getLink());
        }

        $result = $this->kosdesign->DBSelectCookieByIDV2($id);
        if (count($result) > 0) {
            $this->set('id', $id);
            $this->set('_post', $result);
            return;
        }

        return $this->redirect($this->kosdesign->getLink());
    }

    public function save($id = null)
    {
        $post = $this->request->request->all();
        $this->set('_post', $post);
        $postedToken = isset($post['token']) ? $post['token'] : null;
        $postedSiteId = isset($post['site_id']) ? (int) $post['site_id'] : 0;
        $managedCookieNamesRaw = isset($post['managed_cookie_names']) ? (string) $post['managed_cookie_names'] : '';
        $managedCookieDomainRaw = isset($post['managed_cookie_domain']) ? (string) $post['managed_cookie_domain'] : '';
        $managedCookiePathRaw = isset($post['managed_cookie_path']) ? (string) $post['managed_cookie_path'] : CookieConsentService::MANAGED_COOKIE_PATH_DEFAULT;

        if (($id === null && !$this->token->validate('cookie_consent_add', $postedToken)) || ($id !== null && !$this->token->validate('cookie_consent_edit', $postedToken))) {
            $this->error->add($this->token->getErrorMessage());
        }

        if (empty($post['name'])) {
            $this->error->add(t('Cookie type name is required.'));
        }

        if ($postedSiteId <= 0) {
            $this->error->add(t('Site is required.'));
        }

        $this->validateManagedCookieCleanup($managedCookieNamesRaw, $managedCookieDomainRaw, $managedCookiePathRaw);

        $languages = $this->kosdesign->getSiteLanguage();
        $content = [];
        foreach ($languages as $language) {
            if ($postedSiteId !== (int) $language['site_id']) {
                continue;
            }

            $siteId = $language['site_id'];
            $code = $language['language'];
            $label = isset($post['label'][$siteId][$code]) ? $post['label'][$siteId][$code] : null;
            $description = isset($post['content'][$siteId][$code]) ? $post['content'][$siteId][$code] : null;

            if (empty($label)) {
                $this->error->add(t('Name') . ' (' . $language['text'] . ') ' . t('is required.'));
                continue;
            }

            $content[] = [
                'id' => $language['page_id'],
                'language' => $language['language'],
                'name' => $label,
                'content' => $description ? LinkAbstractor::translateTo($description) : '',
            ];
        }

        if ($this->error->has()) {
            $this->set('_type', $id !== null ? 'edit' : 'add');
            if ($id !== null) {
                $this->set('id', $id);
            }
            $this->set('_token', $this->token->generate($id !== null ? 'cookie_consent_edit' : 'cookie_consent_add'));
            $this->set('error', $this->error);
            return;
        }

        try {
            $name = !empty($post['name']) ? $post['name'] : '';
            $active = !empty($post['active']) ? $post['active'] : '0';
            $always = !empty($post['always']) ? $post['always'] : '0';
            $header = !empty($post['code_header']) ? $post['code_header'] : '';
            $body = !empty($post['code_body']) ? $post['code_body'] : '';
            $footer = !empty($post['code_footer']) ? $post['code_footer'] : '';
            $siteId = $postedSiteId;
            $sortOrder = 0;
            $managedCookieNames = $this->normalizeManagedCookieNames($managedCookieNamesRaw);
            $managedCookieDomain = $managedCookieNames !== '' ? $this->kosdesign->sanitizeManagedCookieDomain($managedCookieDomainRaw) : '';
            $managedCookiePath = $managedCookieNames !== ''
                ? $this->kosdesign->sanitizeManagedCookiePath($managedCookiePathRaw)
                : CookieConsentService::MANAGED_COOKIE_PATH_DEFAULT;

            if (isset($post['sort_order'])) {
                $sortOrder = (int) $post['sort_order'];
                if ($sortOrder < 0) {
                    $sortOrder = 0;
                }
            } elseif ($id !== null) {
                $existing = $this->kosdesign->DBSelectCookieByIDV2($id);
                if (!empty($existing) && isset($existing['sort_order'])) {
                    $sortOrder = (int) $existing['sort_order'];
                }
            } else {
                $sortOrder = $this->kosdesign->DBCountCookieV2($siteId) + 1;
            }

            if ($id !== null) {
                $saved = $this->kosdesign->DBUpdateCookie($id, $name, $active, $always, $header, $body, $footer, $siteId, $sortOrder, $managedCookieNames, $managedCookieDomain, $managedCookiePath);
                if (!$saved) {
                    throw new Exception('Can not update cookie. Please try again.');
                }
                $cookieId = $id;
            } else {
                $cookieId = $this->kosdesign->DBInsertCookie($name, $active, $always, $header, $body, $footer, $siteId, $sortOrder, $managedCookieNames, $managedCookieDomain, $managedCookiePath);
                if ($cookieId === null) {
                    throw new Exception('Can not create cookie. Please try again.');
                }
            }

            foreach ($content as $row) {
                $this->kosdesign->DBUpdateContent($cookieId, $row['id'], $row['language'], $row['name'], $row['content']);
            }

            return $this->redirect($this->kosdesign->getLink());
        } catch (Exception $e) {
            $this->error->add($e->getMessage());
            if ($id !== null) {
                return $this->redirect($this->kosdesign->getLink('edit', $id));
            }

            return $this->redirect($this->kosdesign->getLink('add'));
        }
    }

    public function remove()
    {
        $id = $this->post('id');
        $token = $this->post('token');

        if (!$this->token->validate('cookie_consent_delete', $token)) {
            $this->error->add($this->token->getErrorMessage());
        }

        if (empty($id)) {
            $this->error->add(t('ID is not valid.'));
        }

        $response = new UserEditResponse();
        if ($this->error->has()) {
            $response->setError($this->error);
            return $response->outputJSON();
        }

        if ($this->kosdesign->DBDeleteCookie($id)) {
            return new \Symfony\Component\HttpFoundation\JsonResponse([
                'message' => t('Delete setting successfully.'),
                'redirectURL' => $this->kosdesign->getLink(),
                'title' => 'Delete',
            ]);
        }

        $response->setError(t('Delete setting fail. Please try again later.'));
        return $response->outputJSON();
    }

    protected function validateManagedCookieCleanup($managedCookieNamesRaw, $managedCookieDomainRaw, $managedCookiePathRaw)
    {
        $lines = preg_split('/\R+/', (string) $managedCookieNamesRaw);
        $hasManagedCookies = false;
        $reservedCookieNames = [
            CookieConsentService::CONSENT_ID_COOKIE_NAME,
            CookieConsentService::CONSENT_MARKER_COOKIE_NAME,
            CookieConsentService::CONSENT_VERSION_COOKIE_NAME,
        ];

        foreach ($lines as $index => $line) {
            $cookiePattern = trim((string) $line);
            if ($cookiePattern === '') {
                continue;
            }

            $hasManagedCookies = true;
            if (substr_count($cookiePattern, '*') > 1 || (strpos($cookiePattern, '*') !== false && substr($cookiePattern, -1) !== '*')) {
                $this->error->add(t('Managed cookie rule line %s is invalid. Use an exact cookie name or a prefix ending with *.', $index + 1));
                continue;
            }

            $cookieName = substr($cookiePattern, -1) === '*' ? substr($cookiePattern, 0, -1) : $cookiePattern;
            $cookieName = trim((string) $cookieName);
            if ($cookieName === '') {
                $this->error->add(t('Managed cookie rule line %s is invalid.', $index + 1));
                continue;
            }

            if (!preg_match("/^[!#$%&'\\+\\-.\\^_`|~0-9A-Za-z]+$/", $cookieName)) {
                $this->error->add(t('Managed cookie rule line %s contains unsupported characters for a cookie name.', $index + 1));
                continue;
            }

            if (in_array($cookieName, $reservedCookieNames, true)) {
                $this->error->add(t('Managed cookie rule line %s cannot target the consent cookies required by this package.', $index + 1));
            }
        }

        if (!$hasManagedCookies) {
            return;
        }

        $rawDomain = trim((string) $managedCookieDomainRaw);
        if ($rawDomain !== '' && $this->kosdesign->sanitizeManagedCookieDomain($rawDomain) === '') {
            $this->error->add(t('Managed cookie domain must be a hostname only, without protocol or path.'));
        }

        $rawPath = trim((string) $managedCookiePathRaw);
        if ($rawPath !== '' && $this->kosdesign->sanitizeManagedCookiePath($rawPath) === CookieConsentService::MANAGED_COOKIE_PATH_DEFAULT && $rawPath !== CookieConsentService::MANAGED_COOKIE_PATH_DEFAULT) {
            $this->error->add(t('Managed cookie path must start with /.'));
        }
    }

    protected function normalizeManagedCookieNames($managedCookieNamesRaw)
    {
        $lines = preg_split('/\R+/', (string) $managedCookieNamesRaw);
        $result = [];
        $seen = [];

        foreach ($lines as $line) {
            $cookiePattern = trim((string) $line);
            if ($cookiePattern === '') {
                continue;
            }

            if (isset($seen[$cookiePattern])) {
                continue;
            }

            $result[] = $cookiePattern;
            $seen[$cookiePattern] = true;
        }

        return implode("\n", $result);
    }
}
