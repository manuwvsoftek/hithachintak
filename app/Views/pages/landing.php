<?= $this->extend('layouts/landing') ?>
<?= $this->section('content') ?>

<section class="landing-hero">
  <div class="landing-hero-text">
    <h1>Hithachintak Abhiyan</h1>
    <p class="landing-hero-sub">Vishwa Hindu Parishad's nationwide campaign for Hindu welfare — supporting Hithachintak, Dharma Raksha Nidhi and our Magazine Subscription programmes across every Prant in India.</p>
    <div class="landing-hero-actions">
      <a class="btn btn-primary" href="<?= site_url('login') ?>">Donate Now</a>
      <a class="btn btn-secondary" href="<?= site_url('login') ?>">Login</a>
    </div>
  </div>
  <div class="landing-hero-art">
    <img src="<?= base_url('assets/images/logo-placeholder.jpeg') ?>" alt="">
  </div>
</section>

<section class="landing-features">
  <div class="landing-feature">
    <div class="landing-feature-icon">1</div>
    <h3>Verified enrolment</h3>
    <p>Every member is verified by an instant OTP, so each enrolment is genuine and traceable back to the donor.</p>
  </div>
  <div class="landing-feature">
    <div class="landing-feature-icon">2</div>
    <h3>Flexible collection</h3>
    <p>Contribute by UPI/QR online, or hand cash to your local Karyakarta — every rupee is reconciled against a receipt.</p>
  </div>
  <div class="landing-feature">
    <div class="landing-feature-icon">3</div>
    <h3>Instant, multilingual receipts</h3>
    <p>Receipts are issued immediately by WhatsApp, SMS and email, in your choice of 13 Indian languages.</p>
  </div>
</section>

<section class="landing-programmes">
  <h2>Our Programmes</h2>
  <div class="landing-programme-grid">
    <div class="landing-programme-card">
      <h3>Hithachintak</h3>
      <p>The founding membership programme of Vishwa Hindu Parishad — contribution that keeps our Prants, Jilas and Karyakartas active in every corner of the country.</p>
    </div>
    <div class="landing-programme-card">
      <h3>Dharma Raksha Nidhi</h3>
      <p>A dedicated fund supporting the protection and preservation of Hindu Dharma, temples and traditions nationwide.</p>
    </div>
    <div class="landing-programme-card">
      <h3>Magazine Subscription</h3>
      <p>Subscribe to VHP's periodicals and stay connected with the organisation's work, events and perspective throughout the year.</p>
    </div>
  </div>
</section>

<section class="landing-cta-band">
  <h2>Join the Abhiyan</h2>
  <p>Every contribution, large or small, strengthens this nationwide effort.</p>
  <a class="btn btn-primary" href="<?= site_url('login') ?>">Donate Now</a>
</section>

<?= $this->endSection() ?>
