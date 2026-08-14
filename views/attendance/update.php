<?php
/**
 * Vue : modification d'un pointage.
 */
$log       = $log       ?? [];
$employees = $employees ?? [];
$errors    = $errors    ?? [];
$types = ['check_in' => 'Entrée', 'check_out' => 'Sortie', 'break_start' => 'Début pause', 'break_end' => 'Fin pause'];
$csrf_token = $csrf_token ?? '';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h4 class="text-xl font-semibold text-gray-900">Modifier un pointage</h4>
    <a href="index.php?controller=attendance&action=index" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Retour</a>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <form method="post" action="index.php?controller=attendance&action=update&id=<?= (int)($log['id'] ?? 0) ?>">
        <div class="p-6">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-600">*</span></label>
                <input type="date" name="attendance_date" value="<?= htmlspecialchars($log['attendance_date'] ?? '') ?>" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Heure <span class="text-red-600">*</span></label>
                <input type="time" step="1" name="attendance_time" value="<?= htmlspecialchars($log['attendance_time'] ?? '') ?>" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-600">*</span></label>
                <select name="type" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <?php foreach ($types as $k => $v): ?>
                        <option value="<?= $k ?>" <?= (($log['type'] ?? '') === $k) ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?= htmlspecialchars($log['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-end">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium">Enregistrer</button>
        </div>
    </form>
</div>
