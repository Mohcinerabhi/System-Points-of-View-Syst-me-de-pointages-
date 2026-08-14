<?php
/**
 * Vue : affectation d'un badge / terminal à un employé.
 */
$employee = $employee ?? [];
$errors   = $errors   ?? [];
$csrf_token = $csrf_token ?? '';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h4 class="text-xl font-semibold text-gray-900">Affecter un terminal &mdash; <?= htmlspecialchars(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')) ?></h4>
    <a href="index.php?controller=employees&action=view&id=<?= (int)($employee['id'] ?? 0) ?>" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Retour</a>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <form method="post" action="index.php?controller=employees&action=assignTerminal&id=<?= (int)($employee['id'] ?? 0) ?>">
        <div class="p-6">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">ID du badge <span class="text-red-600">*</span></label>
                <input type="text" name="badge_id" value="<?= htmlspecialchars($employee['badge_id'] ?? '') ?>" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <?php if (!empty($errors['badge_id'])): ?><span class="text-red-600 text-sm mt-1 block"><?= htmlspecialchars($errors['badge_id']) ?></span><?php endif; ?>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Code du badge <span class="text-red-600">*</span></label>
                <input type="text" name="badge_code" value="<?= htmlspecialchars($employee['badge_code'] ?? '') ?>" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <?php if (!empty($errors['badge_code'])): ?><span class="text-red-600 text-sm mt-1 block"><?= htmlspecialchars($errors['badge_code']) ?></span><?php endif; ?>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">ID utilisateur Hikvision</label>
                <input type="number" name="hikvision_user_id" value="<?= htmlspecialchars($employee['hikvision_user_id'] ?? '') ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Statut d'enregistrement</label>
                <select name="registration_status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <?php foreach (['pending' => 'En attente', 'registered' => 'Enregistré', 'failed' => 'Échec'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= (($employee['registration_status'] ?? '') === $k) ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-end">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium">Enregistrer</button>
        </div>
    </form>
</div>
