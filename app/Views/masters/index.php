<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div>
    <div class="page-title">Masters</div>
    <div class="page-desc">Programmes, rates and the location hierarchy — configuration, not code</div>
  </div>
</div>

<div class="panel">
  <div class="tabs">
    <a class="tab <?= $tab === 'programmes' ? 'active' : '' ?>" href="?tab=programmes">Programmes</a>
    <a class="tab <?= $tab === 'locations' ? 'active' : '' ?>" href="?tab=locations">Locations</a>
  </div>

  <?php if ($tab === 'programmes'): ?>
    <div class="header-actions" style="margin-bottom:12px">
      <a class="btn btn-primary btn-sm" href="<?= site_url('admin/masters/programmes/new') ?>">+ New Programme</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Programme</th><th>Amount rule</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($programmes as $p): ?>
          <tr>
            <td><?= esc($p['name']) ?></td>
            <td><?= $p['mode'] === 'min' ? fmt_rupees($p['rate']) . ' minimum per person' . ($p['is_recurring'] ? '/month (Autopay)' : '') : 'Open — amount entered at enrolment' ?></td>
            <td><span class="pill <?= status_pill_class($p['status']) ?>"><?= esc(ucfirst($p['status'])) ?></span></td>
            <td>
              <div class="row-actions">
                <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/masters/programmes/' . $p['id'] . '/edit') ?>">Edit</a>
                <form action="<?= site_url('admin/masters/programmes/' . $p['id'] . '/delete') ?>" method="post">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-danger btn-sm"
                          data-confirm="Delete this programme? Only possible if no enrolments use it.">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="panel" style="background:var(--surface-alt)">
      <div class="panel-head">
        <div>
          <div class="panel-title">Bulk import locations</div>
          <div class="panel-note" style="margin-top:3px">Upload a Prant / Jila / Prakhand spreadsheet — rows matching an existing name are reused, the rest are created.</div>
        </div>
        <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/masters/locations/bulk-import/template') ?>">Download sample template (.xlsx)</a>
      </div>
      <form action="<?= site_url('admin/masters/locations/bulk-import') ?>" method="post" enctype="multipart/form-data" class="row-actions" style="align-items:flex-end">
        <?= csrf_field() ?>
        <div class="field" style="margin:0;flex:1;min-width:240px">
          <label class="f-label">Spreadsheet file (.xlsx, .xls or .csv)</label>
          <input class="f-input" type="file" name="file" accept=".xlsx,.xls,.csv" required>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Import</button>
      </form>
    </div>

    <div class="loc-browser">
      <div class="loc-pane">
        <div class="loc-pane-head">
          <form method="get" style="display:flex">
            <input type="hidden" name="tab" value="locations">
            <input class="f-input" style="padding:6px 8px;font-size:12px" type="search" name="q" value="<?= esc($search) ?>" placeholder="Search Prants…">
          </form>
        </div>
        <div class="loc-pane-list">
          <?php foreach ($prants as $p): ?>
            <a class="loc-item <?= $selectedPrantId === (int) $p['id'] ? 'active' : '' ?>" href="?tab=locations&prant=<?= $p['id'] ?>">
              <span><?= esc($p['name']) ?></span>
              <span class="count"><?= $p['jila_count'] ?></span>
            </a>
          <?php endforeach; ?>
          <?php if (! $prants): ?>
            <div class="loc-empty">No Prants match &ldquo;<?= esc($search) ?>&rdquo;.</div>
          <?php endif; ?>
        </div>
        <div class="loc-pane-add">
          <form action="<?= site_url('admin/masters/locations/prant') ?>" method="post">
            <?= csrf_field() ?>
            <input class="f-input" name="name" placeholder="+ Add Prant" required>
          </form>
        </div>
      </div>

      <div class="loc-pane">
        <div class="loc-pane-head"><div class="panel-title">Jilas<?= $currentPrant ? ' — ' . esc($currentPrant['name']) : '' ?></div></div>
        <div class="loc-pane-list">
          <?php foreach ($jilas as $j): ?>
            <a class="loc-item <?= $selectedJilaId === (int) $j['id'] ? 'active' : '' ?>" href="?tab=locations&prant=<?= $selectedPrantId ?>&jila=<?= $j['id'] ?>">
              <span><?= esc($j['name']) ?></span>
              <span class="count"><?= $j['prakhand_count'] ?></span>
            </a>
          <?php endforeach; ?>
          <?php if (! $jilas): ?>
            <div class="loc-empty"><?= $currentPrant ? 'No Jilas yet — add one below.' : 'Select a Prant.' ?></div>
          <?php endif; ?>
        </div>
        <?php if ($currentPrant): ?>
        <div class="loc-pane-add">
          <form action="<?= site_url('admin/masters/locations/jila') ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="prant_id" value="<?= $selectedPrantId ?>">
            <input class="f-input" name="name" placeholder="+ Add Jila" required>
          </form>
        </div>
        <?php endif; ?>
      </div>

      <div class="loc-pane" style="max-height:none">
        <div class="loc-pane-head"><div class="panel-title">Prakhands<?= $currentJila ? ' — ' . esc($currentJila['name']) : '' ?></div></div>
        <div class="loc-pane-list" style="max-height:520px">
          <?php if ($currentJila): ?>
            <?php foreach ($prakhands as $pr): ?>
              <div class="loc-item" data-row>
                <div data-view style="display:flex;justify-content:space-between;align-items:center;flex:1;gap:10px">
                  <span><?= esc($pr['name']) ?></span>
                  <div class="row-actions">
                    <button type="button" class="btn btn-ghost btn-sm" data-edit-trigger>Rename</button>
                    <form action="<?= site_url('admin/masters/locations/prakhand/' . $pr['id'] . '/delete') ?>" method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="prant_id" value="<?= $selectedPrantId ?>">
                      <button type="submit" class="btn btn-danger btn-sm"
                              data-confirm="Delete this Prakhand? Only possible if no users or enrolments reference it.">Delete</button>
                    </form>
                  </div>
                </div>
                <form data-edit hidden action="<?= site_url('admin/masters/locations/prakhand/' . $pr['id'] . '/rename') ?>" method="post" class="row-actions" style="flex:1">
                  <?= csrf_field() ?>
                  <input type="hidden" name="prant_id" value="<?= $selectedPrantId ?>">
                  <input class="f-input" name="name" value="<?= esc($pr['name']) ?>" style="padding:5px 8px;font-size:12px;flex:1;min-width:140px">
                  <button type="submit" class="btn btn-primary btn-sm">Save</button>
                  <button type="button" class="btn btn-ghost btn-sm" data-edit-cancel>Cancel</button>
                </form>
              </div>
            <?php endforeach; ?>
            <?php if (! $prakhands): ?>
              <div class="loc-empty">No Prakhands yet — add one below.</div>
            <?php endif; ?>
          <?php else: ?>
            <div class="loc-empty"><?= $jilas ? 'Select a Jila.' : 'Select a Prant with Jilas, or add one.' ?></div>
          <?php endif; ?>
        </div>
        <?php if ($currentJila): ?>
        <div class="loc-pane-add">
          <form action="<?= site_url('admin/masters/locations/prakhand') ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="jila_id" value="<?= $selectedJilaId ?>">
            <input type="hidden" name="prant_id" value="<?= $selectedPrantId ?>">
            <input class="f-input" name="name" placeholder="+ Add Prakhand" required>
          </form>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($currentPrant): ?>
    <div class="panel" data-row>
      <div data-view style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
        <div>
          <div class="panel-title"><?= esc($currentPrant['name']) ?></div>
          <div class="panel-note" style="margin-top:3px"><?= $currentPrant['jila_count'] ?> Jila<?= $currentPrant['jila_count'] !== 1 ? 's' : '' ?></div>
        </div>
        <div class="row-actions">
          <button type="button" class="btn btn-secondary btn-sm" data-edit-trigger>Rename Prant</button>
          <form action="<?= site_url('admin/masters/locations/prant/' . $currentPrant['id'] . '/delete') ?>" method="post">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger btn-sm"
                    data-confirm="Delete this Prant? Only possible if it has no Jilas, users or enrolments.">Delete Prant</button>
          </form>
        </div>
      </div>
      <form data-edit hidden action="<?= site_url('admin/masters/locations/prant/' . $currentPrant['id'] . '/rename') ?>" method="post" class="row-actions">
        <?= csrf_field() ?>
        <div class="field" style="margin:0;flex:1;min-width:200px"><label class="f-label">Prant name</label><input class="f-input" name="name" value="<?= esc($currentPrant['name']) ?>" required></div>
        <button type="submit" class="btn btn-primary btn-sm">Save</button>
        <button type="button" class="btn btn-ghost btn-sm" data-edit-cancel>Cancel</button>
      </form>

      <div style="margin-top:18px;padding-top:16px;border-top:1px solid var(--border-soft)">
        <div class="f-label" style="margin-bottom:8px">Languages accepted for enrolment in <?= esc($currentPrant['name']) ?></div>
        <?php if (! $prantLanguages): ?>
          <p class="panel-note" style="margin-bottom:10px">Not configured yet — the enrolment form currently offers all 13 languages for this Prant.</p>
        <?php endif; ?>
        <form action="<?= site_url('admin/masters/locations/prant/' . $currentPrant['id'] . '/languages') ?>" method="post" data-chip-multi>
          <?= csrf_field() ?>
          <div class="chip-row">
            <?php foreach ($allLanguages as $code => $label): ?>
              <label class="chip <?= in_array($code, $prantLanguages, true) ? 'selected' : '' ?>">
                <input type="checkbox" name="languages[]" value="<?= esc($code) ?>" style="display:none" <?= in_array($code, $prantLanguages, true) ? 'checked' : '' ?>>
                <?= esc($label) ?>
              </label>
            <?php endforeach; ?>
          </div>
          <button type="submit" class="btn btn-primary btn-sm" style="margin-top:12px">Save languages</button>
        </form>
      </div>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?= $this->endSection() ?>
