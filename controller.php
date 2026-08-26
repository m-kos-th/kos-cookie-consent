<?php

namespace Concrete\Package\KosCookieConsent;

use Concrete\Core\Package\Package;
use Concrete\Core\Page\Page;
use Concrete\Core\Page\Single as SinglePage;
use Concrete\Core\Routing\RouterInterface;
use Concrete\Core\Support\Facade\Events;
use Concrete\Core\View\View;
use Concrete\Package\KosCookieConsent\Src\Controller\Route\CookieConsentApiController;
use Concrete\Package\KosCookieConsent\Src\Frontend\CookieConsentHelper;
use Concrete\Package\KosCookieConsent\Src\Service\CookieConsentService;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends Package
{
    protected $pkgHandle = 'kos_cookie_consent';
    protected $appVersionRequired = '9.0.0';
    protected $pkgVersion = '0.2.9';
    protected $pkgAutoloaderRegistries = [
        'src' => '\Concrete\Package\KosCookieConsent\Src',
    ];
    protected $frontendAssetsRegistered = false;

    public function getPackageName()
    {
        return t('KOS Cookie Consent');
    }

    public function getPackageDescription()
    {
        return t('Automatic multilingual cookie consent popup with dashboard category management, consent logs/export, consent versioning, tracking-code gating, and Cookie Settings for changing preferences.');
    }

    public function on_start()
    {
        $app = $this->app;

        $app->singleton(CookieConsentService::class, function ($app) {
            return new CookieConsentService($app['database/connection'], $app);
        });

        $app->singleton(CookieConsentHelper::class, function ($app) {
            return new CookieConsentHelper($app, $app->make(CookieConsentService::class));
        });

        $router = $app->make(RouterInterface::class);
        $router->post('/cookieConsentSave', CookieConsentApiController::class . '::save');
        $router->post('/cookieConsentSaveV2', CookieConsentApiController::class . '::saveV2');
        $router->get('/cookieConsentStateV2', CookieConsentApiController::class . '::stateV2');
        $router->post('/kos-cookie-consent/save', CookieConsentApiController::class . '::saveV2');
        $router->get('/kos-cookie-consent/state', CookieConsentApiController::class . '::stateV2');

        $this->registerFrontendHooks();
    }

    public function install()
    {
        $pkg = parent::install();
        $this->installOrUpgrade($pkg);

        return $pkg;
    }

    public function upgrade()
    {
        parent::upgrade();
        $this->installOrUpgrade($this);
    }

    protected function installOrUpgrade($pkg)
    {
        $this->ensureConsentLogTables();
        $this->ensurePopupCopyTable();
        $this->ensureCookieSortColumn();
        $this->ensureManagedCookieCleanupColumns();
        $this->ensureConsentCookieSettingsTable();
        $this->installSinglePages($pkg);
    }

    protected function ensureConsentLogTables()
    {
        $db = $this->app['database/connection'];

        $db->executeQuery("CREATE TABLE IF NOT EXISTS _KosIP (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            consent_id VARCHAR(64) NULL,
            IP VARCHAR(45) NULL,
            created DATETIME NULL,
            INDEX idx_consent_id (consent_id),
            INDEX idx_ip (IP)
        )");

        $db->executeQuery("CREATE TABLE IF NOT EXISTS _KosLog (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            pid INT UNSIGNED NOT NULL,
            cid INT UNSIGNED NOT NULL,
            site_id INT UNSIGNED NOT NULL DEFAULT 0,
            consent_version VARCHAR(64) NULL,
            created DATETIME NULL,
            INDEX idx_pid (pid),
            INDEX idx_cid (cid),
            INDEX idx_site_id (site_id),
            INDEX idx_consent_version (consent_version)
        )");

        foreach ([
            'ALTER TABLE _KosIP ADD consent_id VARCHAR(64) NULL',
            'ALTER TABLE _KosIP MODIFY IP VARCHAR(45) NULL',
            'ALTER TABLE _KosIP ADD created DATETIME NULL',
            'ALTER TABLE _KosLog ADD site_id INT UNSIGNED NOT NULL DEFAULT 0',
            'ALTER TABLE _KosLog ADD consent_version VARCHAR(64) NULL',
            'ALTER TABLE _KosLog ADD created DATETIME NULL',
            'CREATE INDEX idx_consent_id ON _KosIP (consent_id)',
            'CREATE INDEX idx_ip ON _KosIP (IP)',
            'CREATE INDEX idx_pid ON _KosLog (pid)',
            'CREATE INDEX idx_cid ON _KosLog (cid)',
            'CREATE INDEX idx_site_id ON _KosLog (site_id)',
            'CREATE INDEX idx_consent_version ON _KosLog (consent_version)',
        ] as $sql) {
            try {
                $db->executeQuery($sql);
            } catch (\Throwable $e) {
                // Existing installs may already have these columns or indexes.
            }
        }
    }

    protected function ensurePopupCopyTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS _KosCookiePopupCopy (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_id INT UNSIGNED NOT NULL DEFAULT 0,
            lang INT UNSIGNED NOT NULL DEFAULT 0,
            lang_code VARCHAR(32) NULL,
            banner_title TEXT NULL,
            banner_summary TEXT NULL,
            modal_title TEXT NULL,
            modal_intro_one TEXT NULL,
            modal_intro_two TEXT NULL,
            preferences_title TEXT NULL,
            save_label VARCHAR(255) NULL,
            accept_label VARCHAR(255) NULL,
            decline_label VARCHAR(255) NULL,
            settings_label VARCHAR(255) NULL,
            created DATETIME NULL,
            updated DATETIME NULL,
            INDEX idx_site_id (site_id),
            INDEX idx_lang (lang),
            INDEX idx_lang_code (lang_code)
        )";

        $db = $this->app['database/connection'];
        $db->executeQuery($sql);

        try {
            $db->executeQuery('ALTER TABLE _KosCookiePopupCopy ADD settings_label VARCHAR(255) NULL');
        } catch (\Throwable $e) {
            // Column may already exist.
        }

        try {
            $db->executeQuery('ALTER TABLE _KosCookiePopupCopy ADD decline_label VARCHAR(255) NULL');
        } catch (\Throwable $e) {
            // Column may already exist.
        }
    }

    protected function ensureCookieSortColumn()
    {
        try {
            $this->app['database/connection']->executeQuery('ALTER TABLE _KosCookie ADD sort_order INT NOT NULL DEFAULT 0');
        } catch (\Throwable $e) {
            // Column may already exist.
        }

        try {
            $this->app['database/connection']->executeQuery('CREATE INDEX idx_sort_order ON _KosCookie (sort_order)');
        } catch (\Throwable $e) {
            // Index may already exist.
        }
    }

    protected function ensureConsentCookieSettingsTable()
    {
        $db = $this->app['database/connection'];
        $db->executeQuery("CREATE TABLE IF NOT EXISTS _KosCookieConsentSetting (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value TEXT NULL,
            updated DATETIME NULL
        )");

        try {
            $existing = $db->fetchAll(
                'SELECT setting_key FROM _KosCookieConsentSetting WHERE setting_key=? LIMIT 1',
                [CookieConsentService::SETTING_CONSENT_COOKIE_LIFETIME_DAYS]
            );
        } catch (\Throwable $e) {
            $existing = [];
        }

        if (empty($existing)) {
            try {
                $db->executeQuery(
                    'INSERT INTO _KosCookieConsentSetting (setting_key, setting_value, updated) VALUES (?, ?, ?)',
                    [
                        CookieConsentService::SETTING_CONSENT_COOKIE_LIFETIME_DAYS,
                        (string) CookieConsentService::DEFAULT_CONSENT_COOKIE_LIFETIME_DAYS,
                        gmdate('Y-m-d H:i:s'),
                    ]
                );
            } catch (\Throwable $e) {
                // Another process may have inserted the default row already.
            }
        }
    }

    protected function ensureManagedCookieCleanupColumns()
    {
        $db = $this->app['database/connection'];

        foreach ([
            'ALTER TABLE _KosCookie ADD managed_cookie_names TEXT NULL',
            'ALTER TABLE _KosCookie ADD managed_cookie_domain VARCHAR(255) NULL',
            "ALTER TABLE _KosCookie ADD managed_cookie_path VARCHAR(255) NULL DEFAULT '/'",
        ] as $sql) {
            try {
                $db->executeQuery($sql);
            } catch (\Throwable $e) {
                // Columns may already exist.
            }
        }
    }

    protected function installSinglePages($pkg)
    {
        $pages = [
            '/dashboard/cookie_consent' => 'Cookie Consent',
            '/dashboard/cookie_consent/logs' => 'Logs',
            '/dashboard/cookie_consent/setting' => 'Setting',
            '/dashboard/cookie_consent/popup_content' => 'Popup Content',
            '/dashboard/cookie_consent/export' => 'Export',
        ];

        foreach ($pages as $path => $name) {
            $page = $this->getInstalledPageByPath($path);
            if (!$page || $page->isError()) {
                $page = SinglePage::add($path, $pkg);
            }

            if ($page && !$page->isError()) {
                $page->update(['cName' => t($name)]);
            }
        }

        $this->sortDashboardPages([
            '/dashboard/cookie_consent/logs',
            '/dashboard/cookie_consent/setting',
            '/dashboard/cookie_consent/popup_content',
            '/dashboard/cookie_consent/export',
        ]);
    }

    protected function sortDashboardPages(array $paths)
    {
        $db = $this->app['database/connection'];
        $displayOrder = 0;

        foreach ($paths as $path) {
            $page = $this->getInstalledPageByPath($path);
            if (!$page || $page->isError()) {
                continue;
            }

            try {
                $db->executeQuery('UPDATE Pages SET cDisplayOrder=? WHERE cID=?', [$displayOrder, (int) $page->getCollectionID()]);
            } catch (\Throwable $e) {
                // Some installs may not allow direct page order updates during upgrade.
            }

            $displayOrder++;
        }
    }

    protected function getInstalledPageByPath($path)
    {
        $page = Page::getByPath($path);
        if ($page && !$page->isError()) {
            return $page;
        }

        $page = Page::getByPath($path, 'ACTIVE');
        if ($page && !$page->isError()) {
            return $page;
        }

        return null;
    }

    protected function registerFrontendHooks()
    {
        Events::addListener('on_before_render', function ($event) {
            $view = $event->getArgument('view');
            if (!$view instanceof View || !$this->shouldRenderFrontendPopup()) {
                return;
            }

            $this->appendFrontendAssets($view);
        });
    }

    protected function shouldRenderFrontendPopup()
    {
        $page = Page::getCurrentPage();
        if (!$page || $page->isError()) {
            return false;
        }

        $path = (string) $page->getCollectionPath();
        if (strpos($path, '/dashboard') === 0 || strpos($path, '/!') === 0) {
            return false;
        }

        /** @var CookieConsentHelper $helper */
        $helper = $this->app->make(CookieConsentHelper::class);

        return $helper->getCurrentSiteId() !== null && !empty($helper->getCookieList());
    }

    protected function appendFrontendAssets(View $view)
    {
        if ($this->frontendAssetsRegistered) {
            return;
        }

        $basePath = $this->getRelativePath();
        $version = h($this->getPackageVersion());

        $view->addHeaderItem('<link rel="stylesheet" href="' . h($basePath) . '/assets/css/cookie-popup.css?v=' . $version . '">');
        $view->addFooterItem($this->renderPackageElement('cookie_popup'));
        $view->addFooterItem('<script src="' . h($basePath) . '/assets/js/cookie-popup.js?v=' . $version . '"></script>');

        $this->frontendAssetsRegistered = true;
    }

    protected function renderPackageElement($file, array $args = [])
    {
        ob_start();
        View::element($file, $args, $this->pkgHandle);

        return trim(ob_get_clean());
    }
}
