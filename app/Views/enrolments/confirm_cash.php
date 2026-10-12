<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div><div class="page-title">Confirm Cash Received</div></div>
  <a class="btn btn-ghost btn-sm" href="<?= $backUrl ?>">Back</a>
</div>

<div class="panel" style="max-width:420px">
  <p class="panel-note" style="margin-bottom:20px;font-size:12.5px">
    Has the cash actually been collected from <strong><?= esc($memberName !== '' ? $memberName : 'this member') ?></strong>?
  </p>

  <form action="<?= $actionUrl ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="cash_received" value="yes">
    <button type="submit" class="btn btn-primary btn-block">Yes, cash received</button>
  </form>

  <form action="<?= $actionUrl ?>" method="post" style="margin-top:10px">
    <?= csrf_field() ?>
    <input type="hidden" name="cash_received" value="no">
    <button type="submit" class="btn btn-ghost btn-block">Not yet<?= $isDraft ? ' — save as draft' : '' ?></button>
  </form>
</div>

<?= $this->endSection() ?>
