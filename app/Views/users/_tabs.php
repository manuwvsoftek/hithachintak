<div class="page-header">
  <div>
    <div class="page-title">Users &amp; Hierarchy</div>
    <div class="page-desc">Manage user accounts across the Prant &rarr; Jila &rarr; Prakhand &rarr; Karyakarta hierarchy</div>
  </div>
</div>
<div class="tabs">
  <a class="tab <?= $activeTab === 'directory' ? 'active' : '' ?>" href="<?= site_url('admin/users') ?>">Directory</a>
  <?php if (current_permission_level('Users & Hierarchy') === 'Edit' || current_permission_level('Users & Hierarchy') === 'Full'): ?>
    <a class="tab <?= $activeTab === 'new' ? 'active' : '' ?>" href="<?= site_url('admin/users/new') ?>">New User</a>
    <a class="tab <?= $activeTab === 'drafts' ? 'active' : '' ?>" href="<?= site_url('admin/users/drafts') ?>">Pending Drafts<?= $draftCount ? ' (' . $draftCount . ')' : '' ?></a>
  <?php endif; ?>
</div>
