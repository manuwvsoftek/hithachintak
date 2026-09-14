<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>

<div class="row " style="overflow-x:hidden;">

<div class="col-md-2">  
</div>

<div class="col-md-8 page">
<h3 style="">Services</h3>
<hr>

<div class="table-wrap">
    <table>
      <thead>
        <tr><th>Service</th><th>Details</th></tr>
      </thead>
      <tbody>
        <tr>
          <td style="font-weight:700">Hithachintak</td>
            <td>₹20 minimum per person</td>
        </tr>
        <tr>
          <td style="font-weight:700">Dharma Raksha Nidhi</td>
            <td>Open — amount entered at enrolment</td>
        </tr>
         <tr>
          <td style="font-weight:700">Magazine Subscription</td>
            <td>Open — amount entered at enrolment</td>
        </tr>
        <tr>
          <td style="font-weight:700">Autopay Monthly Donation to VHP</td>
            <td>₹100 minimum per person/month (Autopay)</td>
        </tr>
      </tbody>
    </table>
    <div class="row-actions" style="margin-top:18px">
    <a class="btn btn-ghost btn-sm" href="<?= site_url('login') ?>">Back to Sign in</a>
  </div>
  </div>



</div><br>


<?= $this->endSection() ?>