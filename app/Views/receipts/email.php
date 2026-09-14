<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;border:1px solid #E7E0D0;border-radius:12px;overflow:hidden">
  <div style="background:#0E2A52;color:#fff;padding:18px">
    <div style="font-weight:bold;font-size:16px"><?= esc($trustName) ?></div>
    <div style="font-size:11px;color:#BCD0EC"><?= esc($t['receiptTitle']) ?> &middot; Hithachintak Abhiyan</div>
    <div style="font-weight:bold;font-size:12px;margin-top:8px"><?= esc($enrolment->receipt_no) ?></div>
  </div>
  <div style="padding:18px">
    <p style="color:#1E8A5C;font-weight:bold;font-size:13px"><?= esc($t['paymentConfirmed']) ?></p>
    <table style="width:100%;font-size:13px;border-collapse:collapse">
      <tr><td style="padding:6px 0;color:#8B93A3"><?= esc($t['programme']) ?></td><td style="text-align:right;font-weight:bold"><?= esc($programmeName ?? '') ?></td></tr>
      <tr><td style="padding:6px 0;color:#8B93A3"><?= esc($t['date']) ?></td><td style="text-align:right;font-weight:bold"><?= esc(date('d M Y', strtotime($enrolment->paid_at ?? $enrolment->created_at))) ?></td></tr>
      <tr><td style="padding:6px 0;color:#8B93A3"><?= esc($t['amountShort']) ?></td><td style="text-align:right;font-weight:bold"><?= fmt_rupees($enrolment->amount) ?></td></tr>
    </table>
    <p style="font-size:10.5px;font-weight:bold;color:#8B93A3;text-transform:uppercase;margin:16px 0 6px"><?= esc($t['familyMembers']) ?></p>
    <table style="width:100%;font-size:12px;border-collapse:collapse">
      <tr>
        <th style="text-align:left;padding:5px 4px;border-bottom:1px solid #E7E0D0;color:#8B93A3;font-size:10px;text-transform:uppercase"><?= esc($t['serialNo']) ?></th>
        <th style="text-align:left;padding:5px 4px;border-bottom:1px solid #E7E0D0;color:#8B93A3;font-size:10px;text-transform:uppercase"><?= esc($t['nameLabel']) ?></th>
        <th style="text-align:left;padding:5px 4px;border-bottom:1px solid #E7E0D0;color:#8B93A3;font-size:10px;text-transform:uppercase"><?= esc($t['agePlaceholder']) ?></th>
        <th style="text-align:left;padding:5px 4px;border-bottom:1px solid #E7E0D0;color:#8B93A3;font-size:10px;text-transform:uppercase"><?= esc($t['professionLabel']) ?></th>
      </tr>
      <?php foreach ($householdRows as $i => $row): ?>
        <tr>
          <td style="padding:6px 4px;border-bottom:1px solid #F2EEE4"><?= $i + 1 ?></td>
          <td style="padding:6px 4px;border-bottom:1px solid #F2EEE4"><?= esc($row['name']) ?><?php if ($row['isHead']): ?> <span style="font-size:9px;color:#8B93A3"><?= esc($t['headOfFamily']) ?></span><?php endif; ?></td>
          <td style="padding:6px 4px;border-bottom:1px solid #F2EEE4"><?= esc($row['age_or_dob'] ?: '—') ?></td>
          <td style="padding:6px 4px;border-bottom:1px solid #F2EEE4"><?= esc($row['profession'] ?: '—') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <p style="font-size:10.5px;color:#8B93A3;margin-top:14px"><?= esc($t['thankYouNote']) ?> <?= esc($t['noSignatureNote']) ?></p>
  </div>
</div>
