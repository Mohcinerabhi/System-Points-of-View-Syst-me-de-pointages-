<?php
/**
 * Vue : affectation d'horaires aux employés.
 */
$schedules   = $schedules   ?? [];
$employees   = $employees   ?? [];
$assignments = $assignments ?? [];
$csrf_token = $csrf_token ?? '';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h4 class="text-xl font-semibold text-gray-900">Affectations d'horaires</h4>
    <a href="index.php?controller=schedules&action=index" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Retour</a>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <form method="post" action="index.php?controller=schedules&action=assignments">
        <div class="p-6">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Horaire</label>
                <select name="schedule_id" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">-- Choisir --</option>
                    <?php foreach ($schedules as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Employé</label>
                <select name="employee_id" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">-- Choisir --</option>
                    <?php foreach ($employees as $e): ?>
                        <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Date de début</label>
                <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Date de fin</label>
                <input type="date" name="end_date" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
            </div>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-end">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium">Affecter</button>
        </div>
    </form>
</div>

<h5 class="text-lg font-semibold text-gray-900 mt-6 mb-4">Affectations existantes</h5>
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-0 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Employé</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Horaire</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Début</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Fin</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            <?php if (empty($assignments)): ?>
                <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">Aucune affectation.</td></tr>
            <?php else: foreach ($assignments as $a): ?>
                <tr class="hover:bg-blue-50 transition-colors">
                    <td class="px-6 py-4"><?= htmlspecialchars(($a['first_name'] ?? '-') . ' ' . ($a['last_name'] ?? '-')) ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($a['schedule_name'] ?? '-') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($a['start_date'] ?? '') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($a['end_date'] ?? 'Permanent') ?></td>
                    <td class="px-6 py-4">
                        <form method="post" action="index.php?controller=schedules&action=deleteAssignment" class="inline" onsubmit="return confirm('Supprimer cette affectation ?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                            <input type="hidden" name="id" value="<?= (int)($a['id'] ?? 0) ?>">
                            <button type="submit" class="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm" title="Supprimer"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
