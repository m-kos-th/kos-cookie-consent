<?php

use Concrete\Core\Multilingual\Page\Section\Section;
use Concrete\Core\Page\Page;
use Concrete\Core\Support\Facade\Url;
use Concrete\Package\KosCookieConsent\Src\Frontend\CookieConsentHelper;

defined('C5_EXECUTE') or die('Access Denied.');

/** @var CookieConsentHelper $helper */
$helper = \Core::make(CookieConsentHelper::class);
$siteId = $helper->getCurrentSiteId();
$cookies = $siteId !== null ? $helper->getCookieList($siteId) : [];
$page = Page::getCurrentPage();
$isEditMode = $page && $page->isEditMode();

if ($siteId === null || empty($cookies)) {
    return;
}

$currentSection = Section::getCurrentSection();
$language = $currentSection ? strtolower((string) $currentSection->getLanguage()) : 'en';
$isThai = strpos($language, 'th') === 0;
$privacyPolicyUrl = null;
$privacyPolicyPage = Page::getByPath('/privacy_policy');
if ($privacyPolicyPage && !$privacyPolicyPage->isError()) {
    if ($currentSection) {
        $translatedPageId = $currentSection->getTranslatedPageID($privacyPolicyPage);
        if ($translatedPageId) {
            $translatedPage = Page::getByID($translatedPageId);
            if ($translatedPage && !$translatedPage->isError()) {
                $privacyPolicyPage = $translatedPage;
            }
        }
    }

    $privacyPolicyUrl = $privacyPolicyPage->getCollectionLink();
}

$defaultCopy = $isThai ? [
    'bannerTitle' => 'เราให้ความสําคัญกับความเป็นส่วนตัวของคุณ',
    'bannerSummary' => 'เว็บไซต์นี้ใช้คุกกี้เพื่อให้การทํางานพื้นฐานสมบูรณ์และเพื่อวิเคราะห์การใช้งานตามกฎหมายคุ้มครองข้อมูล คุณสามารถจัดการการตั้งค่าของคุณหรือยอมรับคุกกี้ทั้งหมดได้',
    'bannerPolicyPrefix' => 'ดูรายละเอียดเพิ่มเติมได้ที่',
    'privacyPolicy' => 'นโยบายความเป็นส่วนตัว',
    'modalTitle' => 'ศูนย์จัดการความเป็นส่วนตัว',
    'modalIntroOne' => 'เมื่อคุณเข้าชมเว็บไซต์ ข้อมูลบางอย่างอาจถูกจัดเก็บหรือดึงจากเบราว์เซอร์ของคุณ โดยมากจะอยู่ในรูปแบบของคุกกี้ ข้อมูลดังกล่าวอาจเกี่ยวข้องกับคุณ การตั้งค่าของคุณ หรืออุปกรณ์ของคุณ และส่วนใหญ่ใช้เพื่อให้เว็บไซต์ทํางานได้ตามที่คุณคาดหวัง',
    'modalIntroTwo' => 'ข้อมูลนี้มักไม่ได้ระบุตัวตนของคุณโดยตรง แต่ช่วยให้เราออกแบบประสบการณ์ใช้งานที่เหมาะกับคุณมากขึ้นได้ คุณสามารถเลือกไม่อนุญาตคุกกี้บางประเภทได้ อย่างไรก็ตาม การปิดคุกกี้บางประเภทอาจส่งผลต่อประสบการณ์ใช้งานและบริการบางส่วนของเว็บไซต์',
    'preferencesTitle' => 'จัดการการตั้งค่าความยินยอม',
    'customize' => 'Customize',
    'accept' => 'Accept',
    'decline' => 'Reject All',
    'save' => 'Save Preferences',
    'settingsLink' => 'ตั้งค่าคุกกี้',
    'close' => 'ปิด',
    'alwaysOn' => 'เปิดใช้งานเสมอ',
    'error' => '',
] : [
    'bannerTitle' => 'We value your privacy.',
    'bannerSummary' => 'This site uses cookies to ensure proper functionality and to analyze traffic in accordance with data protection laws. You can manage your preferences or accept all cookies.',
    'bannerPolicyPrefix' => 'For more details, please read our',
    'privacyPolicy' => 'Privacy Policy',
    'modalTitle' => 'Privacy Preference Center',
    'modalIntroOne' => 'When you visit any website, it may store or retrieve information on your browser, mostly in the form of cookies. This information may relate to you, your preferences, or your device and is mainly used to make the website work as you expect it to.',
    'modalIntroTwo' => 'This information usually does not directly identify you, but it can provide you with a more personalized web experience. Because we respect your right to privacy, you can choose not to allow certain types of cookies. Blocking some types of cookies may impact your experience of the site and the services we are able to offer.',
    'preferencesTitle' => 'Manage Consent Preferences',
    'customize' => 'Customize',
    'accept' => 'Accept',
    'decline' => 'Reject All',
    'save' => 'Save Preferences',
    'settingsLink' => 'Cookie Settings',
    'close' => 'Close',
    'alwaysOn' => 'Always active',
    'error' => '',
];

