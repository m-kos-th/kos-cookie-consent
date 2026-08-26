<?php

namespace Concrete\Package\KosCookieConsent\Src\Service;

use Concrete\Core\Application\Application;
use Concrete\Core\Editor\LinkAbstractor;
use Concrete\Core\Localization\Localization;
use Concrete\Core\Multilingual\Page\Section\Section;
use Concrete\Core\Page\Page;
use Concrete\Core\Site\Service as SiteService;
use Doctrine\DBAL\Connection;

class CookieConsentService
{
    const TABLE_COOKIE = '_KosCookie';
    const TABLE_CONTENT = '_KosCookieContent';
    const TABLE_POPUP_COPY = '_KosCookiePopupCopy';
    const TABLE_SETTING = '_KosCookieConsentSetting';
    const TABLE_IP = '_KosIP';
    const TABLE_LOG = '_KosLog';
    const CONSENT_ID_COOKIE_NAME = 'kos_cookie_consent_id';
    const CONSENT_MARKER_COOKIE_NAME = 'consent';
    const CONSENT_VERSION_COOKIE_NAME = 'consent_version';
    const SETTING_CONSENT_COOKIE_LIFETIME_DAYS = 'consent_cookie_lifetime_days';
    const DEFAULT_CONSENT_COOKIE_LIFETIME_DAYS = 30;
    const MIN_CONSENT_COOKIE_LIFETIME_DAYS = 1;
    const MAX_CONSENT_COOKIE_LIFETIME_DAYS = 400;
    const MANAGED_COOKIE_PATH_DEFAULT = '/';
    const DISPLAY_TIMEZONE = 'Asia/Bangkok';
    const DISPLAY_TIMEZONE_LABEL = 'ICT';

    protected $app;
    protected $db;
    protected $link;
    protected $hasSortOrderColumn;
    protected $hasConsentIdColumn;
    protected $hasConsentVersionColumn;
    protected $hasManagedCookieCleanupColumns;

    public function __construct(Connection $db, Application $app)
    {
        $this->db = $db;
        $this->app = $app;
        $this->hasSortOrderColumn = null;
        $this->hasConsentIdColumn = null;
        $this->hasConsentVersionColumn = null;
        $this->hasManagedCookieCleanupColumns = null;
    }

    public function getConnection()
    {
        return $this->db;
    }

    public function setLink($page = null)
    {
        if ($page === null) {
            $page = Page::getCurrentPage();
        }

        if (is_object($page) && !$page->isError()) {
            $this->link = $page->getCollectionLink();
        }

        return $this->link;
    }

    public function getLink($slug = null, $id = null)
    {
        $link = $this->link;

        if ($slug !== null) {
            $link .= '/' . trim($slug, '/');
        }

        if ($id !== null) {
            $link .= '/' . $id;
        }

        return $link;
    }

    public function getSite()
    {
        $result = [];
        $sites = $this->app->make(SiteService::class)->getList();

        foreach ($sites as $site) {
            $result[$site->getSiteID()] = $site->getSiteName();
        }

        return $result;
    }

    public function getSiteLanguage()
    {
        $result = [];
        $siteService = $this->app->make(SiteService::class);

        foreach ($this->getSite() as $siteId => $siteName) {
            $site = $siteService->getByID($siteId);
            if (!$site) {
                continue;
            }

            $sections = Section::getList($site);
            foreach ($sections as $section) {
                $result[] = [
                    'site_id' => $siteId,
                    'site_name' => $siteName,
                    'page_id' => $section->getCollectionID(),
                    'text' => $section->getLanguageText($section->getLocale()),
                    'locale' => $section->getLocale(),
                    'language' => $section->getLanguage(),
                    'link' => '/',
                ];
            }
        }

        return $result;
    }

    public function getCurrentSection()
    {
        $section = Section::getCurrentSection();
        if ($section) {
            return $section;
        }

        $site = $this->app->make(SiteService::class)->getSite();
        if (!$site) {
            return null;
        }

        $language = Localization::activeLanguage();
        $section = Section::getByLanguage($language, $site);
        if ($section) {
            return $section;
        }

        $section = Section::getDefaultSection($site);
        if ($section) {
            return $section;
        }

        return null;
    }

    public function DBInsertCookie($name = null, $active = null, $always = null, $header = null, $body = null, $footer = null, $siteid = null, $sortOrder = 0, $managedCookieNames = '', $managedCookieDomain = '', $managedCookiePath = self::MANAGED_COOKIE_PATH_DEFAULT)
    {
        if ($name === null || $siteid === null) {
            return null;
        }

        $now = $this->now();
        $columns = ['name', 'code_header', 'code_body', 'code_footer', 'active', 'always', 'site_id'];
        $args = [$name, $header, $body, $footer, (int) !empty($active), (int) !empty($always), (int) $siteid];

        if ($this->hasManagedCookieCleanupColumns()) {
            $columns[] = 'managed_cookie_names';
            $columns[] = 'managed_cookie_domain';
            $columns[] = 'managed_cookie_path';
            $args[] = (string) $managedCookieNames;
            $args[] = (string) $managedCookieDomain;
            $args[] = (string) $managedCookiePath;
        }

        if ($this->hasSortOrderColumn()) {
            $columns[] = 'sort_order';
            $args[] = (int) $sortOrder;
        }

        $columns[] = 'created';
        $columns[] = 'updated';
        $args[] = $now;
        $args[] = $now;

        $sql = 'INSERT INTO ' . self::TABLE_COOKIE . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        $this->db->executeQuery($sql, $args);

        return $this->db->lastInsertId();
    }

    public function DBSelectCookie($current = null, $max = null)
    {
        return $this->DBSelectCookieV2($current, $max, null);
    }

    public function DBSelectCookieV2($current = null, $max = null, $siteid = null)
    {
        $offset = $this->getOffset($current, $max);
        $limit = $this->getMax($max);
        $args = [0];
        $sql = 'SELECT * FROM ' . self::TABLE_COOKIE . ' WHERE remove=?';

        if ($siteid !== null) {
            $args[] = (int) $siteid;
            $sql .= ' AND site_id=?';
        }

        if ($this->hasSortOrderColumn()) {
            $sql .= ' ORDER BY site_id ASC, sort_order ASC, id ASC LIMIT ' . $offset . ', ' . $limit;
        } else {
            $sql .= ' ORDER BY site_id ASC, created DESC LIMIT ' . $offset . ', ' . $limit;
        }

        $result = $this->db->fetchAll($sql, $args);
        $siteMap = $this->getSite();

        foreach ($result as &$row) {
            $row['store_name'] = isset($siteMap[$row['site_id']]) ? $siteMap[$row['site_id']] : '-';
            if (!isset($row['sort_order'])) {
                $row['sort_order'] = 0;
            }
            $this->hydrateManagedCookieCleanupFields($row);
        }
        unset($row);

        return $result;
    }

