<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div><div class="page-title"><?= esc($t['receipt']) ?></div></div>
  <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/enrolments') ?>"><?= esc($t['done']) ?></a>
</div>

<div class="receipt-card">
  <div class="receipt-head">
    <div class="receipt-head-logo"><img src="<?= base_url('assets/images/logo-placeholder.svg') ?>" alt=""></div>
    <div class="receipt-head-titles">
      <div class="receipt-head-trust"><?= esc($trustName) ?></div>
      <div class="receipt-head-sub"><?= esc($t['receiptTitle']) ?> &middot; Hithachintak Abhiyan</div>
    </div>
    <div class="receipt-head-status">
      <div class="receipt-no"><?= esc($enrolment->receipt_no) ?></div>
    </div>
  </div>
  <div class="receipt-body">
    <div class="receipt-confirmed"><?= esc($t['paymentConfirmed']) ?></div>
    <div class="receipt-rows">
      <?php if (! empty($member['pan'])): ?>
        <div class="r"><span><?= esc($t['donorPan']) ?></span><span class="num"><?= esc($member['pan']) ?></span></div>
      <?php endif; ?>
      <div class="r"><span><?= esc($t['programme']) ?></span><span><?= esc($programme['name']) ?></span></div>
      <div class="r"><span><?= esc($t['prant']) ?></span><span><?= esc($prant['name']) ?></span></div>
      <div class="r"><span><?= esc($t['trust']) ?></span><span><?= esc($trustName) ?></span></div>
      <div class="r"><span><?= esc($t['pan']) ?></span><span class="num"><?= esc($trustPan) ?></span></div>
      <div class="r"><span><?= esc($t['date']) ?></span><span><?= esc(date('d M Y', strtotime($enrolment->paid_at ?? $enrolment->created_at))) ?></span></div>
      <div class="r"><span><?= esc($t['mode']) ?></span><span><?= esc(ucfirst($enrolment->payment_mode ?? '—')) ?></span></div>
      <div class="r"><span><?= esc($t['collectedBy']) ?></span><span><?= esc($collectedBy) ?></span></div>
      <div class="r"><span><?= esc($t['language']) ?></span><span><?= esc($t['native']) ?></span></div>
    </div>
    <div class="receipt-family">
      <div class="receipt-family-title"><?= esc($t['familyMembers']) ?></div>
      <table class="receipt-family-table">
        <thead><tr><th class="num"><?= esc($t['serialNo']) ?></th><th><?= esc($t['nameLabel']) ?></th><th><?= esc($t['agePlaceholder']) ?></th><th><?= esc($t['professionLabel']) ?></th></tr></thead>
        <tbody>
        <?php foreach ($householdRows as $i => $row): ?>
          <tr>
            <td class="num"><?= $i + 1 ?></td>
            <td><?= esc($row['name']) ?><?php if ($row['isHead']): ?><span class="head-tag">(<?= esc($t['headOfFamily']) ?>)</span><?php endif; ?></td>
            <td><?= esc($row['age_or_dob'] ?: '—') ?></td>
            <td><?= esc($row['profession'] ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="receipt-amount-strip">
      <span class="lbl" style="font-size:11.5px;font-weight:700;color:var(--ink-soft);text-transform:uppercase"><?= esc($t['amountShort']) ?></span>
      <span class="val"><?= fmt_rupees($enrolment->amount) ?></span>
    </div>
    <div class="receipt-pan-note"><?= esc($t['panNote']) ?></div>
    <div class="receipt-footer-note"><?= esc($t['thankYouNote']) ?><br><?= esc($t['noSignatureNote']) ?></div>
  </div>
  <div class="receipt-actions" style="padding:0 18px 18px">
    <a class="btn btn-primary btn-block" href="<?= site_url('admin/enrolments/' . $enrolment->id . '/receipt.pdf') ?>"><?= esc($t['download']) ?></a>
    <div class="share-row" style="margin-top:10px">
      <form action="<?= site_url('admin/enrolments/' . $enrolment->id . '/receipt/send/whatsapp') ?>" method="post" style="flex:1">
        <?= csrf_field() ?>
        <button type="submit" class="share-btn" style="width:100%">WhatsApp</button>
      </form>
      <form action="<?= site_url('admin/enrolments/' . $enrolment->id . '/receipt/send/email') ?>" method="post" style="flex:1">
        <?= csrf_field() ?>
        <button type="submit" class="share-btn" style="width:100%">Email</button>
      </form>
      <form action="<?= site_url('admin/enrolments/' . $enrolment->id . '/receipt/send/sms') ?>" method="post" style="flex:1">
        <?= csrf_field() ?>
        <button type="submit" class="share-btn" style="width:100%">SMS</button>
      </form>
    </div>
  </div>
</div>

<?= $this->endSection() ?>
