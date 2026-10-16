<?php
/**
 * Shared "Pay via" picker — included inside a form wherever a payment
 * can go through more than one gateway. A single available gateway
 * needs no picker at all (just a hidden field so the posted/queried
 * `gateway` value still matches what EnrolmentController::
 * resolveGatewayChoice() expects); zero available gateways is handled
 * by the caller disabling the button entirely, so this partial has
 * nothing to render in that case.
 *
 * Expects $availableGateways (list<string>) in scope.
 */
$gatewayLabels = ['cashfree' => 'Cashfree', 'razorpay' => 'Razorpay'];
?>
<?php if (count($availableGateways) > 1): ?>
  <div class="field" style="margin-bottom:10px">
    <label class="f-label">Pay via</label>
    <div class="row-actions" style="gap:14px">
      <?php foreach ($availableGateways as $i => $gw): ?>
        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;font-weight:normal">
          <input type="radio" name="gateway" value="<?= esc($gw) ?>" <?= $i === 0 ? 'checked' : '' ?>>
          <?= esc($gatewayLabels[$gw] ?? ucfirst($gw)) ?>
        </label>
      <?php endforeach; ?>
    </div>
  </div>
<?php elseif (count($availableGateways) === 1): ?>
  <input type="hidden" name="gateway" value="<?= esc($availableGateways[0]) ?>">
<?php endif; ?>