$copy = $helper->getPopupCopy($defaultCopy);

$stateUrl = (string) Url::to('/kos-cookie-consent/state');
$saveUrl = (string) Url::to('/kos-cookie-consent/save');
?>

<div
    class="kos-cookie-popup"
    id="kosCookiePopup"
    data-site-id="<?php echo h((string) $siteId); ?>"
    data-state-url="<?php echo h($stateUrl); ?>"
    data-save-url="<?php echo h($saveUrl); ?>"
    data-token="<?php echo h($helper->generateToken('kos_cookie_consent_frontend')); ?>"
    data-edit-mode="<?php echo $isEditMode ? '1' : '0'; ?>"
    data-consent-cookie-max-age="<?php echo h((string) $helper->getConsentCookieLifetimeSeconds()); ?>"
    hidden
>
    <div class="kos-cookie-popup__backdrop" data-cookie-backdrop hidden></div>

    <section class="kos-cookie-popup__banner" data-cookie-banner>
        <div class="kos-cookie-popup__banner-copy">
            <h3 class="kos-cookie-popup__banner-title"><?php echo h($copy['bannerTitle']); ?></h3>
            <div class="kos-cookie-popup__banner-description">
                <?php echo $copy['bannerSummary']; ?>
                <?php if ($privacyPolicyUrl) { ?>
                    <span class="kos-cookie-popup__banner-policy">
                        <?php echo h($copy['bannerPolicyPrefix']); ?>
                        <a href="<?php echo h($privacyPolicyUrl); ?>" class="kos-cookie-popup__banner-link"><?php echo h($copy['privacyPolicy']); ?></a>.
                    </span>
                <?php } ?>
            </div>
        </div>

        <div class="kos-cookie-popup__banner-actions">
            <button type="button" class="kos-cookie-popup__button kos-cookie-popup__button--secondary kos-cookie-popup__button--customize kos-cookie-popup__button--banner-customize" data-cookie-action="open-modal">
                <span><?php echo h($copy['customize']); ?></span>
                <span class="kos-cookie-popup__gear" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15ZM16 12C16 14.2091 14.2091 16 12 16C9.79086 16 8 14.2091 8 12C8 9.79086 9.79086 8 12 8C14.2091 8 16 9.79086 16 12Z"></path>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M9.73251 5.36155L9.34125 5.52235C8.75856 5.76182 8.21485 6.0779 7.72277 6.45835L7.38771 6.7174L4.86735 6.35428L3.54439 8.64572L5.11788 10.6454L5.06201 11.0641C5.02122 11.3698 5.0001 11.6822 5.0001 12C5.0001 12.3178 5.02122 12.6302 5.06201 12.9359L5.11788 13.3546L3.54439 15.3543L4.86735 17.6457L7.38771 17.2826L7.72277 17.5416C8.21485 17.9221 8.75856 18.2382 9.34125 18.4777L9.73251 18.6384L10.6771 21H13.3231L14.2677 18.6384L14.6589 18.4777C15.2416 18.2382 15.7853 17.9221 16.2774 17.5416L16.6125 17.2826L19.1328 17.6457L20.4558 15.3543L18.8823 13.3546L18.9382 12.9359C18.979 12.6302 19.0001 12.3178 19.0001 12C19.0001 11.6822 18.979 11.3698 18.9382 11.0641L18.8823 10.6454L20.4558 8.64572L19.1328 6.35428L16.6125 6.7174L16.2774 6.45835C15.7853 6.0779 15.2416 5.76182 14.6589 5.52235L14.2677 5.36155L13.3231 3H10.6771L9.73251 5.36155ZM10.0001 2H14.0001L15.0391 4.59741C15.7057 4.87135 16.3271 5.23272 16.8891 5.66723L19.6604 5.26795L21.6604 8.73205L19.9294 10.9318C19.976 11.2812 20.0001 11.6378 20.0001 12C20.0001 12.3622 19.976 12.7188 19.9294 13.0682L21.6604 15.2679L19.6604 18.732L16.8891 18.3328C16.3271 18.7673 15.7057 19.1286 15.0391 19.4026L14.0001 22H10.0001L8.96113 19.4026C8.29455 19.1286 7.67312 18.7673 7.11112 18.3328L4.33984 18.732L2.33984 15.2679L4.07079 13.0682C4.02417 12.7188 4.0001 12.3622 4.0001 12C4.0001 11.6378 4.02417 11.2812 4.07079 10.9318L2.33984 8.73205L4.33984 5.26795L7.11112 5.66723C7.67312 5.23272 8.29455 4.87135 8.96113 4.59741L10.0001 2Z"></path>
                    </svg>
                </span>
            </button>
            <button type="button" class="kos-cookie-popup__button kos-cookie-popup__button--ghost kos-cookie-popup__button--reject kos-cookie-popup__button--banner-reject" data-cookie-action="decline-all">
                <?php echo h($copy['decline']); ?>
            </button>
            <button type="button" class="kos-cookie-popup__button kos-cookie-popup__button--primary kos-cookie-popup__button--accept kos-cookie-popup__button--banner-accept" data-cookie-action="accept-all">
                <?php echo h($copy['accept']); ?>
            </button>
        </div>
    </section>

    <section class="kos-cookie-popup__modal" data-cookie-modal hidden aria-modal="true" role="dialog">
        <div class="kos-cookie-popup__modal-panel">
            <button type="button" class="kos-cookie-popup__close kos-cookie-popup__close--modal" data-cookie-action="close-modal" aria-label="<?php echo h($copy['close']); ?>">
                <span></span>
                <span></span>
            </button>

            <div class="kos-cookie-popup__modal-copy">
                <h3 class="kos-cookie-popup__modal-title"><?php echo h($copy['modalTitle']); ?></h3>
                <div class="kos-cookie-popup__modal-description"><?php echo $copy['modalIntroOne']; ?></div>
                <?php if (trim((string) $copy['modalIntroTwo']) !== '') { ?>
                    <div class="kos-cookie-popup__modal-description"><?php echo $copy['modalIntroTwo']; ?></div>
                <?php } ?>
            </div>

            <div class="kos-cookie-popup__preferences">
                <h4 class="kos-cookie-popup__preferences-title"><?php echo h($copy['preferencesTitle']); ?></h4>

                <div class="kos-cookie-popup__list">
                    <?php foreach ($cookies as $cookie) { ?>
                        <?php
                        $cookieId = (int) $cookie['id'];
                        $isAlways = !empty($cookie['always']);
                        ?>
                        <div class="kos-cookie-popup__item" data-cookie-item>
                            <div class="kos-cookie-popup__item-header">
                                <button type="button" class="kos-cookie-popup__item-trigger kos-cookie-popup__item-trigger--toggle" data-cookie-action="toggle-item" aria-expanded="false">
                                    <span class="kos-cookie-popup__item-icon" aria-hidden="true"></span>
                                    <span class="kos-cookie-popup__item-title"><?php echo h($cookie['name']); ?></span>
                                </button>

                                <?php if ($isAlways) { ?>
                                    <span class="kos-cookie-popup__item-state"><?php echo h($copy['alwaysOn']); ?></span>
                                <?php } else { ?>
                                    <label class="kos-cookie-popup__switch">
                                        <input type="checkbox" data-cookie-id="<?php echo $cookieId; ?>">
                                        <span class="kos-cookie-popup__switch-track">
                                            <span class="kos-cookie-popup__switch-thumb"></span>
                                        </span>
                                    </label>
                                <?php } ?>
                            </div>

                            <div class="kos-cookie-popup__item-body" data-cookie-item-body hidden>
                                <div class="kos-cookie-popup__item-description"><?php echo $cookie['content']; ?></div>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <div class="kos-cookie-popup__modal-actions">
                    <button type="button" class="kos-cookie-popup__button kos-cookie-popup__button--ghost kos-cookie-popup__button--reject kos-cookie-popup__button--modal-reject" data-cookie-action="decline-all">
                        <?php echo h($copy['decline']); ?>
                    </button>
                    <button type="button" class="kos-cookie-popup__button kos-cookie-popup__button--ghost kos-cookie-popup__button--save kos-cookie-popup__button--modal-save" data-cookie-action="save">
                        <?php echo h($copy['save']); ?>
                    </button>
                    <button type="button" class="kos-cookie-popup__button kos-cookie-popup__button--primary kos-cookie-popup__button--accept kos-cookie-popup__button--modal-accept" data-cookie-action="accept-all">
                        <?php echo h($copy['accept']); ?>
                    </button>
                </div>
            </div>
        </div>
    </section>
</div>

<button type="button" class="kos-cookie-popup__preferences-link kos-cookie-popup__preferences-link--settings" data-cookie-preferences-trigger hidden>
    <span class="kos-cookie-popup__gear" aria-hidden="true">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15ZM16 12C16 14.2091 14.2091 16 12 16C9.79086 16 8 14.2091 8 12C8 9.79086 9.79086 8 12 8C14.2091 8 16 9.79086 16 12Z"></path>
            <path fill-rule="evenodd" clip-rule="evenodd" d="M9.73251 5.36155L9.34125 5.52235C8.75856 5.76182 8.21485 6.0779 7.72277 6.45835L7.38771 6.7174L4.86735 6.35428L3.54439 8.64572L5.11788 10.6454L5.06201 11.0641C5.02122 11.3698 5.0001 11.6822 5.0001 12C5.0001 12.3178 5.02122 12.6302 5.06201 12.9359L5.11788 13.3546L3.54439 15.3543L4.86735 17.6457L7.38771 17.2826L7.72277 17.5416C8.21485 17.9221 8.75856 18.2382 9.34125 18.4777L9.73251 18.6384L10.6771 21H13.3231L14.2677 18.6384L14.6589 18.4777C15.2416 18.2382 15.7853 17.9221 16.2774 17.5416L16.6125 17.2826L19.1328 17.6457L20.4558 15.3543L18.8823 13.3546L18.9382 12.9359C18.979 12.6302 19.0001 12.3178 19.0001 12C19.0001 11.6822 18.979 11.3698 18.9382 11.0641L18.8823 10.6454L20.4558 8.64572L19.1328 6.35428L16.6125 6.7174L16.2774 6.45835C15.7853 6.0779 15.2416 5.76182 14.6589 5.52235L14.2677 5.36155L13.3231 3H10.6771L9.73251 5.36155ZM10.0001 2H14.0001L15.0391 4.59741C15.7057 4.87135 16.3271 5.23272 16.8891 5.66723L19.6604 5.26795L21.6604 8.73205L19.9294 10.9318C19.976 11.2812 20.0001 11.6378 20.0001 12C20.0001 12.3622 19.976 12.7188 19.9294 13.0682L21.6604 15.2679L19.6604 18.732L16.8891 18.3328C16.3271 18.7673 15.7057 19.1286 15.0391 19.4026L14.0001 22H10.0001L8.96113 19.4026C8.29455 19.1286 7.67312 18.7673 7.11112 18.3328L4.33984 18.732L2.33984 15.2679L4.07079 13.0682C4.02417 12.7188 4.0001 12.3622 4.0001 12C4.0001 11.6378 4.02417 11.2812 4.07079 10.9318L2.33984 8.73205L4.33984 5.26795L7.11112 5.66723C7.67312 5.23272 8.29455 4.87135 8.96113 4.59741L10.0001 2Z"></path>
        </svg>
    </span>
</button>
