<?php

declare(strict_types=1);
?>
        </div>
    </div>
    <div class="sidebar-backdrop" data-sidebar-close hidden></div>
    <div class="toast" id="admin-toast" role="status" hidden></div>
    <?php if (isset($adminScript) && is_string($adminScript) && $adminScript !== ''): ?>
        <script src="<?= root_url($adminScript . '?v=' . filemtime(dirname(__DIR__, 2) . '/' . $adminScript)) ?>" defer></script>
    <?php endif; ?>
    <script src="<?= root_url('assets/js/admin.js?v=' . filemtime(dirname(__DIR__, 2) . '/assets/js/admin.js')) ?>" defer></script>
</body>
</html>
