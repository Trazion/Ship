<?php
function displayMessage($type, $message) {
    $icons = [
        'success' => 'bi-check-circle-fill',
        'error' => 'bi-exclamation-circle-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'info' => 'bi-info-circle-fill'
    ];
    
    $icon = $icons[$type] ?? 'bi-info-circle-fill';
    ?>
    <div class="alert alert-<?= $type ?> alert-dismissible fade show" role="alert">
        <i class="bi <?= $icon ?> me-2"></i>
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php
}
?>
