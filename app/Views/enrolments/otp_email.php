<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;border:1px solid #E7E0D0;border-radius:12px;overflow:hidden">
  <div style="background:#0E2A52;color:#fff;padding:18px">
    <div style="font-weight:bold;font-size:16px"><?= esc($trustName) ?></div>
    <div style="font-size:11px;color:#BCD0EC"><?= esc($t['verifyMemberTitle']) ?> &middot; Hithachintak Abhiyan</div>
  </div>
  <div style="padding:18px">
    <p style="font-size:13px;color:#1E2A3C"><?= esc($t['otpEmailIntro']) ?></p>
    <div style="text-align:center;margin:20px 0">
      <span style="display:inline-block;font-size:28px;font-weight:bold;letter-spacing:6px;color:#0E2A52;background:#F2EEE4;border-radius:8px;padding:12px 20px"><?= esc($code) ?></span>
    </div>
    <p style="font-size:11.5px;color:#8B93A3"><?= esc($t['otpEmailValidity']) ?></p>
  </div>
</div>
