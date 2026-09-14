<?php foreach (['error', 'success', 'info'] as $type): ?>
    <?php if ($msg = session()->getFlashdata($type)): ?>
        <div class="flash flash-<?= $type ?>"><?= esc($msg) ?></div>
    <?php endif; ?>
<?php endforeach; ?>
<?php if ($errors = session()->getFlashdata('errors')): ?>
    <div class="flash flash-error">
        <?= implode('<br>', array_map('esc', $errors)) ?>
    </div>
<?php endif; ?>
