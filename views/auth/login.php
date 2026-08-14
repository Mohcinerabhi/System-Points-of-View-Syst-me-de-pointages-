<?php
/**
 * Login page
 */
$errors = $errors ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Attendance Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.tailwindcss.com">
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="w-full max-w-md bg-white rounded-lg shadow-sm border border-gray-200 p-8">
        <div class="text-center mb-8">
            <img src="assets/images/logoPouinteuse.jpg" alt="Logo" class="h-16 w-auto mx-auto mb-4">
            <h1 class="text-xl font-bold text-gray-900"><?= __('app_name') ?></h1>
            <p class="text-sm text-gray-500 mt-1"><?= __('login') ?></p>
        </div>

        <?php if (!empty($errors['csrf_token'])): ?>
            <div class="flex items-center gap-2 p-4 bg-red-50 text-red-800 rounded-lg mb-4">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($errors['csrf_token']) ?></span>
            </div>
        <?php endif; ?>

        <form method="post" action="index.php?controller=auth&action=login">
            <?= \App\Helpers\Csrf::field() ?>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1" for="username"><?= __('username') ?></label>
                <div class="flex">
                    <input type="text" id="username" name="username" class="flex-1 rounded-l-md border-gray-300 border border-r-0 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($username ?? '') ?>" required autofocus>
                    <span class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500">
                        <i class="fa-solid fa-user"></i>
                    </span>
                </div>
                <?php if (!empty($errors['username'])): ?>
                    <div class="text-red-600 text-sm mt-1"><?= htmlspecialchars($errors['username']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1" for="password"><?= __('password') ?></label>
                <div class="flex">
                    <input type="password" id="password" name="password" class="flex-1 rounded-l-md border-gray-300 border border-r-0 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    <span class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                </div>
                <?php if (!empty($errors['password'])): ?>
                    <div class="text-red-600 text-sm mt-1"><?= htmlspecialchars($errors['password']) ?></div>
                <?php endif; ?>
            </div>

            <button type="submit" class="w-full px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-lg">
                <i class="fa-solid fa-right-to-bracket"></i> <?= __('login') ?>
            </button>
        </form>

            <div class="text-center text-sm text-gray-500 mt-8">
                &copy; <?= date('Y') ?> AttendPro. <?= __('all_rights_reserved', 'All rights reserved') ?>
            </div>
    </div>
</body>
</html>
