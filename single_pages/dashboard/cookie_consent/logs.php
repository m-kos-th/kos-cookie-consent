<?php defined('C5_EXECUTE') or die('Access denied.'); ?>
<div class="ccm-dashboard-header-buttons"></div>
<?php
  $_timezoneLabel = $controller->kosdesign->getDisplayTimezoneLabel();
  $_hasConsentVersion = !empty($result['data']) && array_key_exists('consent_version', $result['data'][0]);
?>
<form class="form form-booking-search" method="get" action="<?php echo $view->action('') ?>">
  <div class="row g-2">
    <?php
      $_optionSite = $controller->kosdesign->getSiteLanguage();

      if (!empty($_optionSite)) {
        $_option = [];
        foreach ($_optionSite as $_val) {
          $_option[$_val['site_id']] = $_val['site_name'];
        }
        ksort($_option);
    ?>
    <div class="col-md-3">
      <div class="form-group">
        <?php echo $form->select('site_id', $_option, isset($_post['site_id']) ? $_post['site_id'] : null);?>
      </div>
    </div>
    <?php } ?>
    <div class="col" style="max-width: 120px;">
      <div class="form-action">
        <button type="submit" class="btn btn-primary ccm-input-submit" style="width: 100%;"><?php echo t('Submit'); ?></button>
      </div>
    </div>
  </div>
</form>
<table border="0" cellspacing="1" cellpadding="0" class="table table-striped setting-table">
  <thead>
    <tr>
      <th><?php echo t('Consent ID'); ?></th>
      <th><?php echo t('IP Address'); ?></th>
      <?php if (!empty($result['header'])) { ?>
        <?php foreach ($result['header'] as $head) { ?>
          <th><?php echo $head['name']; ?></th>
        <?php } ?>
      <?php } ?>
      <?php if ($_hasConsentVersion) { ?>
        <th><?php echo t('Consent Version'); ?></th>
      <?php } ?>
      <th><?php echo t('Created'); ?> (<?php echo h($_timezoneLabel); ?>)</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!empty($result['data'])) { ?>
      <?php foreach ($result['data'] as $row) { ?>
        <tr>
          <td><?php echo !empty($row['consent_id']) ? h($row['consent_id']) : '-'; ?></td>
          <td><?php echo !empty($row['ip']) ? h($row['ip']) : '-'; ?></td>
          <?php if (!empty($result['header'])) { ?>
            <?php foreach ($result['header'] as $head) { ?>
              <?php $value = isset($row[$head['id']]) ? $row[$head['id']] : 0; ?>
              <td><?php echo (int) $value === 0 ? '<b class="red">NO</b>' : '<b class="green">YES</b>'; ?></td>
            <?php } ?>
          <?php } ?>
          <?php if ($_hasConsentVersion) { ?>
            <td><?php echo !empty($row['consent_version']) ? h($row['consent_version']) : '-'; ?></td>
          <?php } ?>
          <td><?php echo h($controller->kosdesign->formatLogTimestamp($row['created'])); ?></td>
        </tr>
      <?php } ?>
    <?php } ?>
  </tbody>
</table>
<?php if (!empty($pagination)) { echo $pagination; } ?>
