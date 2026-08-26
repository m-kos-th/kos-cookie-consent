<?php defined('C5_EXECUTE') or die('Access denied.'); ?>

<div class="ccm-dashboard-header-buttons"></div>

<?php
$popupEditor = Core::make('editor');
$popupEditor->setAllowFileManager(false);
$_languages = isset($popupContentLanguages) && is_array($popupContentLanguages) ? $popupContentLanguages : [];
$_popupMap = isset($popupContentMap) && is_array($popupContentMap) ? $popupContentMap : [];
?>

<div class="setting">
  <form method="post" action="<?php echo $view->action('save'); ?>">
    <input type="hidden" name="token_popup_content" value="<?php echo h($_tokenPopupContent); ?>">

    <div class="card mb-4">
      <div class="card-header">
        <strong><?php echo t('Cookie Popup Content'); ?></strong>
      </div>
      <div class="card-body">
        <p class="text-muted mb-4">
          <?php echo t('Manage the multilingual copy shown in the frontend cookie banner and preference panel.'); ?>
        </p>

        <?php if (!empty($_languages)) { ?>
          <?php foreach ($_languages as $_lang) { ?>
            <?php
              $_siteid = (int) $_lang['site_id'];
              $_siteName = (string) $_lang['site_name'];
              $_code = (string) $_lang['language'];
              $_text = (string) $_lang['text'];
              $_copy = isset($_popupMap[$_siteid][$_code]) ? $_popupMap[$_siteid][$_code] : [];
              $_base = 'popup_copy[' . $_siteid . '][' . $_code . ']';
            ?>
            <fieldset class="border rounded p-3 mb-3">
              <legend class="float-none w-auto px-2" style="font-size: 14px; font-weight: 600;">
                <?php echo h($_siteName); ?> (<?php echo h($_text); ?>)
              </legend>

              <div class="form-group mb-2">
                <?php echo $form->label($_base . '[banner_title]', t('Banner title')); ?>
                <?php echo $form->text($_base . '[banner_title]', isset($_copy['banner_title']) ? $_copy['banner_title'] : ''); ?>
              </div>

              <div class="form-group mb-2">
                <?php echo $form->label($_base . '[banner_summary]', t('Banner summary')); ?>
                <?php echo $popupEditor->outputStandardEditor($_base . '[banner_summary]', isset($_copy['banner_summary']) ? $_copy['banner_summary'] : ''); ?>
              </div>

              <div class="form-group mb-2">
                <?php echo $form->label($_base . '[modal_title]', t('Preference panel title')); ?>
                <?php echo $form->text($_base . '[modal_title]', isset($_copy['modal_title']) ? $_copy['modal_title'] : ''); ?>
              </div>

              <div class="form-group mb-2">
                <?php echo $form->label($_base . '[modal_intro_one]', t('Preference panel intro (paragraph 1)')); ?>
                <?php echo $popupEditor->outputStandardEditor($_base . '[modal_intro_one]', isset($_copy['modal_intro_one']) ? $_copy['modal_intro_one'] : ''); ?>
              </div>

              <div class="form-group mb-2">
                <?php echo $form->label($_base . '[modal_intro_two]', t('Preference panel intro (paragraph 2, optional)')); ?>
                <?php echo $popupEditor->outputStandardEditor($_base . '[modal_intro_two]', isset($_copy['modal_intro_two']) ? $_copy['modal_intro_two'] : ''); ?>
              </div>

              <div class="form-group mb-2">
                <?php echo $form->label($_base . '[preferences_title]', t('Preferences heading')); ?>
                <?php echo $form->text($_base . '[preferences_title]', isset($_copy['preferences_title']) ? $_copy['preferences_title'] : ''); ?>
              </div>

              <div class="row g-2">
                <div class="col-md-3">
                  <div class="form-group mb-0">
                    <?php echo $form->label($_base . '[save_label]', t('Save Preferences button label')); ?>
                    <?php echo $form->text($_base . '[save_label]', isset($_copy['save_label']) ? $_copy['save_label'] : ''); ?>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group mb-0">
                    <?php echo $form->label($_base . '[accept_label]', t('Accept All button label')); ?>
                    <?php echo $form->text($_base . '[accept_label]', isset($_copy['accept_label']) ? $_copy['accept_label'] : ''); ?>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group mb-0">
                    <?php echo $form->label($_base . '[decline_label]', t('Reject All button label')); ?>
                    <?php echo $form->text($_base . '[decline_label]', isset($_copy['decline_label']) ? $_copy['decline_label'] : ''); ?>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group mb-0">
                    <?php echo $form->label($_base . '[settings_label]', t('Cookie settings link label')); ?>
                    <?php echo $form->text($_base . '[settings_label]', isset($_copy['settings_label']) ? $_copy['settings_label'] : ''); ?>
                  </div>
                </div>
              </div>
            </fieldset>
          <?php } ?>
        <?php } else { ?>
          <p class="empty text-center" style="margin: 12px 0;"><?php echo t('No site languages available.'); ?></p>
        <?php } ?>
      </div>
    </div>

    <div class="ccm-dashboard-form-actions-wrapper">
      <div class="ccm-dashboard-form-actions text-end">
        <button type="submit" class="btn btn-primary"><?php echo t('Save Content'); ?></button>
      </div>
    </div>
  </form>
</div>
