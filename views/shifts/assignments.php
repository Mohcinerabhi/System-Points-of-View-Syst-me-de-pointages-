<?php
/**
 * Shift assignments
 */
$shifts      = $shifts ?? [];
$employees   = $employees ?? [];
$assignments = $assignments ?? [];
$errors      = $errors ?? [];
$csrf_token  = $csrf_token ?? '';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h4 class="text-xl font-semibold text-gray-900"><i class="fas fa-user-clock text-blue-600 mr-2"></i>Affectations de shifts</h4>
        <div class="text-gray-500 text-sm">Assigner des shifts aux employés avec période</div>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
    <div class="p-6">
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Shift</label>
                    <select name="shift_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <option value="">Sélectionner</option>
                        <?php foreach ($shifts as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name'] . ' (' . $s['code'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employé</label>
                    <select name="employee_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <option value="">Sélectionner</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name'] . ' (' . $e['employee_code'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex gap-2">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Début</label>
                        <input type="date" name="start_date" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fin (optionnel)</label>
                        <input type="date" name="end_date" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>
                <div>
                    <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium"><i class="fas fa-plus mr-1"></i>Affecter</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-0 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Shift</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Employé</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Début</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Fin</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            <?php if (empty($assignments)): ?>
                <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">Aucune affectation</td></tr>
            <?php else: foreach ($assignments as $a): ?>
                <tr>
                    <td class="px-6 py-4"><?= htmlspecialchars($a['shift_name'] ?? $a['shift_code'] ?? '-') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($a['start_date'] ?? '') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($a['end_date'] ?? 'Permanent') ?></td>
                    <td class="px-6 py-4">
                        <form method="post" action="index.php?controller=shifts&action=deleteAssignment" class="inline" onsubmit="return confirm('Supprimer cette affectation ?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button type="submit" class="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm" title="Supprimer"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
