<?php

namespace Concrete\Package\KosCookieConsent\Controller\SinglePage\Dashboard\CookieConsent;

use Concrete\Core\Page\Page;
use Concrete\Package\KosCookieConsent\Controller\SinglePage\Dashboard\CookieConsent;

defined('C5_EXECUTE') or die('Access Denied.');

class Logs extends CookieConsent
{
    public function view()
    {
        $data = $this->request->query->all();
        $first = 0;

        $optionSite = $this->kosdesign->getSiteLanguage();
        $options = [];
        foreach ($optionSite as $site) {
            $options[$site['site_id']] = $site['site_name'];
        }

        if (!empty($options)) {
            ksort($options);
            $first = array_key_first($options);
        }

        $params = ['site_id' => $first];
        if (!empty($data['site_id'])) {
            $params['site_id'] = h($data['site_id']);
        }

        $currentPage = Page::getCurrentPage();
        $max = 20;
        $current = 1;
        $count = $this->kosdesign->DBCountLogV2($params['site_id']);
        $limit = (int) ceil($count / $max);

        if (!empty($data['page']) && $data['page'] <= 1) {
            $current = 1;
        } elseif (!empty($data['page']) && $limit > 0 && $data['page'] >= $limit) {
            $current = $limit;
        } elseif (!empty($data['page'])) {
            $current = (int) $data['page'];
        }

        $result = $this->kosdesign->DBSelectLogV2($current, $max, $params['site_id']);
        $pagination = $this->renderPagination($currentPage, $limit, $params, $current);

        $this->set('_post', $params);
        $this->set('result', $result);
        $this->set('pagination', $pagination);
        $this->set('pages', $limit);
        $this->set('current', $current);
    }

    public function renderPagination($current = null, $pagetotal = null, $params = [], $page = 1, $custom = null)
    {
        if ($current === null || !is_object($current) || $pagetotal === null || $pagetotal < 2) {
            return '';
        }

        $queryString = [];
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $queryString[] = $key . '=' . rawurlencode($value);
            }
        }

        $query = count($queryString) > 0 ? '?' . implode('&', $queryString) : '';
        $range = 4;
        $pages = (int) $pagetotal;
        $start = max(1, $page - $range);
        $end = min($pages, $page + $range);
        $link = $custom ?: $current->getCollectionPath();
        $firstLink = $link . $query;

        $html = '<div class="ccm-search-results-pagination"><div class="ccm-pagination-wrapper"><ul class="pagination">';

        if ($start > 1) {
            $html .= '<li class="page-item prev"><a class="page-link" href="' . $firstLink . '">1</a></li>';
            if ($start > 2) {
                $html .= '<li class="page-item prev disabled"><span class="page-link dot">…</span></li>';
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            if ($i === (int) $page) {
                $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                continue;
            }

            $pageLink = $i === 1 ? $firstLink : $link . ($query ? $query . '&page=' . $i : '?page=' . $i);
            $html .= '<li class="page-item"><a class="page-link" href="' . $pageLink . '">' . $i . '</a></li>';
        }

        if ($end < $pages) {
            if ($end < ($pages - 1)) {
                $html .= '<li class="page-item next disabled"><span class="page-link dot">…</span></li>';
            }

            $lastLink = $link . ($query ? $query . '&page=' . $pages : '?page=' . $pages);
            $html .= '<li class="page-item next"><a class="page-link" href="' . $lastLink . '">' . $pages . '</a></li>';
        }

        $html .= '</ul></div></div>';

        return $html;
    }
}
