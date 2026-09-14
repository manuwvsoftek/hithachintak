<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>

<div class="panel" style="max-width:560px">
  <div class="panel-title" style="margin-bottom:10px">Contact Us</div>
  <p class="panel-note">Select your Prant to view its registered Trust's contact details.</p>

  <form method="get" class="field" style="margin:14px 0 0">
    <label class="f-label" for="prant_id">Prant</label>
    <select class="f-input js-auto-submit" id="prant_id" name="prant_id">
      <option value="">Select Prant…</option>
      <?php foreach ($prants as $p): ?>
        <option value="<?= $p['id'] ?>" <?= $selectedId === (int) $p['id'] ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if ($selectedId && ! $selected): ?>
    <p class="panel-note contact-info">Prant not found.</p>
  <?php elseif ($selected): ?>
    <?php if (empty($selected['trust_name'])): ?>
      <p class="panel-note contact-info">Contact details for <?= esc($selected['name']) ?> haven't been published yet — please check back later.</p>
    <?php else: ?>
      <div class="contact-info">
        <div class="contact-field">
          <div class="contact-label">Registered Trust Name</div>
          <div class="contact-value"><?= esc($selected['trust_name']) ?></div>
        </div>
        <?php if (! empty($selected['trust_address'])): ?>
          <div class="contact-field">
            <div class="contact-label">Address</div>
            <div class="contact-value"><?= nl2br(esc($selected['trust_address'])) ?></div>
          </div>
        <?php endif; ?>
        <?php if (! empty($selected['trust_pincode'])): ?>
          <div class="contact-field">
            <div class="contact-label">PIN Code</div>
            <div class="contact-value num"><?= esc($selected['trust_pincode']) ?></div>
          </div>
        <?php endif; ?>
        <?php if (! empty($selected['trust_pan'])): ?>
          <div class="contact-field">
            <div class="contact-label">PAN</div>
            <div class="contact-value num"><?= esc($selected['trust_pan']) ?></div>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="row-actions" style="margin-top:18px">
    <a class="btn btn-ghost btn-sm" href="<?= site_url('login') ?>">Back to Sign in</a>
  </div>
</div>

<?= $this->endSection() ?>
