<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div>
    <div class="page-title"><?= esc($forceSelf ? 'Complete Your Own Hithachintak Enrolment' : $t['enrolTitle']) ?></div>
    <div class="page-desc"><?= esc($t['pageDesc']) ?></div>
  </div>
  <div class="header-actions">
    <span class="panel-note" id="autosaveNote"></span>
    <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/enrolments') ?>"><?= esc($t['cancelWord']) ?></a>
  </div>
</div>

<?php if ($forceSelf): ?>
  <div class="flash flash-info">
    Before enrolling anyone else, complete your own Hithachintak registration — First name and Mobile number below are your own account's and can't be changed here.
  </div>
<?php endif; ?>

<div class="panel">
  <form action="<?= site_url('admin/enrolments') ?>" method="post" id="enrolForm" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="draft_id" id="draftIdInput" value="<?= esc($resumeDraftId ?? '') ?>">

    <div class="form-grid">
      <?php if ($lockedLocation): ?>
        <div class="field" style="grid-column:1/-1">
          <label class="f-label"><?= esc($t['yourAssignedLocation']) ?></label>
          <div class="f-input" style="background:var(--surface-alt);color:var(--ink-soft);cursor:not-allowed">
            <?= esc(trim(
              $lockedLocation['prant']['name']
              . ($lockedLocation['jila'] ? ' · ' . $lockedLocation['jila']['name'] : '')
              . ($lockedLocation['prakhand'] ? ' · ' . $lockedLocation['prakhand']['name'] : '')
            )) ?>
          </div>
          <div style="font-size:11px;color:var(--ink-faint);margin-top:3px"><?= esc($t['yourAssignedLocation']) ?> — <?= esc($t['contactAdminToChange']) ?></div>
          <input type="hidden" name="prant_id" id="lockedPrantId" value="<?= $lockedLocation['prant']['id'] ?>">
          <?php if ($lockedLocation['jila']): ?><input type="hidden" name="jila_id" value="<?= $lockedLocation['jila']['id'] ?>"><?php endif; ?>
          <?php if ($lockedLocation['prakhand']): ?><input type="hidden" name="prakhand_id" value="<?= $lockedLocation['prakhand']['id'] ?>"><?php endif; ?>
        </div>
      <?php else: ?>
        <div class="field">
          <label class="f-label"><?= esc($t['prant']) ?></label>
          <select class="f-input" name="prant_id" id="prantSelect" required>
            <option value=""><?= esc($t['prant']) ?>…</option>
            <?php foreach ($prants as $p): ?>
              <option value="<?= $p['id'] ?>"><?= esc($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label class="f-label"><?= esc($t['jila']) ?></label>
          <select class="f-input" name="jila_id" id="jilaSelect"><option value="">—</option></select>
        </div>
        <div class="field" style="grid-column:1/-1">
          <label class="f-label"><?= esc($t['prakhand']) ?></label>
          <select class="f-input" name="prakhand_id" id="prakhandSelect"><option value="">—</option></select>
        </div>
      <?php endif; ?>
    </div>

    <?php
      // A quick-launch link (PWA shortcut, the Karyakarta home button) can
      // pass ?programme= to pre-pick a chip other than the first.
      $selectedProgrammeCode = $preselectProgramme && in_array($preselectProgramme, array_column($programmes, 'code'), true)
        ? $preselectProgramme
        : ($programmes[0]['code'] ?? null);
    ?>
    <div class="section-label" style="margin:16px 0 10px;font-size:11px;font-weight:800;color:var(--blue-700);text-transform:uppercase"><?= esc($t['programme']) ?></div>
    <div class="chip-row" id="programmeChips">
      <?php foreach ($programmes as $p): ?>
        <?php $isSelected = $p['code'] === $selectedProgrammeCode; ?>
        <label class="chip <?= $isSelected ? 'selected' : '' ?>" data-code="<?= esc($p['code']) ?>" data-recurring="<?= $p['is_recurring'] ? '1' : '0' ?>" data-min-amount="<?= esc($p['rate'] ?? '0') ?>">
          <input type="radio" name="programme_code" value="<?= esc($p['code']) ?>" style="display:none" <?= $isSelected ? 'checked' : '' ?>>
          <?= esc($p['name']) ?><?= $p['mode'] === 'min' ? ' (min ' . fmt_rupees($p['rate']) . ($p['is_recurring'] ? '/month' : '') . ')' : '' ?>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="section-label" style="margin:16px 0 10px;font-size:11px;font-weight:800;color:var(--blue-700);text-transform:uppercase"><?= esc($t['paymentMode']) ?></div>
    <div class="chip-row" id="paymentModeChips">
      <label class="chip selected" data-mode="online">
        <input type="radio" name="payment_mode" value="online" style="display:none" checked>
        <?= esc($t['online']) ?>
      </label>
      <label class="chip" id="cashModeChip" data-mode="cash">
        <input type="radio" name="payment_mode" value="cash" style="display:none">
        <?= esc($t['cash']) ?>
      </label>
    </div>
    <div style="font-size:11px;color:var(--ink-faint);margin-top:6px" id="paymentModeHelp"></div>

    <div class="section-label" style="margin:16px 0 10px;font-size:11px;font-weight:800;color:var(--blue-700);text-transform:uppercase"><?= esc($t['memberDetails']) ?></div>
    <div class="form-grid">
      <div class="field">
        <label class="f-label"><?= esc($t['firstName']) ?> <span class="required-mark">*</span></label>
        <input class="f-input" id="memberFirstName" name="member_first_name"
          value="<?= esc($forceSelf ? $selfFirstName : (old('member_first_name') ?? '')) ?>"
          placeholder="<?= esc($t['firstName']) ?>" required <?= $forceSelf ? 'readonly' : '' ?>>
        <?php if ($forceSelf): ?><div class="field-note">From your account</div><?php endif; ?>
      </div>
      <div class="field">
        <label class="f-label"><?= esc($t['lastName']) ?></label>
        <input class="f-input" id="memberLastName" name="member_last_name" value="<?= esc($forceSelf ? $selfLastName : (old('member_last_name') ?? '')) ?>" placeholder="<?= esc($t['lastName']) ?>">
      </div>
      <div class="field">
        <label class="f-label"><?= esc($t['ageOrDobLabel']) ?> <span class="required-mark">*</span></label>
        <input class="f-input" id="ageOrDob" name="age_or_dob" value="<?= esc(old('age_or_dob') ?? '') ?>" placeholder="<?= esc($t['agePlaceholder']) ?>" required>
      </div>
      <div class="field">
        <label class="f-label"><?= esc($t['professionLabel']) ?></label>
        <select class="f-input" id="professionSelect">
          <option value="">— <?= esc($t['professionLabel']) ?> —</option>
          <?php foreach ($professions as $p): ?>
            <option value="<?= esc($p) ?>"><?= esc($p) ?></option>
          <?php endforeach; ?>
        </select>
        <input class="f-input" id="professionOther" style="display:none;margin-top:6px" placeholder="Please specify">
        <input type="hidden" name="profession" id="professionValue" value="<?= esc(old('profession') ?? '') ?>">
      </div>
      <div class="field">
        <label class="f-label"><?= esc($t['phoneNumber']) ?> <span class="required-mark">*</span></label>
        <input class="f-input" id="memberPhone" name="member_phone"
          value="<?= esc($forceSelf ? $selfPhone : (old('member_phone') ?? '')) ?>"
          inputmode="numeric" maxlength="10" placeholder="<?= esc($t['phoneHelp']) ?>" required <?= $forceSelf ? 'readonly' : '' ?>>
        <div class="field-note" id="phoneNote"><?= $forceSelf ? 'From your account' : '' ?></div>
      </div>
      <div class="field">
        <label class="f-label"><?= esc($t['emailOptional']) ?></label>
        <input class="f-input" id="emailInput" type="email" name="email" value="<?= esc(old('email') ?? '') ?>" placeholder="<?= esc($t['emailHelp']) ?>">
      </div>
      <div class="field" id="panField" style="display:none">
        <label class="f-label">PAN <span class="required-mark">*</span></label>
        <input class="f-input" name="member_pan" id="memberPan" value="<?= esc(old('member_pan') ?? '') ?>" maxlength="10" placeholder="AAAAA9999A" style="text-transform:uppercase">
        <div style="font-size:11px;color:var(--ink-faint);margin-top:3px"><?= esc($t['panRequiredNote']) ?></div>
      </div>
      <div class="field" style="grid-column:1/-1">
        <label class="f-label"><?= esc($t['address'] ?? 'Address') ?> <span class="required-mark">*</span></label>
        <textarea class="f-input" id="addressInput" name="address" rows="2" placeholder="<?= esc($t['address'] ?? 'Address') ?>" required><?= esc(old('address') ?? '') ?></textarea>
      </div>
      <div class="field">
        <label class="f-label"><?= esc($t['pincode']) ?> <span class="required-mark">*</span></label>
        <input class="f-input" id="pincodeInput" name="pincode" value="<?= esc(old('pincode') ?? '') ?>" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="<?= esc($t['pincodeHelp']) ?>" required>
      </div>
    </div>

    <div class="section-label" style="margin:16px 0 10px;font-size:11px;font-weight:800;color:var(--blue-700);text-transform:uppercase"><?= esc($t['addFamily']) ?></div>
    <div style="font-size:11px;color:var(--ink-faint);margin-bottom:8px" id="familyMembersNote"></div>
    <div id="familyList"></div>
    <button type="button" class="btn btn-ghost btn-sm" id="addFamilyBtn" style="background:var(--blue-100);color:var(--blue-700)"><?= esc($t['addPerson']) ?></button>

    <div style="display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:12px;margin-top:20px;padding-top:16px;border-top:1px solid var(--border-soft)">
      <div>
        <div class="f-label" style="margin-bottom:2px" id="amountLabel"><?= esc($t['amount']) ?></div>
        <input class="f-input" type="number" name="amount" id="amountInput" min="0" step="1" style="width:130px;font-weight:800;font-size:16px" placeholder="0" required>
        <div style="font-size:11px;color:var(--ink-faint);margin-top:3px" id="amountHelp"></div>
      </div>
      <button type="submit" class="btn btn-primary" id="submitBtn" style="flex:1;min-width:160px"><?= esc($t['sendOtp']) ?></button>
    </div>
  </form>
</div>

<script <?= csp_script_nonce() ?>>
(function(){
  var T_AMOUNT = <?= json_encode($t['amount']) ?>;
  var T_SEND_OTP = <?= json_encode($t['sendOtp']) ?>;
  var T_CONTINUE_TO_PAYMENT = <?= json_encode($t['continueToPayment']) ?>;
  var T_ONLINE_HELP = <?= json_encode($t['onlineHelp']) ?>;
  var T_CASH_HELP = <?= json_encode($t['cashHelp']) ?>;
  var T_UP_TO = <?= json_encode($t['upToWord']) ?>;
  var T_ADDITIONAL_FAMILY_SUFFIX = <?= json_encode($t['additionalFamilyMembersSuffix']) ?>;
  var T_MAXIMUM_REACHED = <?= json_encode($t['maximumReached']) ?>;
  var T_MONTHLY_AMOUNT_PAYABLE = <?= json_encode($t['monthlyAmountPayable']) ?>;
  var T_MINIMUM = <?= json_encode($t['minimumWord']) ?>;
  var T_MAXIMUM = <?= json_encode($t['maximumWord']) ?>;
  var T_PER_MONTH_AUTOPAY = <?= json_encode($t['perMonthAutopay']) ?>;
  var T_FIRST_NAME = <?= json_encode($t['firstName']) ?>;
  var T_LAST_NAME = <?= json_encode($t['lastName']) ?>;
  var T_AGE_PLACEHOLDER = <?= json_encode($t['agePlaceholder']) ?>;
  var T_PROFESSION = <?= json_encode($t['professionLabel']) ?>;
  var T_CONTACT_NUMBER = <?= json_encode($t['contactNumber']) ?>;
  var T_HEAD_OF_FAMILY = <?= json_encode($t['headOfFamily']) ?>;
  var T_ADDITIONAL_MEMBER = <?= json_encode($t['additionalMember']) ?>;
  var T_ADDITIONAL_MEMBERS = <?= json_encode($t['additionalMembers']) ?>;
  var PROFESSIONS = <?= json_encode($professions) ?>;
  var RESUME_DRAFT = <?= json_encode($resumeDraft) ?>;
  var RESUME_DRAFT_ID = <?= json_encode($resumeDraftId) ?>;
  var CSRF_NAME = <?= json_encode(csrf_token()) ?>;
  var FORCE_SELF = <?= json_encode($forceSelf) ?>;

  // Hithachintak: ₹{rate} per person (head of family + each additional
  // member), capped at 9 additional members. Autopay: bounded between the
  // programme's own minimum and this hard monthly ceiling.
  var MAX_FAMILY_MEMBERS = 9;
  var AUTOPAY_MAX_AMOUNT = 5000;

  var form = document.getElementById('enrolForm');
  var draftIdInput = document.getElementById('draftIdInput');
  var csrfInput = form.querySelector('input[name="' + CSRF_NAME + '"]');
  var autosaveNote = document.getElementById('autosaveNote');
  var panField = document.getElementById('panField');
  var memberPan = document.getElementById('memberPan');
  var memberFirstName = document.getElementById('memberFirstName');
  var memberLastName = document.getElementById('memberLastName');
  var ageOrDob = document.getElementById('ageOrDob');
  var memberPhone = document.getElementById('memberPhone');
  var phoneNote = document.getElementById('phoneNote');
  var emailInput = document.getElementById('emailInput');
  var addressInput = document.getElementById('addressInput');
  var pincodeInput = document.getElementById('pincodeInput');
  var professionSelect = document.getElementById('professionSelect');
  var professionOther = document.getElementById('professionOther');
  var professionValue = document.getElementById('professionValue');
  var amountInput = document.getElementById('amountInput');
  var amountLabel = document.getElementById('amountLabel');
  var amountHelp = document.getElementById('amountHelp');
  var submitBtn = document.getElementById('submitBtn');
  var familyList = document.getElementById('familyList');
  var familyMembersNote = document.getElementById('familyMembersNote');
  var addFamilyBtn = document.getElementById('addFamilyBtn');
  var paymentModeChips = document.querySelectorAll('#paymentModeChips .chip');
  var cashModeChip = document.getElementById('cashModeChip');
  var paymentModeHelp = document.getElementById('paymentModeHelp');

  // ---- Profession: preset dropdown, "Others" reveals a free-text field ----
  function wireProfession(selectEl, otherEl, hiddenEl){
    function sync(){
      if (selectEl.value === 'Others') {
        otherEl.style.display = '';
        hiddenEl.value = otherEl.value;
      } else {
        otherEl.style.display = 'none';
        hiddenEl.value = selectEl.value;
      }
    }
    selectEl.addEventListener('change', function(){ sync(); scheduleAutosave(); });
    otherEl.addEventListener('input', function(){ hiddenEl.value = otherEl.value; scheduleAutosave(); });
    sync();
  }

  function setProfessionValue(selectEl, otherEl, hiddenEl, value){
    var isPreset = value && PROFESSIONS.indexOf(value) !== -1 && value !== 'Others';
    if (value && !isPreset) {
      selectEl.value = 'Others';
      otherEl.value = value;
    } else {
      selectEl.value = value || '';
      otherEl.value = '';
    }
    otherEl.style.display = selectEl.value === 'Others' ? '' : 'none';
    hiddenEl.value = value || '';
  }

  wireProfession(professionSelect, professionOther, professionValue);
  if (professionValue.value) {
    setProfessionValue(professionSelect, professionOther, professionValue, professionValue.value);
  }

  function applyPaymentMode(mode){
    paymentModeHelp.textContent = mode === 'cash' ? T_CASH_HELP : T_ONLINE_HELP;
    submitBtn.textContent = mode === 'cash' ? T_SEND_OTP : T_CONTINUE_TO_PAYMENT;
  }

  function selectPaymentMode(mode){
    paymentModeChips.forEach(function(c){ c.classList.remove('selected'); });
    var chip = document.querySelector('#paymentModeChips .chip[data-mode="' + mode + '"]');
    chip.classList.add('selected');
    chip.querySelector('input').checked = true;
    applyPaymentMode(mode);
  }

  paymentModeChips.forEach(function(chip){
    chip.addEventListener('click', function(){
      if (chip.classList.contains('disabled')) { return; }
      selectPaymentMode(chip.dataset.mode);
      scheduleAutosave();
    });
  });

  applyPaymentMode('online');

  function familyCount(){
    return familyList.children.length;
  }

  function isHcSelected(){
    var chip = document.querySelector('#programmeChips .chip.selected');
    return !!chip && chip.dataset.code === 'hc';
  }

  // Hithachintak charges per person — the head of family plus each
  // additional member — at the programme's own rate, capped at
  // MAX_FAMILY_MEMBERS additional members. Recomputed on every add/remove
  // and kept read-only so it can't drift from the actual family list.
  function updateFamilyDependentUI(){
    var chip = document.querySelector('#programmeChips .chip.selected');
    var isHc = chip && chip.dataset.code === 'hc';
    var count = familyCount();

    addFamilyBtn.disabled = count >= MAX_FAMILY_MEMBERS;
    addFamilyBtn.style.display = count >= MAX_FAMILY_MEMBERS ? 'none' : '';
    familyMembersNote.textContent = T_UP_TO + ' ' + MAX_FAMILY_MEMBERS + ' ' + T_ADDITIONAL_FAMILY_SUFFIX
      + (count >= MAX_FAMILY_MEMBERS ? ' ' + T_MAXIMUM_REACHED : '');

    if (!isHc) { return; }

    var rate = parseFloat(chip.dataset.minAmount || '0');
    var total = rate * (1 + count);
    amountInput.value = total;
    amountHelp.textContent = '₹' + rate + ' × (1 ' + T_HEAD_OF_FAMILY + ' + ' + count + ' ' + (count === 1 ? T_ADDITIONAL_MEMBER : T_ADDITIONAL_MEMBERS) + ') = ₹' + total + '.';
  }

  function applyProgramme(chip){
    var recurring = chip.dataset.recurring === '1';
    var isHc = chip.dataset.code === 'hc';
    var minAmount = chip.dataset.minAmount || '0';

    panField.style.display = recurring ? '' : 'none';
    memberPan.required = recurring;
    if (!recurring) { memberPan.value = ''; }

    // A recurring Autopay mandate is always set up online — there's no
    // such thing as cash-collecting a monthly mandate.
    cashModeChip.classList.toggle('disabled', recurring);
    cashModeChip.style.opacity = recurring ? '0.4' : '';
    cashModeChip.style.pointerEvents = recurring ? 'none' : '';
    if (recurring) { selectPaymentMode('online'); }

    familyMembersNote.style.display = isHc ? '' : 'none';

    if (isHc) {
      amountInput.readOnly = true;
      amountInput.min = minAmount;
      amountInput.removeAttribute('max');
      amountLabel.textContent = T_AMOUNT;
      updateFamilyDependentUI();
    } else if (recurring) {
      amountInput.readOnly = false;
      amountInput.min = minAmount;
      amountInput.max = AUTOPAY_MAX_AMOUNT;
      amountInput.placeholder = minAmount;
      amountLabel.textContent = T_MONTHLY_AMOUNT_PAYABLE;
      amountHelp.textContent = T_MINIMUM + ' ₹' + minAmount + ', ' + T_MAXIMUM + ' ₹' + AUTOPAY_MAX_AMOUNT + ' ' + T_PER_MONTH_AUTOPAY;
    } else {
      amountInput.readOnly = false;
      amountInput.min = '0';
      amountInput.removeAttribute('max');
      amountInput.placeholder = '0';
      amountLabel.textContent = T_AMOUNT;
      amountHelp.textContent = '';
    }

    checkPhoneDuplicate();
  }

  amountInput.addEventListener('input', function(){
    var chip = document.querySelector('#programmeChips .chip.selected');
    if (chip && chip.dataset.recurring === '1' && amountInput.value !== '' && parseFloat(amountInput.value) > AUTOPAY_MAX_AMOUNT) {
      amountInput.value = AUTOPAY_MAX_AMOUNT;
    }
  });

  document.querySelectorAll('#programmeChips .chip').forEach(function(chip){
    chip.addEventListener('click', function(){
      document.querySelectorAll('#programmeChips .chip').forEach(function(c){ c.classList.remove('selected'); });
      chip.classList.add('selected');
      chip.querySelector('input').checked = true;
      applyProgramme(chip);
      scheduleAutosave();
    });
  });

  memberPan.addEventListener('input', function(){
    memberPan.value = memberPan.value.toUpperCase();
  });

  applyProgramme(document.querySelector('#programmeChips .chip.selected'));

  var prant = document.getElementById('prantSelect');
  var jila = document.getElementById('jilaSelect');
  var prakhand = document.getElementById('prakhandSelect');

  function fillSelect(sel, items, labelKey){
    sel.innerHTML = '<option value="">—</option>';
    items.forEach(function(it){
      var opt = document.createElement('option');
      opt.value = it.id; opt.textContent = it.name;
      sel.appendChild(opt);
    });
  }

  function loadJilas(prantId){
    if (!jila) { return Promise.resolve(); }
    if (!prantId) { jila.innerHTML = '<option value="">—</option>'; return Promise.resolve(); }
    return fetch('<?= site_url('api/locations/jilas') ?>/' + prantId)
      .then(function(r){ return r.json(); })
      .then(function(items){ fillSelect(jila, items); });
  }

  function loadPrakhands(jilaId){
    if (!prakhand) { return Promise.resolve(); }
    if (!jilaId) { prakhand.innerHTML = '<option value="">—</option>'; return Promise.resolve(); }
    return fetch('<?= site_url('api/locations/prakhands') ?>/' + jilaId)
      .then(function(r){ return r.json(); })
      .then(function(items){ fillSelect(prakhand, items); });
  }

  if (prant) {
    // Free-choice location pickers (Admin-level users only) — a
    // Karyakarta's own assigned location is locked instead (see below).
    prant.addEventListener('change', function(){
      prakhand.innerHTML = '<option value="">—</option>';
      loadJilas(prant.value);
      scheduleAutosave();
    });

    jila.addEventListener('change', function(){
      loadPrakhands(jila.value);
      scheduleAutosave();
    });

    prakhand.addEventListener('change', scheduleAutosave);
  }

  function createFamilyRow(prefill){
    var row = document.createElement('div');
    row.className = 'family-row';
    row.style.cssText = 'position:relative;border:1px solid var(--border);border-radius:10px;padding:10px 34px 10px 10px;margin-bottom:8px;background:var(--surface-alt)';

    var professionOptions = PROFESSIONS.map(function(p){
      var safe = p.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
      return '<option value="' + safe + '">' + safe + '</option>';
    }).join('');

    row.innerHTML = '<div class="form-grid-tight">' +
      '<div><label class="f-label-sm">' + T_FIRST_NAME + ' <span class="required-mark">*</span></label>' +
        '<input class="f-input family-first-name" name="family_first_name[]" required></div>' +
      '<div><label class="f-label-sm">' + T_LAST_NAME + '</label>' +
        '<input class="f-input family-last-name" name="family_last_name[]"></div>' +
      '<div><label class="f-label-sm">' + T_AGE_PLACEHOLDER + ' <span class="required-mark">*</span></label>' +
        '<input class="f-input family-age" name="family_age[]" required></div>' +
      '<div><label class="f-label-sm">' + T_PROFESSION + '</label>' +
        '<select class="f-input family-profession-select"><option value="">—</option>' + professionOptions + '</select>' +
        '<input class="f-input family-profession-other" style="display:none;margin-top:6px" placeholder="Please specify">' +
        '<input type="hidden" name="family_profession[]" class="family-profession-value"></div>' +
      '<div><label class="f-label-sm">' + T_CONTACT_NUMBER + '</label>' +
        '<input class="f-input family-contact" name="family_contact[]" inputmode="tel"></div>' +
      '</div>' +
      '<button type="button" class="btn btn-ghost btn-sm remove-family" style="position:absolute;top:6px;right:6px;color:var(--red-600)">✕</button>';

    row.querySelector('.remove-family').addEventListener('click', function(){
      row.remove();
      updateFamilyDependentUI();
      scheduleAutosave();
    });

    wireProfession(
      row.querySelector('.family-profession-select'),
      row.querySelector('.family-profession-other'),
      row.querySelector('.family-profession-value')
    );

    if (prefill) {
      row.querySelector('.family-first-name').value = prefill.first_name || '';
      row.querySelector('.family-last-name').value = prefill.last_name || '';
      row.querySelector('.family-age').value = prefill.age_or_dob || '';
      row.querySelector('.family-contact').value = prefill.contact_number || '';
      setProfessionValue(
        row.querySelector('.family-profession-select'),
        row.querySelector('.family-profession-other'),
        row.querySelector('.family-profession-value'),
        prefill.profession || ''
      );
    }

    familyList.appendChild(row);
    updateFamilyDependentUI();
    return row;
  }

  addFamilyBtn.addEventListener('click', function(){
    if (familyCount() >= MAX_FAMILY_MEMBERS) { return; }
    createFamilyRow(null);
    scheduleAutosave();
  });

  updateFamilyDependentUI();

  // ---- Duplicate-phone check (Hithachintak primary member only) ----
  var phoneDuplicate = false;
  var phoneCheckSeq = 0;

  function checkPhoneDuplicate(){
    // A forced self-enrolment is always this account's own number —
    // resuming/retrying it is expected, not a duplicate, and the server
    // skips this check for the same reason (see store()).
    if (FORCE_SELF) { return; }

    var digits = memberPhone.value.replace(/\D/g, '');
    phoneDuplicate = false;
    phoneNote.textContent = '';
    phoneNote.classList.remove('field-error-note');
    memberPhone.classList.remove('field-invalid');
    if (digits.length !== 10 || !isHcSelected()) { return; }

    var seq = ++phoneCheckSeq;
    fetch('<?= site_url('api/members/check-hc-phone') ?>/' + digits)
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (seq !== phoneCheckSeq) { return; }
        phoneDuplicate = !!data.registered;
        if (phoneDuplicate) {
          phoneNote.textContent = 'This mobile number is already registered for Hithachintak.';
          phoneNote.classList.add('field-error-note');
          memberPhone.classList.add('field-invalid');
        }
      })
      .catch(function(){ /* offline — server re-checks authoritatively on submit */ });
  }
  memberPhone.addEventListener('blur', checkPhoneDuplicate);
  memberPhone.addEventListener('input', function(){ phoneNote.textContent = ''; memberPhone.classList.remove('field-invalid'); });

  // ---- Client-side validation: instant alert instead of a server round-trip ----
  function isFormValid(){
    var errors = [];
    var firstBad = null;

    function markRequired(el, label){
      el.classList.remove('field-invalid');
      if (!el.value || !el.value.trim()) {
        errors.push(label + ' is required.');
        el.classList.add('field-invalid');
        if (!firstBad) { firstBad = el; }
        return false;
      }
      return true;
    }

    markRequired(memberFirstName, T_FIRST_NAME);
    markRequired(ageOrDob, T_AGE_PLACEHOLDER);
    markRequired(addressInput, 'Address');
    markRequired(pincodeInput, 'PIN code');

    if (markRequired(memberPhone, 'Mobile number')) {
      var digits = memberPhone.value.replace(/\D/g, '');
      if (digits.length !== 10) {
        errors.push('Mobile number must be exactly 10 digits.');
        memberPhone.classList.add('field-invalid');
        if (!firstBad) { firstBad = memberPhone; }
      } else if (isHcSelected() && phoneDuplicate) {
        errors.push('This mobile number is already registered for Hithachintak.');
        if (!firstBad) { firstBad = memberPhone; }
      }
    }

    emailInput.classList.remove('field-invalid');
    if (emailInput.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value)) {
      errors.push('Email address is not valid.');
      emailInput.classList.add('field-invalid');
      if (!firstBad) { firstBad = emailInput; }
    }

    document.querySelectorAll('.family-row').forEach(function(row, idx){
      var fn = row.querySelector('.family-first-name');
      var age = row.querySelector('.family-age');
      fn.classList.remove('field-invalid');
      age.classList.remove('field-invalid');
      if (!fn.value.trim()) {
        errors.push('Family member ' + (idx + 1) + ': ' + T_FIRST_NAME + ' is required.');
        fn.classList.add('field-invalid');
        if (!firstBad) { firstBad = fn; }
      }
      if (!age.value.trim()) {
        errors.push('Family member ' + (idx + 1) + ': ' + T_AGE_PLACEHOLDER + ' is required.');
        age.classList.add('field-invalid');
        if (!firstBad) { firstBad = age; }
      }
    });

    if (errors.length) {
      alert(errors.join('\n'));
      if (firstBad) { firstBad.focus(); }
      return false;
    }
    return true;
  }

  // ---- Autosave: localStorage first (survives offline/refresh/close),
  // best-effort sync to the server so the draft shows up in the
  // Enrolments list' "Pending Drafts" tab from any device. ----
  function uuid(){
    if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c){
      var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }

  function ensureDraftId(){
    if (!draftIdInput.value) { draftIdInput.value = uuid(); }
    return draftIdInput.value;
  }

  function collectFamilyState(){
    var out = [];
    document.querySelectorAll('.family-row').forEach(function(row){
      out.push({
        first_name: row.querySelector('.family-first-name').value,
        last_name: row.querySelector('.family-last-name').value,
        age_or_dob: row.querySelector('.family-age').value,
        profession: row.querySelector('.family-profession-value').value,
        contact_number: row.querySelector('.family-contact').value
      });
    });
    return out;
  }

  function collectState(){
    var programmeChip = document.querySelector('#programmeChips .chip.selected');
    var modeChip = document.querySelector('#paymentModeChips .chip.selected');
    var jilaHidden = document.querySelector('input[name="jila_id"]');
    var prakhandHidden = document.querySelector('input[name="prakhand_id"]');
    var lockedPrantId = document.getElementById('lockedPrantId');

    return {
      draft_id: draftIdInput.value,
      prant_id: prant ? prant.value : (lockedPrantId ? lockedPrantId.value : ''),
      jila_id: jila ? jila.value : (jilaHidden ? jilaHidden.value : ''),
      prakhand_id: prakhand ? prakhand.value : (prakhandHidden ? prakhandHidden.value : ''),
      programme_code: programmeChip ? programmeChip.dataset.code : '',
      payment_mode: modeChip ? modeChip.dataset.mode : 'online',
      member_first_name: memberFirstName.value,
      member_last_name: memberLastName.value,
      age_or_dob: ageOrDob.value,
      profession: professionValue.value,
      member_phone: memberPhone.value,
      email: emailInput.value,
      address: addressInput.value,
      pincode: pincodeInput.value,
      member_pan: memberPan.value,
      amount: amountInput.value,
      family: collectFamilyState()
    };
  }

  function saveLocal(state){
    try { localStorage.setItem('vhp_draft_' + state.draft_id, JSON.stringify(state)); } catch (err) { /* storage full/unavailable */ }
  }

  var autosaveTimer = null;
  function saveRemote(state){
    var fd = new FormData();
    fd.append('draft_id', state.draft_id);
    fd.append('payload', JSON.stringify(state));
    fd.append(CSRF_NAME, csrfInput ? csrfInput.value : '');
    fetch('<?= site_url('admin/enrolments/draft') ?>', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.ok ? r.json() : null; })
      .then(function(data){
        if (data && data[CSRF_NAME] && csrfInput) { csrfInput.value = data[CSRF_NAME]; }
        if (autosaveNote) {
          var now = new Date();
          autosaveNote.textContent = 'Saved ' + now.getHours() + ':' + String(now.getMinutes()).padStart(2, '0');
        }
      })
      .catch(function(){ /* offline — localStorage already has it, retried on next edit */ });
  }

  function scheduleAutosave(){
    ensureDraftId();
    saveLocal(collectState());
    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(function(){ saveRemote(collectState()); }, 1200);
  }

  form.addEventListener('input', scheduleAutosave);
  form.addEventListener('change', scheduleAutosave);

  form.addEventListener('submit', function(e){
    if (!isFormValid()) {
      e.preventDefault();
      return;
    }
    if (draftIdInput.value) {
      try { localStorage.removeItem('vhp_draft_' + draftIdInput.value); } catch (err) { /* best effort */ }
    }
  });

  // ---- Resume: an explicit ?resume=<id> from the Enrolments list, or a
  // bounce-back from a failed server validation (same browser still has
  // the localStorage copy under the draft_id the server handed back). ----
  function applyState(state){
    if (!state) { return; }
    draftIdInput.value = state.draft_id || RESUME_DRAFT_ID || '';

    var proceed = Promise.resolve();
    if (prant && state.prant_id) {
      prant.value = state.prant_id;
      proceed = loadJilas(state.prant_id).then(function(){
        if (state.jila_id) {
          jila.value = state.jila_id;
          return loadPrakhands(state.jila_id).then(function(){
            if (state.prakhand_id) { prakhand.value = state.prakhand_id; }
          });
        }
      });
    }

    proceed.then(function(){
      if (state.programme_code) {
        var chip = document.querySelector('#programmeChips .chip[data-code="' + state.programme_code + '"]');
        if (chip) { chip.click(); }
      }
      if (state.payment_mode) { selectPaymentMode(state.payment_mode); }

      memberFirstName.value = state.member_first_name || '';
      memberLastName.value = state.member_last_name || '';
      ageOrDob.value = state.age_or_dob || '';
      memberPhone.value = state.member_phone || '';
      emailInput.value = state.email || '';
      addressInput.value = state.address || '';
      pincodeInput.value = state.pincode || '';
      if (memberPan) { memberPan.value = state.member_pan || ''; }
      setProfessionValue(professionSelect, professionOther, professionValue, state.profession || '');

      if (state.family && state.family.length) {
        state.family.forEach(function(fm){ createFamilyRow(fm); });
      }

      if (!isHcSelected() && state.amount) { amountInput.value = state.amount; }

      checkPhoneDuplicate();
    });
  }

  var effectiveResumeId = RESUME_DRAFT_ID || (RESUME_DRAFT && RESUME_DRAFT.draft_id) || null;
  var localResume = null;
  if (effectiveResumeId) {
    try { localResume = JSON.parse(localStorage.getItem('vhp_draft_' + effectiveResumeId) || 'null'); } catch (err) { /* ignore */ }
  }
  if (localResume) {
    applyState(localResume);
  } else if (RESUME_DRAFT) {
    applyState(RESUME_DRAFT);
  }
})();
</script>

<?= $this->endSection() ?>
