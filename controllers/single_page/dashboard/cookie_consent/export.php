<?php

namespace Concrete\Package\KosCookieConsent\Controller\SinglePage\Dashboard\CookieConsent;

use Concrete\Package\KosCookieConsent\Controller\SinglePage\Dashboard\CookieConsent;

defined('C5_EXECUTE') or die('Access Denied.');

class Export extends CookieConsent
{
    public function view()
    {
        $siteId = $this->request->query->get('site_id');
        $result = $siteId ? $this->kosdesign->DBSelectLogV2(1, 9999999, (int) $siteId) : $this->kosdesign->DBSelectLog(1, 9999999);

        if (empty($result['header'])) {
            return;
        }

        $hasConsentVersion = !empty($result['data']) && array_key_exists('consent_version', $result['data'][0]);
        $header = ['Consent ID', 'IP Address'];
        foreach ($result['header'] as $column) {
            $header[] = $column['name'];
        }
        if ($hasConsentVersion) {
            $header[] = 'Consent Version';
        }
        $header[] = 'Created (' . $this->kosdesign->getDisplayTimezoneLabel() . ')';

        header('Content-Type: text/csv');
        header('Cache-Control: no-store, no-cache');
        header('Content-Disposition: attachment; filename="COOKIECONSENT-' . gmdate('YmdHis') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, $header);

        foreach ($result['data'] as $row) {
            $data = [
                isset($row['consent_id']) ? $row['consent_id'] : '',
                isset($row['ip']) ? $row['ip'] : '',
            ];
            foreach ($result['header'] as $column) {
                $key = (string) $column['id'];
                $data[] = !empty($row[$key]) ? 'YES' : 'NO';
            }

            if ($hasConsentVersion) {
                $data[] = !empty($row['consent_version']) ? $row['consent_version'] : '';
            }

            $data[] = $this->kosdesign->formatLogTimestamp(isset($row['created']) ? $row['created'] : '');
            fputcsv($out, $data);
        }

        fclose($out);
        exit();
    }
}
