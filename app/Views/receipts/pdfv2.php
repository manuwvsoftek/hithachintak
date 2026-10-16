<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 22px 26px; }
  body { font-family: "<?= esc($fontFamily) ?>"; color: #1A1A1A; font-size: 11.5px; }
  .card { border: 3px solid #C8960C; border-radius: 8px; padding: 14px 18px 16px; }

  .letterhead-table { width: 100%; }
  .logo-cell { width: 54px; vertical-align: middle; }
  .mark { width: 46px; height: 46px; border: 2px solid #A6192E; border-radius: 50%; background: #FFF8EA;
    text-align: center; vertical-align: middle; font-weight: bold; font-size: 20px; color: #A6192E; line-height: 46px; }
  .org-cell { vertical-align: middle; padding-left: 12px; }
  .org-name { font-weight: bold; font-size: 16px; color: #A6192E; line-height: 1.3; }
  .org-name-native { font-weight: bold; font-size: 15px; color: #A6192E; line-height: 1.3; }
  .org-address { font-size: 9.5px; color: #444; margin-top: 3px; line-height: 1.4; }

  .subtitle { text-align: center; font-weight: bold; font-size: 12.5px; color: #E8710A; margin: 10px 0 12px; }

  .fields { margin-bottom: 6px; }
  .fields.group { margin-bottom: 10px; }
  .field-row { font-size: 11.5px; padding: 2px 0; }
  .field-row .flabel { font-weight: bold; color: #1A1A1A; }
  .field-row .fvalue { color: #0E2A52; font-weight: bold; }
  .field-row .fvalue.accent { color: #A6192E; }

  .family-title { font-weight: bold; font-size: 11px; color: #1A1A1A; margin-bottom: 5px; margin-top: 4px; }
  table.family { width: 100%; border-collapse: collapse; border: 1px solid #999; font-size: 10.5px; margin-bottom: 10px; }
  table.family th { text-align: left; font-weight: bold; background: #F2F2F2; border: 1px solid #999; padding: 6px 7px; font-size: 10px; }
  table.family th.num, table.family td.num { text-align: center; width: 28px; }
  table.family td { border: 1px solid #999; padding: 6px 7px; }
  .head-tag { font-size: 8.5px; color: #5B6577; font-weight: normal; }

  .notes { font-size: 9.5px; color: #1A1A1A; line-height: 1.6; margin-top: 8px; }
  .notes .note-line { padding-left: 12px; text-indent: -12px; margin-bottom: 2px; }
  .notes .note-line::before { content: "\2022  "; font-weight: bold; }
  .notes b { font-weight: bold; }

  .footer { font-size: 8.5px; color: #8B93A3; margin-top: 12px; text-align: center; }
</style>
</head>
<body>
<div class="card">

  <table class="letterhead-table">
    <tr>
      <td class="logo-cell"><div class="mark"><img src="<?= base_url('assets/images/logo-placeholder.jpeg') ?>" alt="" height="38"></div></td>
      <td class="org-cell">
        <div class="org-name"><?= esc(\App\Libraries\Enrolment\I18n::trustName('en')) ?></div>
        <?php if ($enrolment->language !== 'en'): ?>
          <div class="org-name-native"><?= esc($trustName) ?></div>
        <?php endif; ?>
        <?php if (! empty($prant['trust_address']) || ! empty($prant['trust_pincode'])): ?>
          <div class="org-address">
            <?= esc(trim($prant['trust_address'] ?? '')) ?><?php if (! empty($prant['trust_pincode'])): ?> - <?= esc($prant['trust_pincode']) ?><?php endif; ?>
          </div>
        <?php endif; ?>
      </td>
    </tr>
  </table>

  <div class="subtitle"><?= esc($t['receiptTitle']) ?> &middot; <?= esc($programme['name']) ?></div>

  <div class="fields group">
    <div class="field-row"><span class="flabel"><?= esc($t['prant']) ?>:</span> <span class="fvalue"><?= esc($prant['name']) ?></span></div>
    <div class="field-row"><span class="flabel"><?= esc($t['pan']) ?>:</span> <span class="fvalue"><?= esc($trustPan) ?></span></div>
  </div>

  <div class="fields">
    <div class="field-row"><span class="flabel"><?= esc($t['receipt']) ?> no:</span> <span class="fvalue accent"><?= esc($enrolment->receipt_no) ?></span></div>
    <div class="field-row"><span class="flabel"><?= esc($t['date']) ?>:</span> <span class="fvalue"><?= esc(date('d M Y', strtotime($enrolment->paid_at ?? $enrolment->created_at))) ?></span></div>
    <div class="field-row"><span class="flabel"><?= esc($t['collectedBy']) ?>:</span> <span class="fvalue"><?= esc($collectedBy) ?></span></div>
    <div class="field-row"><span class="flabel"><?= esc($t['amountShort']) ?>:</span> <span class="fvalue accent"><?= fmt_rupees($enrolment->amount) ?></span></div>
    <div class="field-row"><span class="flabel"><?= esc($t['mode']) ?>:</span> <span class="fvalue"><?= esc(strtoupper($enrolment->payment_mode ?? '—')) ?></span></div>
  </div>

  <?php if ($programme['code'] === 'hc'): ?>
  <div class="family-title">Hithachintak Details</div>
  <table class="family">
    <thead><tr><th class="num"><?= esc($t['serialNo']) ?></th><th><?= esc($t['nameLabel']) ?></th><th><?= esc($t['agePlaceholder']) ?></th><th><?= esc($t['professionLabel']) ?></th></tr></thead>
    <tbody>
    <?php foreach ($householdRows as $i => $row): ?>
      <tr>
        <td class="num"><?= $i + 1 ?></td>
        <td><?= esc($row['name']) ?><?php if ($row['isHead']): ?> <span class="head-tag">(<?php  //esc($t['headOfFamily']) ?>)</span><?php endif; ?></td>
        <td><?= esc($row['age_or_dob'] ?: '—') ?></td>
        <td><?= esc($row['profession'] ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <div class="notes">
    <div class="note-line"><b><?= esc($t['thankYouNote']) ?></b></div>
    <div class="note-line"><?= esc($t['noSignatureNote']) ?></div>
    <div class="note-line"><?php //esc($t['panNote']) ?></div>
  </div>

</div>
<div class="footer">System-generated receipt &middot; Hithachintak Abhiyan &middot; Vishwa Hindu Parishad</div>
</body>
</html>