    public function DBSelectCookieByID($id = null)
    {
        return $this->selectCookieById($id, false);
    }

    public function DBSelectCookieByIDV2($id = null)
    {
        return $this->selectCookieById($id, true);
    }

    public function DBUpdateCookie($id = null, $name = null, $active = null, $always = null, $header = null, $body = null, $footer = null, $siteid = null, $sortOrder = 0, $managedCookieNames = '', $managedCookieDomain = '', $managedCookiePath = self::MANAGED_COOKIE_PATH_DEFAULT)
    {
        if ($id === null || $name === null || $siteid === null) {
            return null;
        }

        $sets = ['name=?', 'code_header=?', 'code_body=?', 'code_footer=?', 'active=?', 'always=?', 'site_id=?'];
        $args = [$name, $header, $body, $footer, (int) !empty($active), (int) !empty($always), (int) $siteid];

        if ($this->hasManagedCookieCleanupColumns()) {
            $sets[] = 'managed_cookie_names=?';
            $sets[] = 'managed_cookie_domain=?';
            $sets[] = 'managed_cookie_path=?';
            $args[] = (string) $managedCookieNames;
            $args[] = (string) $managedCookieDomain;
            $args[] = (string) $managedCookiePath;
        }

        if ($this->hasSortOrderColumn()) {
            $sets[] = 'sort_order=?';
            $args[] = (int) $sortOrder;
        }

        $sets[] = 'updated=?';
        $args[] = $this->now();
        $args[] = (int) $id;
        $sql = 'UPDATE ' . self::TABLE_COOKIE . ' SET ' . implode(', ', $sets) . ' WHERE id=?';

        $this->db->executeQuery($sql, $args);

        return true;
    }

    public function DBDeleteCookie($id = null)
    {
        if ($id === null) {
            return null;
        }

        $args = [1, $this->now(), (int) $id];
        $sql = 'UPDATE ' . self::TABLE_COOKIE . ' SET remove=?, updated=? WHERE id=?';
        $this->db->executeQuery($sql, $args);

        return true;
    }

    public function DBUpdateCookieSortOrder(array $ids = [], $siteid = null)
    {
        if (!$this->hasSortOrderColumn() || count($ids) === 0) {
            return false;
        }

        $order = 1;
        foreach ($ids as $id) {
            $cookieId = (int) $id;
            if ($cookieId <= 0) {
                continue;
            }

            $args = [$order, $this->now(), $cookieId];
            $sql = 'UPDATE ' . self::TABLE_COOKIE . ' SET sort_order=?, updated=? WHERE id=?';
            if ($siteid !== null) {
                $args[] = (int) $siteid;
                $sql .= ' AND site_id=?';
            }

            $this->db->executeQuery($sql, $args);
            $order++;
        }

        return true;
    }

    public function DBCountCookie()
    {
        return $this->DBCountCookieV2(null);
    }

    public function DBCountCookieV2($siteid = null)
    {
        $args = [0];
        $sql = 'SELECT COUNT(*) AS total FROM ' . self::TABLE_COOKIE . ' WHERE remove=?';

        if ($siteid !== null) {
            $args[] = (int) $siteid;
            $sql .= ' AND site_id=?';
        }

        $result = $this->db->fetchAll($sql, $args);

        return $result && count($result) > 0 ? (int) $result[0]['total'] : 0;
    }

    public function DBInsertContent($cid = null, $lang = null, $lang_code = null, $name = null, $content = null)
    {
        if ($name === null) {
            return null;
        }

        $now = $this->now();
        $args = [(int) $cid, (int) $lang, $lang_code, $name, $content, $now, $now];
        $sql = 'INSERT INTO ' . self::TABLE_CONTENT . ' (cid, lang, lang_code, name, content, created, updated) VALUES (?, ?, ?, ?, ?, ?, ?)';
        $this->db->executeQuery($sql, $args);

        return $this->db->lastInsertId();
    }

    public function DBUpdateContent($cid = null, $lang = null, $lang_code = null, $name = null, $content = null)
    {
        if ($name === null) {
            return null;
        }

        $args = [(int) $cid, (int) $lang];
        $sql = 'SELECT id FROM ' . self::TABLE_CONTENT . ' WHERE cid=? AND lang=?';
        $rows = $this->db->fetchAll($sql, $args);

        if ($rows && count($rows) > 0) {
            $updateArgs = [$name, $content, $this->now(), (int) $rows[0]['id']];
            $updateSql = 'UPDATE ' . self::TABLE_CONTENT . ' SET name=?, content=?, updated=? WHERE id=?';
            $this->db->executeQuery($updateSql, $updateArgs);
            return true;
        }

        $this->DBInsertContent($cid, $lang, $lang_code, $name, $content);

        return true;
    }

    public function DBSelectCookieConsent($siteid = null)
    {
        $section = $this->getCurrentSection();
        if (!$section) {
            return [];
        }

        $args = [0, 1, (int) $section->getCollectionID()];
        $sql = 'SELECT a.id AS id, a.always AS always, a.code_header AS code_header, a.code_body AS code_body, a.code_footer AS code_footer, b.name AS name, b.content AS content FROM ' . self::TABLE_COOKIE . ' a INNER JOIN ' . self::TABLE_CONTENT . ' b ON (a.id = b.cid) WHERE a.remove=? AND a.active=? AND b.lang=?';
        if ($siteid !== null) {
            $args[] = (int) $siteid;
            $sql .= ' AND a.site_id=?';
        }

        if ($this->hasSortOrderColumn()) {
            $sql .= ' ORDER BY a.always DESC, a.sort_order ASC, a.id ASC';
        } else {
            $sql .= ' ORDER BY a.always DESC, b.cid DESC';
        }

        $result = $this->db->fetchAll($sql, $args);

        foreach ($result as &$row) {
            $row['content'] = LinkAbstractor::translateFrom($row['content']);
            $this->hydrateManagedCookieCleanupFields($row);
        }
        unset($row);

        return $result;
    }

