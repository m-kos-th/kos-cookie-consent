<?php defined('C5_EXECUTE') or die('Access denied.'); ?>

<div class="ccm-dashboard-header-buttons">
  <?php if (empty($_type)) { ?>
    <a class="btn btn-primary" href="<?php echo $controller->kosdesign->getLink('add'); ?>"><?php echo t('Add Cookie'); ?></a>
  <?php } ?>
</div>
<div class="setting">
  <?php if (isset($_type) && ($_type === 'add' || $_type === 'edit')) { ?>
    <div class="form">
      <?php
      $editor = Core::make('editor');
      $editor->setAllowFileManager(false);
      ?>
      <form method="post" action="<?php echo $view->action('save', isset($id) ? $id : null); ?>">
        <fieldset>
          <legend><?php echo t('Cookie type name') ?></legend>
          <div class="form-group">
            <?php echo $form->text('name', isset($_post['name']) ? $_post['name'] : '', ['required' => 'required']) ?>
          </div>
          <?php
            $_optionSite = $controller->kosdesign->getSiteLanguage();
            if (!empty($_optionSite)) {
              $_option = ['' => t('Please select site')];

              foreach ($_optionSite as $_val) {
                $_option[$_val['site_id']] = $_val['site_name'];
              }
          ?>
          <legend><?php echo t('Site') ?></legend>
          <div class="form-group">
            <?php echo $form->select('site_id', $_option, isset($_post['site_id']) ? $_post['site_id'] : null, ['required' => 'required']);?>
          </div>
          <?php } ?>
          <legend><?php echo t('Enable') ?></legend>
          <div class="form-group">
            <div class="form-check">
              <?php echo $form->checkbox('active', '1', isset($_post['active']) ? $_post['active'] : '0') ?>
              <label class="form-check-label" for="active"><?php echo t('Enable the cookie option on the website.') ?></label>
            </div>
          </div>
          <legend><?php echo t('Always active?') ?></legend>
          <div class="form-group">
            <div class="form-check">
              <?php echo $form->checkbox('always', '1', isset($_post['always']) ? $_post['always'] : '1') ?>
              <label class="form-check-label" for="always"><?php echo t('If always active. Users cannot disable cookies.') ?></label>
            </div>
          </div>
        </fieldset>
        <hr>
        <fieldset>
          <legend><?php echo t('Cookies') ?></legend>
          <?php
            $_options = $controller->kosdesign->getSiteLanguage();
            foreach ($_options as $_val) {
              $_siteid = $_val['site_id'];
              $_code = $_val['language'];
              $_codetext = '(' . $_val['text'] . ')';
              $_name = 'label[' . $_siteid . '][' . $_code .']';
              $_content = 'content[' . $_siteid . '][' . $_code .']';
          ?>
          <div class="form-language d-none" data-site="<?php echo $_siteid; ?>">
            <div class="form-group">
              <?php echo $form->label($_name, t('Name') . ' ' . $_codetext) ?>
              <?php echo $form->text($_name, isset($_post['label'][$_siteid][$_code]) ? $_post['label'][$_siteid][$_code] : null) ?>
            </div>
            <div class="form-group">
              <?php echo $form->label($_content, t('Description') . ' ' . $_codetext) ?>
              <?php echo $editor->outputStandardEditor($_content, isset($_post['content'][$_siteid][$_code]) ? $_post['content'][$_siteid][$_code] : null); ?>
            </div>
          </div>
          <?php } ?>
          <div class="form-group">
            <?php echo $form->label('code_header', t('Header Tracking Codes')) ?>
            <?php echo $form->textarea('code_header', isset($_post['code_header']) ? $_post['code_header'] : '', ['style' => 'height: 150px;', 'class' => 'form-control font-monospace', 'spellcheck' => 'false']) ?>
          </div>
          <div class="form-group">
            <?php echo $form->label('code_body', t('Body Tracking Codes')) ?>
            <?php echo $form->textarea('code_body', isset($_post['code_body']) ? $_post['code_body'] : '', ['style' => 'height: 150px;', 'class' => 'form-control font-monospace', 'spellcheck' => 'false']) ?>
          </div>
          <div class="form-group">
            <?php echo $form->label('code_footer', t('Footer Tracking Codes')) ?>
            <?php echo $form->textarea('code_footer', isset($_post['code_footer']) ? $_post['code_footer'] : '', ['style' => 'height: 150px;', 'class' => 'form-control font-monospace', 'spellcheck' => 'false']) ?>
          </div>
          <hr>
          <legend><?php echo t('Managed Cookie Cleanup') ?></legend>
          <div class="form-group">
            <?php echo $form->label('managed_cookie_names', t('Cookies to clear on withdrawal')) ?>
            <?php echo $form->textarea('managed_cookie_names', isset($_post['managed_cookie_names']) ? $_post['managed_cookie_names'] : '', ['style' => 'height: 140px;', 'class' => 'form-control font-monospace', 'spellcheck' => 'false', 'placeholder' => "_ga\n_gid\n_ga_*"]) ?>
            <small class="form-text text-muted">
              <?php echo t('Enter one first-party cookie name per line. Use * only at the end for prefix matches, for example %s.', '_ga_*'); ?>
            </small>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <?php echo $form->label('managed_cookie_domain', t('Cookie domain override')) ?>
                <?php echo $form->text('managed_cookie_domain', isset($_post['managed_cookie_domain']) ? $_post['managed_cookie_domain'] : '', ['placeholder' => 'example.com']) ?>
                <small class="form-text text-muted">
                  <?php echo t('Optional. Leave blank for host-only plus current-domain cleanup attempts.'); ?>
                </small>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <?php echo $form->label('managed_cookie_path', t('Cookie path')) ?>
                <?php echo $form->text('managed_cookie_path', isset($_post['managed_cookie_path']) ? $_post['managed_cookie_path'] : '/', ['placeholder' => '/']) ?>
                <small class="form-text text-muted">
                  <?php echo t('Default is %s. The cleanup helper also tries common path variants below the current page.', '/'); ?>
                </small>
              </div>
            </div>
          </div>
          <div class="alert alert-info">
            <?php echo t('Use this for cookies that your site writes on its own domain, such as analytics or marketing cookies. Do not add %s, %s, or %s here because those are required to remember the visitor consent choice.', 'kos_cookie_consent_id', 'consent', 'consent_version'); ?>
            <br>
            <?php echo t('Browser privacy rules prevent this package from deleting third-party cookies that live on another vendor domain, and HttpOnly cookies cannot be removed from frontend JavaScript.'); ?>
          </div>
        </fieldset>

        <div class="ccm-dashboard-form-actions-wrapper">
          <div class="ccm-dashboard-form-actions d-flex justify-content-between">
            <div class="col-12 col-sm-6">
              <a href="<?php echo $controller->kosdesign->getLink() ?>" class="btn btn-default pull-left"><?php echo t('Back') ?></a>
              <?php if (isset($_type) && $_type === 'add') { ?>
                <a href="<?php echo $controller->kosdesign->getLink('add') ?>" class="btn btn-danger pull-left"><?php echo t('Reset') ?></a>
              <?php } ?>
              <?php if (isset($id) && isset($_type) && $_type === 'edit') { ?>
                <a href="<?php echo $controller->kosdesign->getLink('edit/' . $id) ?>" class="btn btn-danger pull-left"><?php echo t('Reset') ?></a>
              <?php } ?>
            </div>
            <div class="col-12 col-sm-6 text-end">
              <input type="hidden" name="token" value="<?php echo $_token; ?>">
              <input type="submit" class="btn btn-primary pull-right btn ccm-input-submit" value="<?php echo t('Save') ?>">
            </div>
          </div>
        </div>
      </form>
    </div>
    <script>
      $(function() {
        var id = $('#site_id').val();
        if (id) {
          $('.form-language[data-site="' + id + '"]').removeClass('d-none');
        }

        $('#site_id').on('change', function() {
          var siteId = $(this).val();
          $('.form-language').addClass('d-none');
          if (siteId) {
            $('.form-language[data-site="' + siteId + '"]').removeClass('d-none');
          }
        });
      });
    </script>
  <?php } else { ?>
  <div class="card mb-4">
    <div class="card-body">
      <h3 class="h5 mb-3"><?php echo t('Consent Cookie Retention'); ?></h3>
      <p class="text-muted">
        <?php echo t('This controls the first-party consent cookies created by this package: %s, %s, and %s.', 'kos_cookie_consent_id', 'consent', 'consent_version'); ?>
      </p>
      <form method="post" action="<?php echo $view->action('save_preferences'); ?>">
        <div class="row g-3 align-items-end">
          <div class="col-md-3">
            <div class="form-group">
              <label for="consent_cookie_lifetime_days"><?php echo t('Retention (days)'); ?></label>
              <input
                type="number"
                min="<?php echo isset($_consentCookieSettings['min_days']) ? (int) $_consentCookieSettings['min_days'] : 1; ?>"
                max="<?php echo isset($_consentCookieSettings['max_days']) ? (int) $_consentCookieSettings['max_days'] : 400; ?>"
                step="1"
                class="form-control"
                id="consent_cookie_lifetime_days"
                name="consent_cookie_lifetime_days"
                value="<?php echo isset($_consentCookieSettings['consent_cookie_lifetime_days']) ? (int) $_consentCookieSettings['consent_cookie_lifetime_days'] : 30; ?>"
                required
              >
            </div>
          </div>
          <div class="col-md-9">
            <p class="small text-muted mb-2">
              <?php echo t('Allowed range: %s to %s days. Modern browsers cap persistent cookie expiry, so values above this range are not allowed.', isset($_consentCookieSettings['min_days']) ? (int) $_consentCookieSettings['min_days'] : 1, isset($_consentCookieSettings['max_days']) ? (int) $_consentCookieSettings['max_days'] : 400); ?>
            </p>
            <p class="small text-muted mb-0">
              <?php echo t('The package writes these cookies with Path=/, SameSite=Lax, and Secure when the site is served over HTTPS. Third-party cookies created by injected tracking scripts must be configured inside those scripts separately.'); ?>
            </p>
          </div>
        </div>
        <div class="mt-3 text-end">
          <input type="hidden" name="token" value="<?php echo $_tokenSettings; ?>">
          <button type="submit" class="btn btn-primary"><?php echo t('Save retention'); ?></button>
        </div>
      </form>
    </div>
  </div>
  <form class="form form-booking-search" method="get" action="<?php echo $view->action('') ?>">
    <div class="row g-2">
      <?php
        $_optionSite = $controller->kosdesign->getSiteLanguage();
        if (!empty($_optionSite)) {
          $_option = ['' => t('All Stores')];

          foreach ($_optionSite as $_val) {
            $_option[$_val['site_id']] = $_val['site_name'];
          }
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
  <table border="0" cellspacing="1" cellpadding="0" class="table table-striped setting-table" id="cookie-sort-table">
    <thead>
      <tr>
        <th class="td-other" style="width: 48px;"></th>
        <th class="td-name"><?php echo t('Name'); ?></th>
        <th class="td-other"><?php echo t('Store'); ?></th>
        <th class="td-other"><?php echo t('Sort'); ?></th>
        <th class="td-other"><?php echo t('Enable'); ?></th>
        <th class="td-other"><?php echo t('Always active?'); ?></th>
        <th class="td-other"><?php echo t('Updated'); ?> (<?php echo h($controller->kosdesign->getDisplayTimezoneLabel()); ?>)</th>
        <th class="td-action"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($result)) { ?>
        <?php foreach ($result as $val) { ?>
          <tr draggable="true" data-sort-id="<?php echo (int) $val['id']; ?>" data-site-id="<?php echo (int) $val['site_id']; ?>">
            <td class="text-center align-middle" style="cursor: move; user-select: none;">&#x2630;</td>
            <td><?php echo $val['name']; ?></td>
            <td><?php echo $val['store_name']; ?></td>
            <td><?php echo isset($val['sort_order']) ? (int) $val['sort_order'] : 0; ?></td>
            <td><?php echo $val['active'] ? 'Yes' : 'No'; ?></td>
            <td><?php echo $val['always'] ? 'Yes' : 'No'; ?></td>
            <td><?php echo h($controller->kosdesign->formatLogTimestamp($val['updated'])); ?></td>
            <td class="text-end td-action">
              <div class="btn-group">
                <a class="btn btn-secondary btn-sm" href="<?php echo $controller->kosdesign->getLink('edit', $val['id']); ?>"><?php echo t('EDIT'); ?></a>
                <a href="#" class="btn btn-danger btn-sm delete-form-answers" data-id="<?php echo $val['id']; ?>" data-name="<?php echo h($val['name']); ?>" data-button="data-delete"><?php echo t('DELETE'); ?></a>
              </div>
            </td>
          </tr>
        <?php } ?>
      <?php } else { ?>
        <tr>
          <td colspan="99">
            <p class="empty text-center" style="margin: 12px 0;"><?php echo t('No information available.'); ?></p>
          </td>
        </tr>
      <?php } ?>
    </tbody>
  </table>
  <?php if ($pages > 1) { ?>
    <div class="ccm-search-results-pagination">
      <div class="ccm-pagination-wrapper">
        <?php
        for ($i = 1; $pages >= $i; $i++) {
          if ($i == 1 && $i == $current) {
            echo '<ul class="pagination"><li class="page-item prev disabled"><span class="page-link">' . t('Prev') . '</span></li>';
          } elseif ($i == 1 && $i != $current) {
            echo '<ul class="pagination"><li class="page-item prev"><a class="page-link" href="' . $controller->kosdesign->getLink() . '?page=' . ($current - 1) . '">' . t('Prev') . '</a></li>';
          }

          if ($i == $current) {
            echo '<li class="page-item active"><span class="page-link">' . $i . '<span class="sr-only">(current)</span></span></li>';
          } else {
            echo '<li class="page-item"><a class="page-link" href="' . $controller->kosdesign->getLink() . '?page=' . $i . '">' . $i . '</a></li>';
          }

          if ($i == $pages && $i == $current) {
            echo '<li class="page-item next disabled"><span class="page-link">' . t('Next') . '</span></li></ul>';
          } elseif ($i == $pages && $i != $current) {
            echo '<li class="page-item next"><a class="page-link" href="' . $controller->kosdesign->getLink() . '?page=' . ($current + 1) . '">' . t('Next') . '</a></li></ul>';
          }
        }
        ?>
      </div>
    </div>
  <?php } ?>
  <script>
    (function() {
      var table = document.getElementById('cookie-sort-table');
      if (!table) {
        return;
      }

      var tbody = table.querySelector('tbody');
      if (!tbody) {
        return;
      }

      var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-sort-id]'));
      if (rows.length < 2) {
        return;
      }

      var dragRow = null;

      function getAfterElement(container, y) {
        var candidates = Array.prototype.slice.call(container.querySelectorAll('tr[data-sort-id]:not(.is-dragging)'));
        var closest = {offset: Number.NEGATIVE_INFINITY, element: null};

        candidates.forEach(function(child) {
          var box = child.getBoundingClientRect();
          var offset = y - box.top - box.height / 2;
          if (offset < 0 && offset > closest.offset) {
            closest = {offset: offset, element: child};
          }
        });

        return closest.element;
      }

      function persistSort() {
        var ids = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-sort-id]')).map(function(row) {
          return row.getAttribute('data-sort-id');
        });

        $.concreteAjax({
          url: '<?php echo $view->action('save_sort_order'); ?>',
          data: {
            ids: ids,
            site_id: '<?php echo isset($_post['site_id']) ? (int) $_post['site_id'] : 0; ?>',
            token: '<?php echo $_tokenSort; ?>'
          },
          success: function() {
            var index = 1;
            tbody.querySelectorAll('tr[data-sort-id]').forEach(function(row) {
              var sortCell = row.children[3];
              if (sortCell) {
                sortCell.textContent = index;
              }
              index++;
            });
          }
        });
      }

      rows.forEach(function(row) {
        row.addEventListener('dragstart', function() {
          dragRow = row;
          row.classList.add('is-dragging');
        });

        row.addEventListener('dragend', function() {
          row.classList.remove('is-dragging');
          dragRow = null;
          persistSort();
        });
      });

      tbody.addEventListener('dragover', function(e) {
        e.preventDefault();
        if (!dragRow) {
          return;
        }

        var afterElement = getAfterElement(tbody, e.clientY);
        if (afterElement == null) {
          tbody.appendChild(dragRow);
        } else {
          tbody.insertBefore(dragRow, afterElement);
        }
      });
    })();

    $('a[data-button=data-delete]').on('click', function() {
      return confirm('Are you sure you want to remove "' + $(this).attr('data-name') + '"? ');
    });

    $('.setting').on('click', 'a[data-button=data-delete]', function() {
      $.concreteAjax({
        url: '<?php echo $controller->kosdesign->getLink('remove'); ?>',
        data: {
          id: $(this).attr('data-id'),
          token: '<?php echo $_tokenDelete; ?>'
        },
        success: function(r) {
          if (r) {
            window.location.href = r.redirectURL;
          }
          jQuery.fn.dialog.closeTop();
        }
      });
      return false;
    });
  </script>
<?php } ?>
</div>
