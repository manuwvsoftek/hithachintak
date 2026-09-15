<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 28px 32px; }
  body { font-family: "<?= esc($fontFamily) ?>"; color: #182338; font-size: 12px; }
  .head { background: #0E2A52; color: #ffffff; padding: 16px 18px; border-radius: 10px 10px 0 0; }
  .head-table { width: 100%; }
  .mark { width: 34px; height: 34px; background: #ffffff; border-radius: 8px; text-align: center;
    vertical-align: middle; font-weight: bold; font-size: 14px; color: #0E2A52; }
  .trust-name { font-weight: bold; font-size: 15px; color: #ffffff; }
  .trust-sub { font-size: 10px; color: #BCD0EC; margin-top: 2px; }
  .receipt-no { font-weight: bold; font-size: 12px; text-align: right; color: #ffffff; }
  .body { border: 1px solid #E7E0D0; border-top: none; border-radius: 0 0 10px 10px; padding: 18px; }
  .confirmed { color: #1E8A5C; font-weight: bold; font-size: 12px; margin-bottom: 12px; }
  table.rows { width: 100%; border-collapse: collapse; }
  table.rows td { padding: 7px 0; border-bottom: 1px dashed #EFEAE0; font-size: 11.5px; }
  table.rows td.label { color: #8B93A3; width: 40%; }
  table.rows td.value { text-align: right; font-weight: bold; color: #182338; }
  .amount-strip { background: #FAF7F0; border-radius: 8px; padding: 10px 14px; margin-top: 12px; }
  .amount-strip table { width: 100%; }
  .amount-label { font-size: 10px; font-weight: bold; color: #5B6577; text-transform: uppercase; }
  .amount-value { font-size: 16px; font-weight: bold; color: #1C4F8F; text-align: right; }
  .pan-note { font-size: 9px; color: #8B93A3; margin-top: 10px; }
  .family-title { font-size: 9.5px; font-weight: bold; color: #5B6577; text-transform: uppercase; margin-top: 14px; margin-bottom: 6px; }
  table.family { width: 100%; border-collapse: collapse; font-size: 10.5px; }
  table.family th { text-align: left; font-weight: bold; color: #8B93A3; font-size: 9px; text-transform: uppercase; padding: 5px 6px; border-bottom: 1px solid #E7E0D0; }
  table.family th.num, table.family td.num { text-align: center; width: 26px; }
  table.family td { padding: 6px 6px; border-bottom: 1px dashed #EFEAE0; }
  .head-tag { font-size: 8px; color: #8B93A3; font-weight: bold; }
  .footer-note { font-size: 9px; color: #8B93A3; margin-top: 12px; text-align: center; line-height: 1.5; }
  .footer { font-size: 9px; color: #8B93A3; margin-top: 18px; text-align: center; }
</style>
</head>
<body>
<div class="head">
  <table class="head-table">
    <tr>
      <td style="width:34px"><div class="mark">H</div></td>
      <td style="padding-left:10px">
        <div class="trust-name"><?= esc($trustName) ?></div>
        <div class="trust-sub"><?= esc($t['receiptTitle']) ?> &middot; Hithachintak Abhiyan</div>
      </td>
      <td class="receipt-no"><?= esc($enrolment->receipt_no) ?></td>
    </tr>
  </table>
</div>

<div class="body" style="margin-top:-1px">
  <div class="confirmed"><?= esc($t['paymentConfirmed']) ?></div>
  <table class="rows">
    <?php if (! empty($member['pan'])): ?>
      <tr><td class="label"><?= esc($t['donorPan']) ?></td><td class="value"><?= esc($member['pan']) ?></td></tr>
    <?php endif; ?>
    <tr><td class="label"><?= esc($t['programme']) ?></td><td class="value"><?= esc($programme['name']) ?></td></tr>
    <tr><td class="label"><?= esc($t['prant']) ?></td><td class="value"><?= esc($prant['name']) ?></td></tr>
    <tr><td class="label"><?= esc($t['trust']) ?></td><td class="value"><?= esc($trustName) ?></td></tr>
    <tr><td class="label"><?= esc($t['pan']) ?></td><td class="value"><?= esc($trustPan) ?></td></tr>
    <tr><td class="label"><?= esc($t['date']) ?></td><td class="value"><?= esc(date('d M Y', strtotime($enrolment->paid_at ?? $enrolment->created_at))) ?></td></tr>
    <tr><td class="label"><?= esc($t['mode']) ?></td><td class="value"><?= esc(ucfirst($enrolment->payment_mode ?? '—')) ?></td></tr>
    <tr><td class="label"><?= esc($t['collectedBy']) ?></td><td class="value"><?= esc($collectedBy) ?></td></tr>
    <tr><td class="label"><?= esc($t['language']) ?></td><td class="value"><?= esc($t['native']) ?></td></tr>
  </table>
  <div class="family-title"><?= esc($t['familyMembers']) ?></div>
  <table class="family">
    <thead><tr><th class="num"><?= esc($t['serialNo']) ?></th><th><?= esc($t['nameLabel']) ?></th><th><?= esc($t['agePlaceholder']) ?></th><th><?= esc($t['professionLabel']) ?></th></tr></thead>
    <tbody>
    <?php foreach ($householdRows as $i => $row): ?>
      <tr>
        <td class="num"><?= $i + 1 ?></td>
        <td><?= esc($row['name']) ?><?php if ($row['isHead']): ?> <span class="head-tag">(<?= esc($t['headOfFamily']) ?>)</span><?php endif; ?></td>
        <td><?= esc($row['age_or_dob'] ?: '—') ?></td>
        <td><?= esc($row['profession'] ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="amount-strip">
    <table>
      <tr>
        <td class="amount-label"><?= esc($t['amountShort']) ?></td>
        <td class="amount-value"><?= fmt_rupees($enrolment->amount) ?></td>
      </tr>
    </table>
  </div>
  <div class="pan-note"><?= esc($t['panNote']) ?></div>
  <div class="footer-note"><?= esc($t['thankYouNote']) ?><br><?= esc($t['noSignatureNote']) ?></div>
</div>

<div class="footer">System-generated receipt &middot; Hithachintak Abhiyan &middot; Vishwa Hindu Parishad</div>
</body>
</html>
