<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>

<div class="panel" style="max-width:560px">
  <div class="panel-title" style="margin-bottom:10px">About this platform</div>
  <p class="panel-note">VHP Hithachintak Abhiyan is the enrolment, collection and receipting platform used by Vishwa Hindu Parishad's Prants across India for the Hithachintak, Dharma Raksha Nidhi and Magazine Subscription programmes.</p>
  <p class="panel-note" style="margin-top:10px">Karyakartas and Admins use it to enrol members in the field with instant OTP verification, collect payments by UPI/QR or cash, and issue receipts by WhatsApp, SMS and email in 13 languages.</p>
  <div class="row-actions" style="margin-top:18px">
    <a class="btn btn-secondary btn-sm" href="<?= site_url('contact-us') ?>">Contact Us</a>
    <a class="btn btn-ghost btn-sm" href="<?= site_url('login') ?>">Back to Sign in</a>
  </div>
</div>

<?= $this->endSection() ?>