    public function DBSelectPopupCopyBySection($sectionId = null)
    {
        if ($sectionId === null) {
            return [];
        }

        try {
            $rows = $this->db->fetchAll('SELECT * FROM ' . self::TABLE_POPUP_COPY . ' WHERE lang=? ORDER BY updated DESC, id DESC LIMIT 1', [(int) $sectionId]);
        } catch (\Exception $e) {
            return [];
        }

        if (!$rows || count($rows) === 0) {
            return [];
        }

        return $rows[0];
    }

    public function DBSelectPopupCopyMap()
    {
        try {
            $rows = $this->db->fetchAll('SELECT * FROM ' . self::TABLE_POPUP_COPY . ' ORDER BY site_id ASC, lang_code ASC, id ASC');
        } catch (\Exception $e) {
            return [];
        }

        $result = [];

        foreach ($rows as $row) {
            $siteId = (int) $row['site_id'];
            $langCode = (string) $row['lang_code'];
            if ($langCode === '') {
                continue;
            }

            if (!isset($result[$siteId])) {
                $result[$siteId] = [];
            }

            $result[$siteId][$langCode] = [
                'banner_title' => (string) $row['banner_title'],
                'banner_summary' => LinkAbstractor::translateFrom((string) $row['banner_summary']),
                'modal_title' => (string) $row['modal_title'],
                'modal_intro_one' => LinkAbstractor::translateFrom((string) $row['modal_intro_one']),
                'modal_intro_two' => LinkAbstractor::translateFrom((string) $row['modal_intro_two']),
                'preferences_title' => (string) $row['preferences_title'],
                'save_label' => (string) $row['save_label'],
                'accept_label' => (string) $row['accept_label'],
                'decline_label' => isset($row['decline_label']) ? (string) $row['decline_label'] : '',
                'settings_label' => isset($row['settings_label']) ? (string) $row['settings_label'] : '',
            ];
        }

        return $result;
    }

    public function DBUpdatePopupCopy($siteId = null, $lang = null, $langCode = null, array $data = [])
    {
        if ($siteId === null || $lang === null || $langCode === null || trim((string) $langCode) === '') {
            return false;
        }

        try {
            $existing = $this->db->fetchAll('SELECT id FROM ' . self::TABLE_POPUP_COPY . ' WHERE lang=? ORDER BY id DESC LIMIT 1', [(int) $lang]);
        } catch (\Exception $e) {
            return false;
        }
        $fields = [
            (string) (isset($data['banner_title']) ? $data['banner_title'] : ''),
            (string) (isset($data['banner_summary']) ? $data['banner_summary'] : ''),
            (string) (isset($data['modal_title']) ? $data['modal_title'] : ''),
            (string) (isset($data['modal_intro_one']) ? $data['modal_intro_one'] : ''),
            (string) (isset($data['modal_intro_two']) ? $data['modal_intro_two'] : ''),
            (string) (isset($data['preferences_title']) ? $data['preferences_title'] : ''),
            (string) (isset($data['save_label']) ? $data['save_label'] : ''),
            (string) (isset($data['accept_label']) ? $data['accept_label'] : ''),
            (string) (isset($data['decline_label']) ? $data['decline_label'] : ''),
            (string) (isset($data['settings_label']) ? $data['settings_label'] : ''),
        ];

        if ($existing && count($existing) > 0) {
            $args = array_merge([(int) $siteId, (int) $lang, (string) $langCode], $fields, [$this->now(), (int) $existing[0]['id']]);
            $sql = 'UPDATE ' . self::TABLE_POPUP_COPY . ' SET site_id=?, lang=?, lang_code=?, banner_title=?, banner_summary=?, modal_title=?, modal_intro_one=?, modal_intro_two=?, preferences_title=?, save_label=?, accept_label=?, decline_label=?, settings_label=?, updated=? WHERE id=?';
            $this->db->executeQuery($sql, $args);
            return true;
        }

        $now = $this->now();
        $args = array_merge([(int) $siteId, (int) $lang, (string) $langCode], $fields, [$now, $now]);
        $sql = 'INSERT INTO ' . self::TABLE_POPUP_COPY . ' (site_id, lang, lang_code, banner_title, banner_summary, modal_title, modal_intro_one, modal_intro_two, preferences_title, save_label, accept_label, decline_label, settings_label, created, updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $this->db->executeQuery($sql, $args);

        return true;
    }

    public function getPopupCopy(array $defaults = [])
    {
        $section = $this->getCurrentSection();
        if (!$section) {
            return $defaults;
        }

        $row = $this->DBSelectPopupCopyBySection((int) $section->getCollectionID());
        if (empty($row)) {
            return $defaults;
        }

        $map = [
            'banner_title' => 'bannerTitle',
            'banner_summary' => 'bannerSummary',
            'modal_title' => 'modalTitle',
            'modal_intro_one' => 'modalIntroOne',
            'modal_intro_two' => 'modalIntroTwo',
            'preferences_title' => 'preferencesTitle',
            'save_label' => 'save',
            'accept_label' => 'accept',
            'decline_label' => 'decline',
            'settings_label' => 'settingsLink',
        ];

        $result = $defaults;
        foreach ($map as $dbKey => $copyKey) {
            if (!array_key_exists($dbKey, $row)) {
                continue;
            }

            $value = (string) $row[$dbKey];
            if (in_array($dbKey, ['banner_summary', 'modal_intro_one', 'modal_intro_two'], true)) {
                $value = LinkAbstractor::translateFrom($value);
            }
            $value = trim($value);
            if ($dbKey === 'modal_intro_two') {
                $result[$copyKey] = $value;
                continue;
            }
            if ($value !== '') {
                $result[$copyKey] = $value;
            }
        }

        if (array_key_exists('settings_label', $row)) {
            $settingsLabel = trim((string) $row['settings_label']);
            if ($settingsLabel !== '') {
                $result['customize'] = $settingsLabel;
                $result['settingsLink'] = $settingsLabel;
            }
        }

        return $result;
    }

