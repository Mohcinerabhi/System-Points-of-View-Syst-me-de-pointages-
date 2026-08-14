<?php
/**
 * Admin profile page
 */
$user = $user ?? [];
$errors = $errors ?? [];
$csrf_token = $csrf_token ?? '';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h4 class="text-xl font-semibold text-gray-900"><i class="fas fa-user-shield text-blue-600 mr-2"></i>Profil Administrateur</h4>
        <div class="text-gray-500 text-sm">Gérez vos informations personnelles et vos préférences système</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Profile Card -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 text-center">
            <div class="w-24 h-24 rounded-full bg-blue-600 text-white flex items-center justify-center text-3xl mx-auto mb-4">
                <i class="fas fa-user"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></h3>
            <p class="text-gray-500 text-sm mt-1">@<?= htmlspecialchars($user['username'] ?? '') ?></p>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mt-2">
                Administrateur
            </span>
            <div class="mt-4 pt-4 border-t border-gray-200">
                <div class="flex items-center justify-center gap-2 text-sm text-gray-600">
                    <i class="fas fa-envelope"></i>
                    <span><?= htmlspecialchars($user['email'] ?? '') ?></span>
                </div>
                <?php if (!empty($user['phone'])): ?>
                <div class="flex items-center justify-center gap-2 text-sm text-gray-600 mt-2">
                    <i class="fas fa-phone"></i>
                    <span><?= htmlspecialchars($user['phone']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Profile Edit Form -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h5 class="font-semibold text-gray-900"><i class="fas fa-edit mr-2"></i>Modifier le profil</h5>
            </div>
            <div class="p-6">
                <?php if (!empty($errors)): ?>
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <div class="text-red-800 font-medium">Veuillez corriger les erreurs suivantes :</div>
                        <ul class="mt-2 text-sm text-red-600 list-disc list-inside">
                            <?php foreach ($errors as $field => $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="index.php?controller=profile&action=update">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prénom</label>
                            <input type="text" name="first_name" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nom</label>
                            <input type="text" name="last_name" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                            <input type="text" name="phone" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mt-6">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium">
                            <i class="fas fa-save mr-1"></i>Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mt-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h5 class="font-semibold text-gray-900"><i class="fas fa-lock mr-2"></i>Changer le mot de passe</h5>
            </div>
            <div class="p-6">
                <form method="post" action="index.php?controller=profile&action=changePassword">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe actuel</label>
                            <input type="password" name="current_password" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        </div>
                        <div></div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nouveau mot de passe</label>
                            <input type="password" name="new_password" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirmer le mot de passe</label>
                            <input type="password" name="confirm_password" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        </div>
                    </div>
                    <div class="mt-6">
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 shadow-sm font-medium">
                            <i class="fas fa-key mr-1"></i>Modifier le mot de passe
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
