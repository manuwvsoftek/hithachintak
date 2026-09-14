<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= $this->include('settings/_tabs') ?>

<div class="panel" style="margin-top:16px">
  <div class="panel-head">
    <div>
      <div class="panel-title">Roles &amp; Permissions</div>
      <div class="panel-note" style="margin-top:3px">Enforced on the server for every request — reaching this page at all already means you hold Full on Settings &amp; Integrations.</div>
    </div>
  </div>
  <p class="panel-note" style="margin-bottom:14px">Each level sees only its own scope, nothing wider — a Pranta Admin can't see another Pranta's data, a Jila Admin can't see another Jila's, a Prakhand Admin can't see another Prakhand's, and a Karyakarta sees only the enrolments they personally collected. Dev Admin has no access to operational data by design — only Settings &amp; Integrations (and this page) are configurable for that role, from here.</p>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Module</th><?php foreach ($permRoles as $r): ?><th><?= esc($roleLabels[$r]) ?></th><?php endforeach; ?></tr>
      </thead>
      <tbody>
      <?php foreach ($permMatrix as $module => $row): ?>
        <tr>
          <td style="font-weight:700"><?= esc($module) ?></td>
          <?php foreach ($permRoles as $r): ?>
            <td>
              <?php $level = $row[$r] ?? 'None'; ?>
              <form action="<?= site_url('admin/settings/roles/update') ?>" method="post" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="role" value="<?= esc($r) ?>">
                <input type="hidden" name="module" value="<?= esc($module) ?>">
                <select name="level" class="f-input js-auto-submit" style="padding:4px 6px;font-size:11px">
                  <?php foreach ($permLevels as $lvl): ?>
                    <option value="<?= $lvl ?>" <?= $level === $lvl ? 'selected' : '' ?>><?= $lvl ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