    public function getActiveCookiesBySite($siteid = null)
    {
        $args = [0, 1];
        if ($this->hasManagedCookieCleanupColumns()) {
            $sql = 'SELECT id, always, site_id, managed_cookie_names, managed_cookie_domain, managed_cookie_path FROM ' . self::TABLE_COOKIE . ' WHERE remove=? AND active=?';
        } else {
            $sql = 'SELECT id, always, site_id FROM ' . self::TABLE_COOKIE . ' WHERE remove=? AND active=?';
        }

        if ($siteid !== null) {
            $args[] = (int) $siteid;
            $sql .= ' AND site_id=?';
        }

        if ($this->hasSortOrderColumn()) {
            $sql .= ' ORDER BY always DESC, sort_order ASC, id ASC';
        } else {
            $sql .= ' ORDER BY always DESC, id DESC';
        }

        $rows = $this->db->fetchAll($sql, $args);
        foreach ($rows as &$row) {
            $this->hydrateManagedCookieCleanupFields($row);
        }
        unset($row);

        return $rows;
    }

    public function getManagedCookieCleanupMap($siteid = null)
    {
        $result = [];

        foreach ($this->getActiveCookiesBySite($siteid) as $row) {
            $patterns = $this->parseManagedCookiePatterns(isset($row['managed_cookie_names']) ? $row['managed_cookie_names'] : '');
            if (empty($patterns)) {
                continue;
            }

            $result[(string) ((int) $row['id'])] = [
                'always' => !empty($row['always']),
                'domain' => $this->sanitizeManagedCookieDomain(isset($row['managed_cookie_domain']) ? $row['managed_cookie_domain'] : ''),
                'path' => $this->sanitizeManagedCookiePath(isset($row['managed_cookie_path']) ? $row['managed_cookie_path'] : self::MANAGED_COOKIE_PATH_DEFAULT),
                'cookies' => $patterns,
            ];
        }

        return $result;
    }

    public function getConsentVersion($siteid = null)
    {
        $siteId = (int) $siteid;
        if ($siteId <= 0) {
            return 'global';
        }

        $payload = [
            'site_id' => $siteId,
            'cookies' => [],
            'content' => [],
            'popup_copy' => [],
        ];

        try {
            $cookies = $this->db->fetchAll('SELECT id, always, code_header, code_body, code_footer FROM ' . self::TABLE_COOKIE . ' WHERE remove=? AND active=? AND site_id=? ORDER BY id ASC', [0, 1, $siteId]);
        } catch (\Exception $e) {
            return 'legacy';
        }

        $cookieIds = [];
        foreach ($cookies as $row) {
            $cookieId = (int) $row['id'];
            $cookieIds[] = $cookieId;
            $payload['cookies'][] = [
                'id' => $cookieId,
                'always' => (int) $row['always'],
                'code_header' => (string) $row['code_header'],
                'code_body' => (string) $row['code_body'],
                'code_footer' => (string) $row['code_footer'],
            ];
        }

        if (!empty($cookieIds)) {
            $placeholders = implode(',', array_fill(0, count($cookieIds), '?'));
            $contentRows = $this->db->fetchAll('SELECT cid, lang, lang_code, name, content FROM ' . self::TABLE_CONTENT . ' WHERE cid IN (' . $placeholders . ') ORDER BY cid ASC, lang ASC, lang_code ASC', $cookieIds);
            foreach ($contentRows as $row) {
                $payload['content'][] = [
                    'cid' => (int) $row['cid'],
                    'lang' => (int) $row['lang'],
                    'lang_code' => (string) $row['lang_code'],
                    'name' => (string) $row['name'],
                    'content' => (string) $row['content'],
                ];
            }
        }

        try {
            $popupRows = $this->db->fetchAll('SELECT * FROM ' . self::TABLE_POPUP_COPY . ' WHERE site_id=? ORDER BY lang ASC, lang_code ASC, id ASC', [$siteId]);
            foreach ($popupRows as $row) {
                $payload['popup_copy'][] = [
                    'lang' => (int) $row['lang'],
                    'lang_code' => (string) $row['lang_code'],
                    'banner_title' => (string) $row['banner_title'],
                    'banner_summary' => (string) $row['banner_summary'],
                    'modal_title' => (string) $row['modal_title'],
                    'modal_intro_one' => (string) $row['modal_intro_one'],
                    'modal_intro_two' => (string) $row['modal_intro_two'],
                    'preferences_title' => (string) $row['preferences_title'],
                    'save_label' => (string) $row['save_label'],
                    'accept_label' => (string) $row['accept_label'],
                    'decline_label' => isset($row['decline_label']) ? (string) $row['decline_label'] : '',
                    'settings_label' => isset($row['settings_label']) ? (string) $row['settings_label'] : '',
                ];
            }
        } catch (\Exception $e) {
            // Older installs can still save consent; popup copy simply won't affect the hash until upgraded.
        }

        return substr(hash('sha256', json_encode($payload)), 0, 16);
    }

    public function getDisplayTimezone()
    {
        return self::DISPLAY_TIMEZONE;
    }

    public function getDisplayTimezoneLabel()
    {
        return self::DISPLAY_TIMEZONE_LABEL;
    }

    public function getConsentCookieSettings()
    {
        return [
            'consent_cookie_lifetime_days' => $this->getConsentCookieLifetimeDays(),
            'min_days' => self::MIN_CONSENT_COOKIE_LIFETIME_DAYS,
            'max_days' => self::MAX_CONSENT_COOKIE_LIFETIME_DAYS,
        ];
    }

    public function getConsentCookieLifetimeDays()
    {
        try {
            $rows = $this->db->fetchAll(
                'SELECT setting_value FROM ' . self::TABLE_SETTING . ' WHERE setting_key=? LIMIT 1',
                [self::SETTING_CONSENT_COOKIE_LIFETIME_DAYS]
            );
        } catch (\Exception $e) {
            return self::DEFAULT_CONSENT_COOKIE_LIFETIME_DAYS;
        }

        if (!$rows || count($rows) === 0) {
            return self::DEFAULT_CONSENT_COOKIE_LIFETIME_DAYS;
        }

        return $this->sanitizeConsentCookieLifetimeDays($rows[0]['setting_value']);
    }

    public function getConsentCookieLifetimeSeconds()
    {
        return $this->getConsentCookieLifetimeDays() * 24 * 60 * 60;
    }

