<?php
/** Vue : formulaire de mot de passe oublié. */
$errors = $errors ?? [];
$csrf_token = $csrf_token ?? '';
$email = $email ?? '';
?>
<div class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="w-full max-w-md bg-white rounded-lg shadow-sm border border-gray-200 p-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Mot de passe oublié</h1>

        <?php if (!empty($errors['csrf_token'])): ?>
            <div class="p-4 bg-red-50 text-red-800 rounded-lg mb-4"><?= htmlspecialchars($errors['csrf_token']) ?></div>
        <?php endif; ?>

        <form method="post" action="index.php?controller=auth&action=forgotPassword">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Adresse e-mail</label>
                <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <?php if (!empty($errors['email'])): ?>
                    <span class="text-red-600 text-sm mt-1 block"><?= htmlspecialchars($errors['email']) ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Envoyer le lien de réinitialisation</button>
            <p class="mt-4"><a href="index.php?controller=auth&action=login" class="text-blue-600 hover:text-blue-700">Retour à la connexion</a></p>
        </form>
    </div>
</div>
