<?php

namespace Concrete\Package\KosCookieConsent\Controller\SinglePage\Dashboard\CookieConsent;

use Concrete\Core\Editor\LinkAbstractor;
use Concrete\Package\KosCookieConsent\Controller\SinglePage\Dashboard\CookieConsent;

defined('C5_EXECUTE') or die('Access Denied.');

class PopupContent extends CookieConsent
{
    const TOKEN_ACTION = 'cookie_consent_popup_content_save';

    public function view()
    {
        $this->set('popupContentLanguages', $this->kosdesign->getSiteLanguage());
        $this->set('popupContentMap', $this->kosdesign->DBSelectPopupCopyMap());
        $this->set('_tokenPopupContent', $this->token->generate(self::TOKEN_ACTION));
    }

    public function save()
    {
        $post = $this->request->request->all();
        $postedToken = isset($post['token_popup_content']) ? $post['token_popup_content'] : null;

        if (!$this->token->validate(self::TOKEN_ACTION, $postedToken)) {
            $this->error->add($this->token->getErrorMessage());
        }

        if ($this->error->has()) {
            $this->set('error', $this->error);
            return $this->view();
        }

        $languages = $this->kosdesign->getSiteLanguage();
        foreach ($languages as $language) {
            $siteId = (int) $language['site_id'];
            $langCode = (string) $language['language'];
            $langPageId = (int) $language['page_id'];

            $payload = isset($post['popup_copy'][$siteId][$langCode]) && is_array($post['popup_copy'][$siteId][$langCode])
                ? $post['popup_copy'][$siteId][$langCode]
                : [];

            $data = [
                'banner_title' => isset($payload['banner_title']) ? trim((string) $payload['banner_title']) : '',
                'banner_summary' => !empty($payload['banner_summary']) ? LinkAbstractor::translateTo($payload['banner_summary']) : '',
                'modal_title' => isset($payload['modal_title']) ? trim((string) $payload['modal_title']) : '',
                'modal_intro_one' => !empty($payload['modal_intro_one']) ? LinkAbstractor::translateTo($payload['modal_intro_one']) : '',
                'modal_intro_two' => !empty($payload['modal_intro_two']) ? LinkAbstractor::translateTo($payload['modal_intro_two']) : '',
                'preferences_title' => isset($payload['preferences_title']) ? trim((string) $payload['preferences_title']) : '',
                'save_label' => isset($payload['save_label']) ? trim((string) $payload['save_label']) : '',
                'accept_label' => isset($payload['accept_label']) ? trim((string) $payload['accept_label']) : '',
                'decline_label' => isset($payload['decline_label']) ? trim((string) $payload['decline_label']) : '',
                'settings_label' => isset($payload['settings_label']) ? trim((string) $payload['settings_label']) : '',
            ];

            $this->kosdesign->DBUpdatePopupCopy($siteId, $langPageId, $langCode, $data);
        }

        $this->flash('success', t('Cookie popup content updated successfully.'));

        return $this->redirect($this->kosdesign->getLink());
    }
}