    public function sanitizeManagedCookieDomain($domain)
    {
        $domain = strtolower(trim((string) $domain));
        $domain = ltrim($domain, '.');
        if ($domain === '') {
            return '';
        }

        if (strpos($domain, '://') !== false || strpos($domain, '/') !== false || preg_match('/[\s;,]/', $domain)) {
            return '';
        }

        return preg_match('/^[a-z0-9-]+(?:\.[a-z0-9-]+)*$/', $domain) ? $domain : '';
    }

    public function sanitizeManagedCookiePath($path)
    {
        $path = trim((string) $path);
        if ($path === '') {
            return self::MANAGED_COOKIE_PATH_DEFAULT;
        }

        if ($path[0] !== '/' || preg_match('/[\s;,]/', $path)) {
            return self::MANAGED_COOKIE_PATH_DEFAULT;
        }

        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
        }

        return $path !== '' ? $path : self::MANAGED_COOKIE_PATH_DEFAULT;
    }

    public function parseManagedCookiePatterns($value)
    {
        $lines = preg_split('/\R+/', (string) $value);
        $result = [];
        $seen = [];

        foreach ($lines as $line) {
            $pattern = trim((string) $line);
            if ($pattern === '') {
                continue;
            }

            if (substr_count($pattern, '*') > 1 || (strpos($pattern, '*') !== false && substr($pattern, -1) !== '*')) {
                continue;
            }

            $isPrefix = substr($pattern, -1) === '*';
            if ($isPrefix) {
                $pattern = substr($pattern, 0, -1);
            }

            $pattern = trim((string) $pattern);
            if ($pattern === '') {
                continue;
            }

            $key = ($isPrefix ? 'prefix:' : 'exact:') . $pattern;
            if (isset($seen[$key])) {
                continue;
            }

            $result[] = [
                'match' => $isPrefix ? 'prefix' : 'exact',
                'pattern' => $pattern,
            ];
            $seen[$key] = true;
        }

        return $result;
    }

    public function saveConsentCookieLifetimeDays($days)
    {
        $normalizedDays = $this->sanitizeConsentCookieLifetimeDays($days);
        $this->ensureConsentCookieSettingsTable();

        $rows = $this->db->fetchAll(
            'SELECT setting_key FROM ' . self::TABLE_SETTING . ' WHERE setting_key=? LIMIT 1',
            [self::SETTING_CONSENT_COOKIE_LIFETIME_DAYS]
        );

        if ($rows && count($rows) > 0) {
            $this->db->executeQuery(
                'UPDATE ' . self::TABLE_SETTING . ' SET setting_value=?, updated=? WHERE setting_key=?',
                [(string) $normalizedDays, $this->now(), self::SETTING_CONSENT_COOKIE_LIFETIME_DAYS]
            );

            return true;
        }

        $this->db->executeQuery(
            'INSERT INTO ' . self::TABLE_SETTING . ' (setting_key, setting_value, updated) VALUES (?, ?, ?)',
            [self::SETTING_CONSENT_COOKIE_LIFETIME_DAYS, (string) $normalizedDays, $this->now()]
        );

        return true;
    }

    public function formatLogTimestamp($value, $format = 'j M Y H:i:s')
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }

        try {
            $date = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
            return $date->setTimezone(new \DateTimeZone(self::DISPLAY_TIMEZONE))->format($format);
        } catch (\Exception $e) {
            return $value;
        }
    }

    protected function hasSortOrderColumn()
    {
        if ($this->hasSortOrderColumn !== null) {
            return $this->hasSortOrderColumn;
        }

        try {
            $this->db->fetchAll('SELECT sort_order FROM ' . self::TABLE_COOKIE . ' LIMIT 1');
            $this->hasSortOrderColumn = true;
        } catch (\Exception $e) {
            try {
                $this->db->executeQuery('ALTER TABLE ' . self::TABLE_COOKIE . ' ADD sort_order INT NOT NULL DEFAULT 0');
            } catch (\Exception $innerException) {
                // Ignore; column may already exist or DB user may not have ALTER permissions.
            }

            try {
                $this->db->executeQuery('CREATE INDEX idx_sort_order ON ' . self::TABLE_COOKIE . ' (sort_order)');
            } catch (\Exception $innerException) {
                // Ignore; index may already exist.
            }

            try {
                $this->db->fetchAll('SELECT sort_order FROM ' . self::TABLE_COOKIE . ' LIMIT 1');
                $this->hasSortOrderColumn = true;
            } catch (\Exception $innerException) {
                $this->hasSortOrderColumn = false;
            }
        }

        return $this->hasSortOrderColumn;
    }

    protected function hasConsentIdColumn()
    {
        if ($this->hasConsentIdColumn !== null) {
            return $this->hasConsentIdColumn;
        }

        try {
            $this->db->fetchAll('SELECT consent_id FROM ' . self::TABLE_IP . ' LIMIT 1');
            $this->hasConsentIdColumn = true;
        } catch (\Exception $e) {
            try {
                $this->db->executeQuery('ALTER TABLE ' . self::TABLE_IP . ' ADD consent_id VARCHAR(64) NULL');
            } catch (\Exception $innerException) {
                // Ignore; column may already exist or DB user may not have ALTER permissions.
            }

            try {
                $this->db->executeQuery('CREATE INDEX idx_consent_id ON ' . self::TABLE_IP . ' (consent_id)');
            } catch (\Exception $innerException) {
                // Ignore; index may already exist.
            }

            try {
                $this->db->fetchAll('SELECT consent_id FROM ' . self::TABLE_IP . ' LIMIT 1');
                $this->hasConsentIdColumn = true;
            } catch (\Exception $innerException) {
                $this->hasConsentIdColumn = false;
            }
        }

        return $this->hasConsentIdColumn;
    }

    protected function hasConsentVersionColumn()
    {
        if ($this->hasConsentVersionColumn !== null) {
            return $this->hasConsentVersionColumn;
        }

        try {
            $this->db->fetchAll('SELECT consent_version FROM ' . self::TABLE_LOG . ' LIMIT 1');
            $this->hasConsentVersionColumn = true;
        } catch (\Exception $e) {
            try {
                $this->db->executeQuery('ALTER TABLE ' . self::TABLE_LOG . ' ADD consent_version VARCHAR(64) NULL');
            } catch (\Exception $innerException) {
                // Ignore; column may already exist or DB user may not have ALTER permissions.
            }

            try {
                $this->db->executeQuery('CREATE INDEX idx_consent_version ON ' . self::TABLE_LOG . ' (consent_version)');
            } catch (\Exception $innerException) {
                // Ignore; index may already exist.
            }

            try {
                $this->db->fetchAll('SELECT consent_version FROM ' . self::TABLE_LOG . ' LIMIT 1');
                $this->hasConsentVersionColumn = true;
            } catch (\Exception $innerException) {
                $this->hasConsentVersionColumn = false;
            }
        }

        return $this->hasConsentVersionColumn;
    }

    protected function hasManagedCookieCleanupColumns()
    {
        if ($this->hasManagedCookieCleanupColumns !== null) {
            return $this->hasManagedCookieCleanupColumns;
        }

        try {
            $this->db->fetchAll('SELECT managed_cookie_names, managed_cookie_domain, managed_cookie_path FROM ' . self::TABLE_COOKIE . ' LIMIT 1');
            $this->hasManagedCookieCleanupColumns = true;
        } catch (\Exception $e) {
            foreach ([
                'ALTER TABLE ' . self::TABLE_COOKIE . ' ADD managed_cookie_names TEXT NULL',
                'ALTER TABLE ' . self::TABLE_COOKIE . ' ADD managed_cookie_domain VARCHAR(255) NULL',
                "ALTER TABLE " . self::TABLE_COOKIE . " ADD managed_cookie_path VARCHAR(255) NULL DEFAULT '/'",
            ] as $sql) {
                try {
                    $this->db->executeQuery($sql);
                } catch (\Exception $innerException) {
                    // Ignore; column may already exist or DB user may not have ALTER permissions.
                }
            }

            try {
                $this->db->fetchAll('SELECT managed_cookie_names, managed_cookie_domain, managed_cookie_path FROM ' . self::TABLE_COOKIE . ' LIMIT 1');
                $this->hasManagedCookieCleanupColumns = true;
            } catch (\Exception $innerException) {
                $this->hasManagedCookieCleanupColumns = false;
            }
        }

        return $this->hasManagedCookieCleanupColumns;
    }

    protected function ensureConsentCookieSettingsTable()
    {
        $this->db->executeQuery("CREATE TABLE IF NOT EXISTS " . self::TABLE_SETTING . " (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value TEXT NULL,
            updated DATETIME NULL
        )");
    }

    protected function sanitizeConsentCookieLifetimeDays($days)
    {
        if (!is_scalar($days) || !preg_match('/^\d+$/', trim((string) $days))) {
            return self::DEFAULT_CONSENT_COOKIE_LIFETIME_DAYS;
        }

        $normalizedDays = (int) trim((string) $days);
        if ($normalizedDays < self::MIN_CONSENT_COOKIE_LIFETIME_DAYS) {
            return self::MIN_CONSENT_COOKIE_LIFETIME_DAYS;
        }

        if ($normalizedDays > self::MAX_CONSENT_COOKIE_LIFETIME_DAYS) {
            return self::MAX_CONSENT_COOKIE_LIFETIME_DAYS;
        }

        return $normalizedDays;
    }

    public function DBInsertLog($data = null)
    {
        return $this->insertLog($data, false);
    }

    public function DBInsertLogV2($data = null)
    {
        return $this->insertLog($data, true);
    }

    public function DBSelectLog($current = null, $max = null)
    {
        return $this->selectLogRows($current, $max, null);
    }

    public function DBSelectLogV2($current = null, $max = null, $siteid = null)
    {
        return $this->selectLogRows($current, $max, $siteid);
    }

    public function DBCountLog()
    {
        $sql = 'SELECT COUNT(DISTINCT pid) AS total FROM ' . self::TABLE_LOG;
        $result = $this->db->fetchAll($sql);

        return $result && count($result) > 0 ? (int) $result[0]['total'] : 0;
    }

    public function DBCountLogV2($siteid = null)
    {
        $sql = 'SELECT COUNT(DISTINCT pid) AS total FROM ' . self::TABLE_LOG . ' WHERE site_id=?';
        $result = $this->db->fetchAll($sql, [(int) $siteid]);

        return $result && count($result) > 0 ? (int) $result[0]['total'] : 0;
    }

    public function DBSelectLogByIP($ip = null)
    {
        return $this->selectLogByIp($ip, null);
    }

    public function DBSelectLogByIPV2($ip = null, $siteid = null, $consentVersion = null)
    {
        return $this->selectLogByIp($ip, $siteid, $consentVersion);
    }

    public function DBSelectLogByConsentId($consentId = null, $consentVersion = null)
    {
        return $this->selectLogByConsentId($consentId, null, $consentVersion);
    }

    public function DBSelectLogByConsentIdV2($consentId = null, $siteid = null, $consentVersion = null)
    {
        return $this->selectLogByConsentId($consentId, $siteid, $consentVersion);
    }

    public function adoptLegacyLogByConsentIdV2($consentId = null, $ip = null, $siteid = null, $consentVersion = null)
    {
        return $this->adoptLegacyLogByConsentId($consentId, $ip, $siteid, $consentVersion);
    }

    protected function selectCookieById($id, $groupBySite)
    {
        if ($id === null) {
            return [];
        }

        $args = [(int) $id, 0];
        $sql = 'SELECT * FROM ' . self::TABLE_COOKIE . ' WHERE id=? AND remove=?';
        $rows = $this->db->fetchAll($sql, $args);

        if (!$rows || count($rows) === 0) {
            return [];
        }

        $result = $rows[0];
        $contentRows = $this->db->fetchAll('SELECT * FROM ' . self::TABLE_CONTENT . ' WHERE cid=?', [(int) $result['id']]);
        $this->hydrateManagedCookieCleanupFields($result);

        foreach ($contentRows as $row) {
            if ($row['lang_code'] === null) {
                continue;
            }

            $content = LinkAbstractor::translateFrom($row['content']);
            if ($groupBySite) {
                $result['label'][$result['site_id']][$row['lang_code']] = $row['name'];
                $result['content'][$result['site_id']][$row['lang_code']] = $content;
            } else {
                $result[$row['lang_code']] = [
                    'name' => $row['name'],
                    'content' => $content,
                ];
            }
        }

        return $result;
    }

    protected function hydrateManagedCookieCleanupFields(array &$row)
    {
        $row['managed_cookie_names'] = isset($row['managed_cookie_names']) ? trim((string) $row['managed_cookie_names']) : '';
        $row['managed_cookie_domain'] = $this->sanitizeManagedCookieDomain(isset($row['managed_cookie_domain']) ? $row['managed_cookie_domain'] : '');
        $row['managed_cookie_path'] = $this->sanitizeManagedCookiePath(isset($row['managed_cookie_path']) ? $row['managed_cookie_path'] : self::MANAGED_COOKIE_PATH_DEFAULT);
    }

    protected function insertLog($data, $withSite)
    {
        $consentId = isset($data['consent_id']) ? trim((string) $data['consent_id']) : '';
        $ipAddress = isset($data['ip']) && is_scalar($data['ip']) ? trim((string) $data['ip']) : '';
        if (strlen($ipAddress) > 45) {
            $ipAddress = substr($ipAddress, 0, 45);
        }

        if ($data === null || $consentId === '') {
            return false;
        }

        if ($withSite && empty($data['site_id'])) {
            return false;
        }

        $consents = isset($data['data']) && is_array($data['data']) ? array_unique(array_map('intval', $data['data'])) : [];
        $consents = array_values(array_filter($consents, function ($consentId) {
            return $consentId > 0;
        }));

        if (empty($consents)) {
            $consents = [0];
        }

        $now = $this->now();
        $consentVersion = isset($data['consent_version']) ? trim((string) $data['consent_version']) : null;
        $hasConsentVersionColumn = $this->hasConsentVersionColumn();
        $this->db->beginTransaction();

        try {
            if ($this->hasConsentIdColumn()) {
                $this->db->executeQuery('INSERT INTO ' . self::TABLE_IP . ' (consent_id, IP, created) VALUES (?, ?, ?)', [$consentId, $ipAddress, $now]);
            } else {
                $this->db->executeQuery('INSERT INTO ' . self::TABLE_IP . ' (IP, created) VALUES (?, ?)', [$consentId, $now]);
            }
            $logId = $this->db->lastInsertId();

            foreach ($consents as $consentId) {
                $columns = ['pid', 'cid', 'site_id', 'created'];
                $args = [(int) $logId, (int) $consentId, $withSite ? (int) $data['site_id'] : 0, $now];

                if ($hasConsentVersionColumn) {
                    $columns[] = 'consent_version';
                    $args[] = $consentVersion;
                }

                $sql = 'INSERT INTO ' . self::TABLE_LOG . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
                $this->db->executeQuery($sql, $args);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            try {
                $this->db->rollBack();
            } catch (\Throwable $rollbackException) {
                // Ignore rollback failures; the original insert error is enough to fail the save.
            }

            return false;
        }

        return true;
    }

    protected function selectLogRows($current, $max, $siteid)
    {
        $offset = $this->getOffset($current, $max);
        $limit = $this->getMax($max);
        $result = [];
        $headerArgs = [0, 1];
        $headerSql = 'SELECT id, name FROM ' . self::TABLE_COOKIE . ' WHERE remove=? AND active=?';

        if ($siteid !== null) {
            $headerArgs[] = (int) $siteid;
            $headerSql .= ' AND site_id=?';
        }

        if ($this->hasSortOrderColumn()) {
            $headerSql .= ' ORDER BY always DESC, sort_order ASC, id ASC';
        } else {
            $headerSql .= ' ORDER BY name ASC';
        }
        $headers = $this->db->fetchAll($headerSql, $headerArgs);
        $result['header'] = $headers;

        $identifierColumn = $this->hasConsentIdColumn()
            ? "COALESCE(NULLIF(B.consent_id, ''), B.IP) AS consent_id"
            : 'B.IP AS consent_id';
        $columns = ['A.pid AS id', $identifierColumn, 'B.IP AS ip'];
        foreach ($headers as $header) {
            $cookieId = (int) $header['id'];
            $columns[] = 'SUM(CASE WHEN A.cid=' . $cookieId . ' THEN A.cid ELSE 0 END) AS `' . $cookieId . '`';
        }
        if ($this->hasConsentVersionColumn()) {
            $columns[] = 'MAX(A.consent_version) AS consent_version';
        }
        $columns[] = 'B.created AS created';

        $sql = 'SELECT ' . implode(', ', $columns) . ' FROM ' . self::TABLE_LOG . ' A INNER JOIN ' . self::TABLE_IP . ' B ON (A.pid = B.id)';
        $args = [];

        if ($siteid !== null) {
            $sql .= ' WHERE A.site_id=?';
            $args[] = (int) $siteid;
        }

        if ($this->hasConsentIdColumn()) {
            $sql .= ' GROUP BY A.pid, B.consent_id, B.IP, B.created';
        } else {
            $sql .= ' GROUP BY A.pid, B.IP, B.created';
        }

        $sql .= ' ORDER BY B.created DESC LIMIT ' . $offset . ', ' . $limit;
        $result['data'] = $this->db->fetchAll($sql, $args);

        return $result;
    }

    protected function getCookieMap($siteid)
    {
        $cookieMap = [
            'site_id' => [],
            'id' => [],
            'header' => [],
            'body' => [],
            'footer' => [],
        ];

        $cookieArgs = [0, 1];
        $cookieSql = 'SELECT * FROM ' . self::TABLE_COOKIE . ' WHERE remove=? AND active=?';
        if ($siteid !== null) {
            $cookieArgs[] = (int) $siteid;
            $cookieSql .= ' AND site_id=?';
        }
        $cookieSql .= ' ORDER BY id DESC';

        foreach ($this->db->fetchAll($cookieSql, $cookieArgs) as $row) {
            $cookieId = (int) $row['id'];
            $cookieMap['site_id'][$cookieId] = (int) $row['site_id'];
            $cookieMap['id'][$cookieId] = $cookieId;
            $cookieMap['header'][$cookieId] = $row['code_header'];
            $cookieMap['body'][$cookieId] = $row['code_body'];
            $cookieMap['footer'][$cookieId] = $row['code_footer'];
        }

        return $cookieMap;
    }

    protected function buildLogResultForPid($pid, $siteid, array $cookieMap)
    {
        if ($pid === null) {
            return [];
        }

        $result = [
            'saved' => true,
        ];
        $args = [(int) $pid];
        $sql = 'SELECT cid FROM ' . self::TABLE_LOG . ' WHERE pid=?';
        if ($siteid !== null) {
            $args[] = (int) $siteid;
            $sql .= ' AND site_id=?';
        }

        $logRows = $this->db->fetchAll($sql, $args);
        foreach ($logRows as $row) {
            $cookieId = (int) $row['cid'];
            if (!isset($cookieMap['id'][$cookieId])) {
                continue;
            }

            $result['code']['site_id'][] = $cookieMap['site_id'][$cookieId];
            $result['code']['id'][] = $cookieMap['id'][$cookieId];
            $result['code']['header'][] = $cookieMap['header'][$cookieId];
            $result['code']['body'][] = $cookieMap['body'][$cookieId];
            $result['code']['footer'][] = $cookieMap['footer'][$cookieId];
        }

        return $result;
    }

    protected function selectLogByIp($ip, $siteid, $consentVersion = null)
    {
        if ($ip === null || $ip === '') {
            return [];
        }

        $cookieMap = $this->getCookieMap($siteid);
        $pid = $this->getLatestLogPidByIp($ip, $siteid, $consentVersion);

        return $this->buildLogResultForPid($pid, $siteid, $cookieMap);
    }

    protected function selectLogByConsentId($consentId, $siteid, $consentVersion = null)
    {
        $consentId = trim((string) $consentId);
        if ($consentId === '') {
            return [];
        }

        $cookieMap = $this->getCookieMap($siteid);
        $pid = $this->getLatestLogPidByConsentId($consentId, $siteid, $consentVersion);

        return $this->buildLogResultForPid($pid, $siteid, $cookieMap);
    }

    protected function adoptLegacyLogByConsentId($consentId, $ip, $siteid, $consentVersion = null)
    {
        $consentId = trim((string) $consentId);
        $ip = trim((string) $ip);
        if ($consentId === '' || $ip === '') {
            return [];
        }

        if (!$this->hasConsentIdColumn()) {
            return [];
        }

        $pid = $this->getLatestLogPidByIp($ip, $siteid, $consentVersion);
        if ($pid === null) {
            return [];
        }

        $this->db->executeQuery(
            'UPDATE ' . self::TABLE_IP . ' SET consent_id=? WHERE id=? AND (consent_id IS NULL OR consent_id=?)',
            [$consentId, (int) $pid, '']
        );

        return $this->selectLogByConsentId($consentId, $siteid, $consentVersion);
    }

    protected function getLatestLogPidByIp($ip, $siteid = null, $consentVersion = null)
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        $version = trim((string) $consentVersion);
        $shouldFilterVersion = $version !== '' && $this->hasConsentVersionColumn();
        $legacyOnly = $this->hasConsentIdColumn();

        if ($siteid !== null) {
            $args = [$ip, (int) $siteid];
            $sql = 'SELECT A.pid AS pid FROM ' . self::TABLE_LOG . ' A INNER JOIN ' . self::TABLE_IP . ' B ON (A.pid = B.id) WHERE B.IP=? AND A.site_id=?';
            if ($legacyOnly) {
                $args[] = '';
                $sql .= ' AND (B.consent_id IS NULL OR B.consent_id=?)';
            }
            if ($shouldFilterVersion) {
                $args[] = $version;
                $sql .= ' AND A.consent_version=?';
            }
            $sql .= ' GROUP BY A.pid, B.created ORDER BY B.created DESC, A.pid DESC LIMIT 1';
            $rows = $this->db->fetchAll($sql, $args);
        } else {
            if ($shouldFilterVersion) {
                $args = [$ip];
                $sql = 'SELECT A.pid AS pid FROM ' . self::TABLE_LOG . ' A INNER JOIN ' . self::TABLE_IP . ' B ON (A.pid = B.id) WHERE B.IP=?';
                if ($legacyOnly) {
                    $args[] = '';
                    $sql .= ' AND (B.consent_id IS NULL OR B.consent_id=?)';
                }
                $args[] = $version;
                $sql .= ' AND A.consent_version=? GROUP BY A.pid, B.created ORDER BY B.created DESC, A.pid DESC LIMIT 1';
                $rows = $this->db->fetchAll($sql, $args);
            } else {
                $args = [$ip];
                $sql = 'SELECT id AS pid FROM ' . self::TABLE_IP . ' WHERE IP=?';
                if ($legacyOnly) {
                    $args[] = '';
                    $sql .= ' AND (consent_id IS NULL OR consent_id=?)';
                }
                $sql .= ' ORDER BY created DESC, id DESC LIMIT 1';
                $rows = $this->db->fetchAll($sql, $args);
            }
        }

        if (!$rows || count($rows) === 0) {
            return null;
        }

        return (int) $rows[0]['pid'];
    }

    protected function getLatestLogPidByConsentId($consentId, $siteid = null, $consentVersion = null)
    {
        $consentId = trim((string) $consentId);
        if ($consentId === '') {
            return null;
        }

        $version = trim((string) $consentVersion);
        $shouldFilterVersion = $version !== '' && $this->hasConsentVersionColumn();
        $identityColumn = $this->hasConsentIdColumn() ? 'B.consent_id' : 'B.IP';
        $args = [$consentId];
        $sql = 'SELECT A.pid AS pid FROM ' . self::TABLE_LOG . ' A INNER JOIN ' . self::TABLE_IP . ' B ON (A.pid = B.id) WHERE ' . $identityColumn . '=?';

        if ($siteid !== null) {
            $args[] = (int) $siteid;
            $sql .= ' AND A.site_id=?';
        }

        if ($shouldFilterVersion) {
            $args[] = $version;
            $sql .= ' AND A.consent_version=?';
        }

        $sql .= ' GROUP BY A.pid, B.created ORDER BY B.created DESC, A.pid DESC LIMIT 1';
        $rows = $this->db->fetchAll($sql, $args);

        if (!$rows || count($rows) === 0) {
            return null;
        }

        return (int) $rows[0]['pid'];
    }

    protected function getOffset($current, $max)
    {
        $page = (int) ($current ?: 1);
        $limit = $this->getMax($max);

        return $page > 1 ? ($page - 1) * $limit : 0;
    }

    protected function getMax($max)
    {
        $limit = (int) ($max ?: 15);

        return $limit > 0 ? $limit : 15;
    }

    protected function now()
    {
        return gmdate('Y-m-d H:i:s');
    }
}
