<?php
/**
 * Vue : création d'un horaire de travail.
 */
$schedule = $schedule ?? [];
$errors   = $errors   ?? [];
$days = [
    'monday' => 'Lundi', 'tuesday' => 'Mardi', 'wednesday' => 'Mercredi',
    'thursday' => 'Jeudi', 'friday' => 'Vendredi', 'saturday' => 'Samedi', 'sunday' => 'Dimanche',
];
$csrf_token = $csrf_token ?? '';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h4 class="text-xl font-semibold text-gray-900">Nouvel horaire</h4>
    <a href="index.php?controller=schedules&action=index" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Retour</a>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <form method="post" action="index.php?controller=schedules&action=create">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

        <div class="p-6">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-600">*</span></label>
                <input type="text" name="name" value="<?= htmlspecialchars($schedule['name'] ?? '') ?>" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <?php if (!empty($errors['name'])): ?><span class="text-red-600 text-sm mt-1 block"><?= htmlspecialchars($errors['name']) ?></span><?php endif; ?>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?= htmlspecialchars($schedule['description'] ?? '') ?></textarea>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-600">*</span></label>
                <select name="type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="fixed" <?= (($schedule['type'] ?? 'fixed') === 'fixed') ? 'selected' : '' ?>>Fixe</option>
                    <option value="flexible" <?= (($schedule['type'] ?? '') === 'flexible') ? 'selected' : '' ?>>Flexible</option>
                </select>
                <?php if (!empty($errors['type'])): ?><span class="text-red-600 text-sm mt-1 block"><?= htmlspecialchars($errors['type']) ?></span><?php endif; ?>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tolérance de retard (min)</label>
                <input type="number" name="late_tolerance_minutes" value="<?= htmlspecialchars($schedule['late_tolerance_minutes'] ?? 15) ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tolérance de départ anticipé (min)</label>
                <input type="number" name="early_departure_tolerance_minutes" value="<?= htmlspecialchars($schedule['early_departure_tolerance_minutes'] ?? 10) ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Heures de travail requises</label>
                <input type="number" step="0.5" name="required_work_hours" value="<?= htmlspecialchars($schedule['required_work_hours'] ?? 8) ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <h5 class="text-sm font-semibold text-gray-900 mt-4 mb-2">Pause (optionnel)</h5>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Début pause</label>
                    <input type="time" name="break_start" value="<?= htmlspecialchars($schedule['break_start'] ?? '') ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fin pause</label>
                    <input type="time" name="break_end" value="<?= htmlspecialchars($schedule['break_end'] ?? '') ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>

            <div class="mb-4 flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" id="is_active" <?= !empty($schedule['is_active']) ? 'checked' : '' ?> class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <label for="is_active" class="text-sm font-medium text-gray-700">Actif</label>
            </div>

            <h5 class="text-lg font-semibold text-gray-900 mt-6 mb-3">Jours ouvrables et plages horaires</h5>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead><tr><th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 bg-gray-50">Jour</th><th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 bg-gray-50">Ouvré</th><th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 bg-gray-50">Début</th><th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 bg-gray-50">Fin</th></tr></thead>
                    <tbody class="divide-y divide-gray-200">
                    <?php foreach ($days as $key => $label): ?>
                        <tr>
                            <td class="px-4 py-2"><?= $label ?></td>
                            <td class="px-4 py-2"><input type="checkbox" name="working_day[]" value="<?= $key ?>" <?= (!empty($schedule[$key . '_start']) || !empty($schedule[$key . '_end'])) ? 'checked' : '' ?> class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"></td>
                            <td class="px-4 py-2"><input type="time" name="<?= $key ?>_start" value="<?= htmlspecialchars($schedule[$key . '_start'] ?? '') ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></td>
                            <td class="px-4 py-2"><input type="time" name="<?= $key ?>_end" value="<?= htmlspecialchars($schedule[$key . '_end'] ?? '') ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-end">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium">Enregistrer</button>
        </div>
    </form>
</div>
