<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= $this->include('users/_tabs') ?>

<div class="panel">
  <div class="panel-head">
    <div>
      <div class="panel-title">Pending user drafts</div>
      <div class="panel-note" style="margin-top:3px">New user forms started but not yet submitted — autosaved as you type, resumable even after a refresh, an accidental close, or a network drop. Drafts untouched for 5 days are automatically discarded.</div>
    </div>
  </div>
  <form class="filter-row" method="get">
    <input type="text" name="q" placeholder="Search name or phone" value="<?= esc($search ?? '') ?>">
    <button type="submit" class="btn btn-secondary btn-sm">Search</button>
  </form>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Phone</th><th>Role</th><th>Last saved</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($drafts as $d): ?>
        <tr>
          <td style="font-weight:700"><?= esc($d['name'] ?: '(no name yet)') ?></td>
          <td class="num"><?= esc($d['phone'] ? mask_phone($d['phone']) : '—') ?></td>
          <td><?= esc($roleLabels[$d['role']] ?? $d['role'] ?? '—') ?></td>
          <td><?= esc(date('d M Y, g:i a', strtotime($d['updated_at']))) ?></td>
          <td class="row-actions">
            <a class="btn btn-primary btn-sm" href="<?= site_url('admin/users/new') ?>?resume=<?= esc($d['id'], 'url') ?>">Resume</a>
            <form action="<?= site_url('admin/users/draft/' . $d['id'] . '/delete') ?>" method="post" style="margin:0">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-ghost btn-sm" data-confirm="Discard this draft? This can't be undone.">Discard</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
       <?php if (! $drafts): ?>
        <tr><td colspan="5" class="panel-note"><?= $search !== '' ? 'No pending drafts match that search.' : 'No pending drafts.' ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
