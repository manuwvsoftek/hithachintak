<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>

<div class="receipt-card">
  <div class="receipt-body" style="padding-top:18px">
    <h2 style="margin:0 0 6px">Find My Receipt</h2>
    <p class="panel-note" style="margin:0 0 16px">Enter the head of family's registered mobile number to download the receipt.</p>

    <?= $this->include('partials/flash') ?>

    <form action="<?= site_url('h/lookup') ?>" method="post">
      <?= csrf_field() ?>
      <div class="field">
        <label class="f-label" for="phone">Registered mobile number</label>
        <input class="f-input" type="text" id="phone" name="phone" inputmode="numeric" maxlength="10"
               placeholder="10-digit mobile" value="<?= esc(old('phone')) ?>" required autofocus>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Find receipt</button>
    </form>
  </div>
</div>

<?= $this->endSection() ?>
