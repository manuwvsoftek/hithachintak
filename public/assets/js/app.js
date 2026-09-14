/**
 * Small shared behaviors used across admin/mobile pages, kept in one
 * external file (rather than inline onclick=/onchange= attributes) so it
 * works under the app's Content-Security-Policy without weakening it —
 * inline event-handler attributes can't be nonce-allowed under CSP the
 * way a <script> tag can.
 */
(function () {
  document.querySelectorAll('.js-auto-submit').forEach(function (el) {
    el.addEventListener('change', function () {
      el.form.submit();
    });
  });

  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(el.dataset.confirm)) {
        e.preventDefault();
      }
    });
  });

  // Inline rename rows: a row starts in [data-view] (name + action
  // buttons) and swaps to its sibling [data-edit] form on click, rather
  // than showing a live, always-editable input on every row at once.
  document.querySelectorAll('[data-edit-trigger]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var row = btn.closest('[data-row]');
      if (!row) return;
      row.querySelectorAll('[data-view]').forEach(function (el) { el.hidden = true; });
      var edit = row.querySelector('[data-edit]');
      if (!edit) return;
      edit.hidden = false;
      var input = edit.querySelector('input[type="text"], input:not([type])');
      if (input) {
        input.focus();
        input.select();
      }
    });
  });
  document.querySelectorAll('[data-edit-cancel]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var row = btn.closest('[data-row]');
      if (!row) return;
      row.querySelectorAll('[data-view]').forEach(function (el) { el.hidden = false; });
      var edit = row.querySelector('[data-edit]');
      if (edit) edit.hidden = true;
    });
  });

  // Enrolments table: expand/collapse the additional family members
  // listed under a row's head-of-family name.
  document.querySelectorAll('[data-family-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var list = btn.nextElementSibling;
      if (!list) return;
      list.hidden = !list.hidden;
      btn.textContent = (list.hidden ? '+ ' : '− ') + btn.dataset.familyToggle;
      btn.setAttribute('aria-expanded', String(!list.hidden));
    });
  });

  // Multi-select chip groups (e.g. a Prant's enrolment languages, or the
  // Reports column picker): each chip is a <label> wrapping a hidden
  // checkbox. Listening for the label's own 'click' and toggling the
  // checkbox there double-toggles it — the browser's native label-click
  // behavior *also* forwards a synthetic click to the checkbox, which
  // bubbles back up through the label and re-fires this same listener, so
  // the two toggles cancel out and the chip never actually (de)selects.
  // Listening on the checkbox's 'change' event instead fires exactly once
  // per real state change, letting the native forwarding do the toggling
  // and this only sync the chip's visual class to match.
  document.querySelectorAll('[data-chip-multi] .chip').forEach(function (chip) {
    var input = chip.querySelector('input');
    if (!input) return;
    input.addEventListener('change', function () {
      chip.classList.toggle('selected', input.checked);
    });
  });

  // New/Edit User forms: cascading Prant -> Jila -> Prakhand selects (only
  // present for the levels the actor doesn't already have fixed), a
  // role-based depth toggle — picking "Pranta Admin" hides Jila/Prakhand,
  // picking "Karyakarta" shows all the way down — and, at whichever level
  // is the selected role's OWN tier, a multi-select + "All" checkbox in
  // place of the single picker (Prant for Pranta Admin, Jila for Jila
  // Admin, Prakhand for Prakhand Admin).
  document.querySelectorAll('[data-user-form]').forEach(function (form) {
    var roleSelect     = form.querySelector('[data-role-select]');
    var prantSelect    = form.querySelector('[data-prant-select]');
    var jilaSelect     = form.querySelector('[data-jila-select]');
    var prakhandSelect = form.querySelector('[data-prakhand-select]');
    var prantMulti     = form.querySelector('[data-prant-multi]');
    var jilaMulti      = form.querySelector('[data-jila-multi]');
    var prakhandMulti  = form.querySelector('[data-prakhand-multi]');
    var prantAll       = form.querySelector('[data-prant-all]');
    var jilaAll        = form.querySelector('[data-jila-all]');
    var prakhandAll    = form.querySelector('[data-prakhand-all]');

    function fillSelect(sel, items) {
      if (!sel) return;
      var current = sel.value;
      sel.innerHTML = '<option value="">—</option>';
      items.forEach(function (it) {
        var opt = document.createElement('option');
        opt.value = it.id;
        opt.textContent = it.name;
        if (String(it.id) === current) opt.selected = true;
        sel.appendChild(opt);
      });
    }

    // The multi-select fields (a role's own tier) render as a scrollable
    // checkbox list instead of a <select multiple> — plain, individually
    // clickable checkboxes need no custom toggle JS at all (unlike the
    // chip widget above), only rebuilding the list when the AJAX-fetched
    // options change, preserving whichever ones are already checked.
    function fillCheckboxList(container, items, name) {
      if (!container) return;
      var current = Array.prototype.map.call(
        container.querySelectorAll('input:checked'),
        function (el) { return el.value; }
      );
      container.innerHTML = '';
      if (!items.length) {
        var empty = document.createElement('div');
        empty.className = 'check-list-empty';
        empty.textContent = 'Select the field above first.';
        container.appendChild(empty);
        return;
      }
      items.forEach(function (it) {
        var label = document.createElement('label');
        label.className = 'check-item';
        var input = document.createElement('input');
        input.type = 'checkbox';
        input.name = name;
        input.value = it.id;
        if (current.indexOf(String(it.id)) !== -1) input.checked = true;
        label.appendChild(input);
        label.appendChild(document.createTextNode(it.name));
        container.appendChild(label);
      });
    }

    function loadJilas(prantId, done) {
      if (!jilaSelect && !jilaMulti) return;
      if (!prantId) { fillSelect(jilaSelect, []); fillCheckboxList(jilaMulti, [], 'jila_ids[]'); if (done) done(); return; }
      fetch(form.dataset.jilasUrl + '/' + prantId)
        .then(function (r) { return r.json(); })
        .then(function (items) { fillSelect(jilaSelect, items); fillCheckboxList(jilaMulti, items, 'jila_ids[]'); if (done) done(); });
    }
    function loadPrakhands(jilaId, done) {
      if (!prakhandSelect && !prakhandMulti) return;
      if (!jilaId) { fillSelect(prakhandSelect, []); fillCheckboxList(prakhandMulti, [], 'prakhand_ids[]'); if (done) done(); return; }
      fetch(form.dataset.prakhandsUrl + '/' + jilaId)
        .then(function (r) { return r.json(); })
        .then(function (items) { fillSelect(prakhandSelect, items); fillCheckboxList(prakhandMulti, items, 'prakhand_ids[]'); if (done) done(); });
    }

    if (prantSelect) {
      prantSelect.addEventListener('change', function () {
        loadJilas(prantSelect.value);
        fillSelect(prakhandSelect, []);
        fillCheckboxList(prakhandMulti, [], 'prakhand_ids[]');
      });
    }
    if (jilaSelect) {
      jilaSelect.addEventListener('change', function () { loadPrakhands(jilaSelect.value); });
    }

    // An "All" checkbox makes its paired checkbox list redundant — disable
    // every checkbox in it (so nothing is submitted from it) and dim it,
    // rather than hiding it, so the admin can still see what "All" is
    // standing in for.
    function setListDisabled(container, disabled) {
      if (!container) return;
      container.classList.toggle('is-disabled', disabled);
      container.querySelectorAll('input').forEach(function (el) { el.disabled = disabled; });
    }
    function wireScopeAll(checkbox, container) {
      if (!checkbox || !container) return;
      checkbox.addEventListener('change', function () {
        setListDisabled(container, checkbox.checked);
      });
      setListDisabled(container, checkbox.checked);
    }
    wireScopeAll(prantAll, prantMulti);
    wireScopeAll(jilaAll, jilaMulti);
    wireScopeAll(prakhandAll, prakhandMulti);

    function applyDepth() {
      if (!roleSelect) return;
      var opt      = roleSelect.options[roleSelect.selectedIndex];
      var depth    = parseInt((opt && opt.dataset.depth) || '2', 10);
      var ownTierAttr = opt ? opt.dataset.ownTier : '';
      var ownTier  = ownTierAttr ? parseInt(ownTierAttr, 10) : null;

      form.querySelectorAll('[data-loc-level]').forEach(function (field) {
        var level  = parseInt(field.dataset.locLevel, 10);
        var isMulti = field.dataset.variant === 'multi';
        var hidden;
        if (level > depth) {
          hidden = true;
        } else if (level === ownTier) {
          hidden = !isMulti;
        } else {
          hidden = isMulti;
        }
        field.hidden = hidden;
        field.querySelectorAll('select, input').forEach(function (el) { el.disabled = hidden; });
        // Re-apply the "All" checkbox's own disabling of its checkbox list
        // once fields are re-enabled, so toggling role back doesn't
        // resurrect a list that "All" should still be suppressing.
        if (!hidden && isMulti) {
          var all  = field.querySelector('input[type="checkbox"][data-prant-all], input[type="checkbox"][data-jila-all], input[type="checkbox"][data-prakhand-all]');
          var list = field.querySelector('.check-list');
          if (all && list) setListDisabled(list, all.checked);
        }
      });
    }
    if (roleSelect) {
      roleSelect.addEventListener('change', applyDepth);
      applyDepth();
    }

    // ---- Client-side validation: instant feedback instead of a server
    // round-trip that (before this) threw away everything typed. Generic
    // over whatever [data-loc-level] fields are currently visible, so it
    // works unchanged for every actor/role combination applyDepth() can
    // produce, on both the New User and Edit User forms. ----
    function isFormValid() {
      var errors = [];
      var firstBad = null;

      function markInvalid(el, msg) {
        errors.push(msg);
        if (el) el.classList.add('field-invalid');
        if (!firstBad && el) firstBad = el;
      }

      var nameInput  = form.querySelector('input[name="name"]');
      var phoneInput = form.querySelector('input[name="phone"]');
      var passwordInput = form.querySelector('input[name="password"]');

      form.querySelectorAll('.field-invalid').forEach(function (el) { el.classList.remove('field-invalid'); });

      if (nameInput && !nameInput.value.trim()) {
        markInvalid(nameInput, 'Full name is required.');
      }

      if (phoneInput) {
        var digits = phoneInput.value.replace(/\D/g, '');
        if (!digits) {
          markInvalid(phoneInput, 'Phone number is required.');
        } else if (!/^[6-9][0-9]{9}$/.test(digits)) {
          markInvalid(phoneInput, 'Phone number must be a valid 10-digit mobile number.');
        }
      }

      if (passwordInput && passwordInput.value && passwordInput.value.length < 6) {
        markInvalid(passwordInput, 'Password must be at least 6 characters.');
      }

      form.querySelectorAll('[data-loc-level]').forEach(function (field) {
        if (field.hidden) return;
        var labelEl = field.querySelector('.f-label');
        var label = labelEl ? labelEl.textContent.replace(/\(s\)$/, '') : 'This field';
        if (field.dataset.variant === 'single') {
          var sel = field.querySelector('select');
          if (sel && !sel.disabled && !sel.value) {
            markInvalid(sel, 'Select a ' + label + '.');
          }
        } else if (field.dataset.variant === 'multi') {
          var allCb = field.querySelector('input[data-prant-all], input[data-jila-all], input[data-prakhand-all]');
          var list  = field.querySelector('.check-list');
          var anyChecked = list && list.querySelectorAll('input:checked').length > 0;
          if (!(allCb && allCb.checked) && !anyChecked) {
            markInvalid(list, 'Select at least one ' + label + ', or check "All".');
          }
        }
      });

      if (errors.length) {
        alert(errors.join('\n'));
        if (firstBad && firstBad.focus) firstBad.focus();
        return false;
      }
      return true;
    }

    form.addEventListener('submit', function (e) {
      if (!isFormValid()) { e.preventDefault(); }
    });

    // No Prant picker here means the actor's own Prant (and maybe Jila)
    // is fixed server-side — preload from it since there's no Prant
    // change event to trigger the cascade from.
    var actorPrant = form.dataset.actorPrant;
    var actorJila  = form.dataset.actorJila;

    // Fires once, after whatever initial location cascade (if any) has
    // settled — immediately if none of the branches below apply. The New
    // User form's autosave/resume setup hangs off this so it never races
    // the actor's own fixed-location preload for a Jila/Prakhand Admin
    // creating a subordinate.
    function afterInitialLoad() {
      setUpAutosave();
    }

    if (!prantSelect && (jilaSelect || jilaMulti) && actorPrant && actorPrant !== '0') {
      loadJilas(actorPrant, function () {
        if (actorJila && actorJila !== '0' && jilaSelect) {
          jilaSelect.value = actorJila;
        }
        if (jilaSelect && jilaSelect.value) {
          loadPrakhands(jilaSelect.value, afterInitialLoad);
        } else {
          afterInitialLoad();
        }
      });
    } else if (!prantSelect && !jilaSelect && !jilaMulti && (prakhandSelect || prakhandMulti) && actorJila && actorJila !== '0') {
      loadPrakhands(actorJila, afterInitialLoad);
    } else if (prantSelect && prantSelect.value) {
      // Edit form: a Prant is already selected on load — populate Jila
      // (and, once that resolves, Prakhand) so the existing values show.
      loadJilas(prantSelect.value, function () {
        if (jilaSelect && jilaSelect.value) {
          loadPrakhands(jilaSelect.value, afterInitialLoad);
        } else {
          afterInitialLoad();
        }
      });
    } else {
      afterInitialLoad();
    }

    // ---- Autosave (New User form only, marked by data-autosave-url —
    // Edit User has no draft concept, it's editing a row that already
    // exists). Same localStorage-first, best-effort-remote pattern as the
    // New Enrolment form's autosave. ----
    function setUpAutosave() {
      if (!form.dataset.autosaveUrl) return;

      var draftIdInput = form.querySelector('input[name="draft_id"]');
      var csrfName      = form.dataset.csrfName;
      var csrfInput     = csrfName ? form.querySelector('input[name="' + csrfName + '"]') : null;
      var autosaveNote  = document.getElementById('userAutosaveNote');

      function uuid() {
        if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
          var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
          return v.toString(16);
        });
      }
      function ensureDraftId() {
        if (!draftIdInput.value) { draftIdInput.value = uuid(); }
        return draftIdInput.value;
      }
      function checkedValues(container) {
        if (!container) return [];
        return Array.prototype.map.call(container.querySelectorAll('input:checked'), function (el) { return el.value; });
      }
      function fieldVal(name) {
        var el = form.querySelector('[name="' + name + '"]');
        return el ? el.value : '';
      }

      function collectState() {
        return {
          draft_id: draftIdInput.value,
          name: fieldVal('name'),
          phone: fieldVal('phone'),
          role: roleSelect ? roleSelect.value : '',
          prant_id: prantSelect ? prantSelect.value : '',
          jila_id: jilaSelect ? jilaSelect.value : '',
          prakhand_id: prakhandSelect ? prakhandSelect.value : '',
          prant_ids: checkedValues(prantMulti),
          jila_ids: checkedValues(jilaMulti),
          prakhand_ids: checkedValues(prakhandMulti),
          prant_scope_all: !!(prantAll && prantAll.checked),
          jila_scope_all: !!(jilaAll && jilaAll.checked),
          prakhand_scope_all: !!(prakhandAll && prakhandAll.checked),
          address: fieldVal('address'),
          aadhar_number: fieldVal('aadhar_number'),
          email: fieldVal('email'),
          profession: fieldVal('profession')
          // password is deliberately never included — a draft is stored
          // as plain JSON, not hashed/encrypted like the real account
          // record, so a typed password shouldn't sit in it.
        };
      }

      function saveLocal(state) {
        try { localStorage.setItem('vhp_user_draft_' + state.draft_id, JSON.stringify(state)); } catch (err) { /* storage full/unavailable */ }
      }

      var autosaveTimer = null;
      function saveRemote(state) {
        var fd = new FormData();
        fd.append('draft_id', state.draft_id);
        fd.append('payload', JSON.stringify(state));
        if (csrfName && csrfInput) { fd.append(csrfName, csrfInput.value); }
        fetch(form.dataset.autosaveUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (data) {
            if (data && csrfName && data[csrfName] && csrfInput) { csrfInput.value = data[csrfName]; }
            if (autosaveNote) {
              var now = new Date();
              autosaveNote.textContent = 'Saved ' + now.getHours() + ':' + String(now.getMinutes()).padStart(2, '0');
            }
          })
          .catch(function () { /* offline — localStorage already has it, retried on next edit */ });
      }

      function scheduleAutosave() {
        ensureDraftId();
        saveLocal(collectState());
        clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(function () { saveRemote(collectState()); }, 1200);
      }
      form.addEventListener('input', scheduleAutosave);
      form.addEventListener('change', scheduleAutosave);

      form.addEventListener('submit', function () {
        if (draftIdInput.value) {
          try { localStorage.removeItem('vhp_user_draft_' + draftIdInput.value); } catch (err) { /* best effort */ }
        }
      });

      function setField(name, value) {
        var el = form.querySelector('[name="' + name + '"]');
        if (el) el.value = value || '';
      }

      function applyState(state) {
        if (!state) return;
        draftIdInput.value = state.draft_id || form.dataset.resumeDraftId || '';
        setField('name', state.name);
        setField('phone', state.phone);
        if (state.role && roleSelect) { roleSelect.value = state.role; applyDepth(); }

        function finish() {
          if (prakhandSelect && state.prakhand_id) { prakhandSelect.value = state.prakhand_id; }
          (state.prant_ids || []).forEach(function (id) {
            var cb = prantMulti && prantMulti.querySelector('input[value="' + id + '"]');
            if (cb) { cb.checked = true; }
          });
          (state.jila_ids || []).forEach(function (id) {
            var cb = jilaMulti && jilaMulti.querySelector('input[value="' + id + '"]');
            if (cb) { cb.checked = true; }
          });
          (state.prakhand_ids || []).forEach(function (id) {
            var cb = prakhandMulti && prakhandMulti.querySelector('input[value="' + id + '"]');
            if (cb) { cb.checked = true; }
          });
          if (prantAll && state.prant_scope_all) { prantAll.checked = true; setListDisabled(prantMulti, true); }
          if (jilaAll && state.jila_scope_all) { jilaAll.checked = true; setListDisabled(jilaMulti, true); }
          if (prakhandAll && state.prakhand_scope_all) { prakhandAll.checked = true; setListDisabled(prakhandMulti, true); }
          setField('address', state.address);
          setField('aadhar_number', state.aadhar_number);
          setField('email', state.email);
          setField('profession', state.profession);
        }

        if (prantSelect && state.prant_id) {
          prantSelect.value = state.prant_id;
          loadJilas(state.prant_id, function () {
            if (jilaSelect && state.jila_id) {
              jilaSelect.value = state.jila_id;
              loadPrakhands(state.jila_id, finish);
            } else {
              finish();
            }
          });
        } else if (!prantSelect && jilaSelect && state.jila_id) {
          jilaSelect.value = state.jila_id;
          loadPrakhands(state.jila_id, finish);
        } else {
          finish();
        }
      }

      var resumeId = form.dataset.resumeDraftId || '';
      var localResume = null;
      if (resumeId) {
        try { localResume = JSON.parse(localStorage.getItem('vhp_user_draft_' + resumeId) || 'null'); } catch (err) { /* ignore */ }
      }
      if (localResume) {
        applyState(localResume);
      } else if (window.VHP_USER_RESUME_DRAFT) {
        applyState(window.VHP_USER_RESUME_DRAFT);
      }
    }
  });

  // "Copy" buttons next to a read-only field (e.g. the webhook URL).
  document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = document.querySelector(btn.dataset.copyTarget);
      if (!target) return;
      navigator.clipboard.writeText(target.value).then(function () {
        var original = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(function () { btn.textContent = original; }, 1500);
      });
    });
  });
})();
