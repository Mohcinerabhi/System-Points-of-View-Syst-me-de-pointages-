<?php
/**
 * Vue : création d'un département.
 */
$department = $department ?? [];
$errors     = $errors     ?? [];
$schedules  = $schedules  ?? [];
$managers   = $managers   ?? [];
$csrf_token = $csrf_token ?? '';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h4 class="text-xl font-semibold text-gray-900">Nouveau département</h4>
    <a href="index.php?controller=departments&action=index" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Retour</a>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <form method="post" action="index.php?controller=departments&action=create">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

        <div class="p-6">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-600">*</span></label>
                <input type="text" name="name" value="<?= htmlspecialchars($department['name'] ?? '') ?>" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <?php if (!empty($errors['name'])): ?><span class="text-red-600 text-sm mt-1 block"><?= htmlspecialchars($errors['name']) ?></span><?php endif; ?>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?= htmlspecialchars($department['description'] ?? '') ?></textarea>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Horaire</label>
                <select name="schedule_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">-- Aucun --</option>
                    <?php foreach ($schedules as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= ((int)($department['schedule_id'] ?? 0) === (int)$s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Responsable</label>
                <select name="manager_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">-- Aucun --</option>
                    <?php foreach ($managers as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" <?= ((int)($department['manager_id'] ?? 0) === (int)$m['id']) ? 'selected' : '' ?>><?= htmlspecialchars(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                <select name="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="active" <?= (($department['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Actif</option>
                    <option value="inactive" <?= (($department['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactif</option>
                </select>
            </div>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-end">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium">Enregistrer</button>
        </div>
    </form>
</div>
