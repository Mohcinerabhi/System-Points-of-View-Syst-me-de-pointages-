<?php
/**
 * Layout principal - Attendance Pro
 * Structure moderne : sidebar + header + contenu + footer
 */
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(\App\Helpers\Language::current()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token ?? '') ?>">
    <meta name="base-url" content="">
    <title><?= htmlspecialchars($pageTitle ?? 'Système de Pointage') ?> - AttendPro</title>
    <link rel="icon" type="image/jpeg" href="assets/images/logoPouinteuse.jpg">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Bootstrap 5 CSS (pagination and component styles) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php if (!empty($auth_user)): ?>
        <?php include __DIR__ . '/sidebar.php'; ?>
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-20 hidden" id="sidebarOverlay"></div>
        <?php include __DIR__ . '/header.php'; ?>
    <?php endif; ?>

    <main id="mainContent" class="flex-1 min-w-0 transition-all duration-300 <?= !empty($auth_user) ? 'lg:ml-64' : '' ?>">
        <?php if (!empty($auth_user)): ?>
        <div class="p-6 flex-1">
            <?php endif; ?>

            <?php if (!empty($flash_success)): ?>
                <div class="flex items-center gap-2 p-4 bg-green-50 text-green-800 rounded-lg mb-4" role="alert">
                    <?= htmlspecialchars($flash_success) ?>
                    <button type="button" class="ml-auto p-1 rounded hover:bg-green-100" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>
            <?php if (!empty($flash_error)): ?>
                <div class="flex items-center gap-2 p-4 bg-red-50 text-red-800 rounded-lg mb-4" role="alert">
                    <?= htmlspecialchars($flash_error) ?>
                    <button type="button" class="ml-auto p-1 rounded hover:bg-red-100" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>
            <?php if (!empty($flash_info)): ?>
                <div class="flex items-center gap-2 p-4 bg-sky-50 text-sky-800 rounded-lg mb-4" role="alert">
                    <?= htmlspecialchars($flash_info) ?>
                    <button type="button" class="ml-auto p-1 rounded hover:bg-sky-100" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <?= $content ?>

            <?php if (!empty($auth_user)): ?>
        </div>
    <?php endif; ?>
</main>

    <?php if (!empty($auth_user)): ?>
        <?php include __DIR__ . '/footer.php'; ?>
    <?php endif; ?>

    <?php if (!empty(\App\Helpers\Session::get('refresh_dashboard'))): ?>
        <script>
            <?php \App\Helpers\Session::remove('refresh_dashboard'); ?>
            if (window.App && App.refreshDashboardStats) {
                setTimeout(App.refreshDashboardStats, 500);
            }
        </script>
    <?php endif; ?>
</body>
</html>
