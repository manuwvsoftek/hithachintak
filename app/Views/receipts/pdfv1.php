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

  .subtitle { text-align: center; font-weight: bold; font-size: 12.5px; color: #E8710A; margin: 10px 0 10px; }

  table.info-table { width: 100%; border-collapse: collapse; border: 1px solid #999; margin-bottom: 10px; }
  table.info-table td { border: 1px solid #999; padding: 6px 10px; font-size: 11px; }
  table.info-table td.label { font-weight: bold; color: #1A1A1A; width: 42%; background: #FBF7EC; }
  table.info-table td.value { font-weight: bold; color: #0E2A52; }
  table.info-table td.value.accent { color: #A6192E; }

  .confirm-banner { text-align: center; font-weight: bold; font-size: 12.5px; color: #A6192E;
    background: #FFF4D6; border: 1px solid #D9A520; border-radius: 5px; padding: 8px; margin-bottom: 10px; }

  .family-title { font-weight: bold; font-size: 11px; color: #1A1A1A; margin-bottom: 5px; }
  table.family { width: 100%; border-collapse: collapse; border: 1px solid #999; font-size: 10.5px; margin-bottom: 10px; }
  table.family th { text-align: left; font-weight: bold; background: #F2F2F2; border: 1px solid #999; padding: 6px 7px; font-size: 10px; }
  table.family th.num, table.family td.num { text-align: center; width: 28px; }
  table.family td { border: 1px solid #999; padding: 6px 7px; }
  .head-tag { font-size: 8.5px; color: #5B6577; font-weight: normal; }

  .notes { font-size: 9.5px; color: #1A1A1A; line-height: 1.6; }
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
      <td style="width:34px"><div class="mark"><img src="<?= base_url('assets/images/logo-placeholder.jpeg') ?>" alt="" height="38"></div></td>
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

  <div class="subtitle"><?= esc($t['receiptTitle']) ?> &middot; Hithachintak Abhiyan</div>

  <table class="info-table">
    <tr>
      <td class="label"><?= esc($t['receipt']) ?></td>
      <td class="value accent"><?= esc($enrolment->receipt_no) ?></td>
    </tr>
    <tr>
      <td class="label"><?= esc($t['date']) ?></td>
      <td class="value"><?= esc(date('d M Y', strtotime($enrolment->paid_at ?? $enrolment->created_at))) ?></td>
    </tr>
    <tr>
      <td class="label"><?= esc($t['programme']) ?></td>
      <td class="value"><?= esc($programme['name']) ?></td>
    </tr>
    <tr>
      <td class="label"><?= esc($t['prant']) ?></td>
      <td class="value"><?= esc($prant['name']) ?></td>
    </tr>
    <tr>
      <td class="label"><?= esc($t['mode']) ?></td>
      <td class="value"><?= esc(ucfirst($enrolment->payment_mode ?? '—')) ?></td>
    </tr>
    <tr>
      <td class="label"><?= esc($t['collectedBy']) ?></td>
      <td class="value"><?= esc($collectedBy) ?></td>
    </tr>
    <?php if (! empty($member['pan'])): ?>
    <tr>
      <td class="label"><?= esc($t['donorPan']) ?></td>
      <td class="value"><?= esc($member['pan']) ?></td>
    </tr>
    <?php endif; ?>
    <tr>
      <td class="label"><?= esc($t['pan']) ?> (<?= esc($t['trust']) ?>)</td>
      <td class="value"><?= esc($trustPan) ?></td>
    </tr>
    <tr>
      <td class="label"><?= esc($t['amountShort']) ?></td>
      <td class="value accent"><?= fmt_rupees($enrolment->amount) ?></td>
    </tr>
  </table>

  <div class="confirm-banner"><?= esc($t['paymentConfirmed']) ?></div>

  <div class="family-title"><?= esc($t['familyMembers']) ?></div>
  <table class="family">
    <thead><tr><th class="num"><?= esc($t['serialNo']) ?></th><th><?= esc($t['nameLabel']) ?></th><th><?= esc($t['agePlaceholder']) ?></th><th><?= esc($t['professionLabel']) ?></th></tr></thead>
    <tbody>
    <?php foreach ($householdRows as $i => $row): ?>
      <tr>
        <td class="num"><?= $i + 1 ?></td>
        <td><?= esc($row['name']) ?><?php if ($row['isHead']): ?> <span class="head-tag">(<?php //esc($t['headOfFamily']) ?>)</span><?php endif; ?></td>
        <td><?= esc($row['age_or_dob'] ?: '—') ?></td>
        <td><?= esc($row['profession'] ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="notes">
    <div class="note-line"><b><?= esc($t['thankYouNote']) ?></b></div>
    <div class="note-line"><?= esc($t['noSignatureNote']) ?></div>
    <div class="note-line"><?php // esc($t['panNote']) ?></div>
  </div>

</div>
<div class="footer">System-generated receipt &middot; Hithachintak Abhiyan &middot; Vishwa Hindu Parishad</div>
</body>
</html>
