<?php
/**
 * Vue : détail d'un employé.
 */
$employee   = $employee   ?? [];
$department = $department ?? null;
$history    = $history    ?? [];
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h4 class="text-xl font-semibold text-gray-900"><?= htmlspecialchars(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')) ?></h4>
    <div class="flex items-center gap-2">
        <a href="index.php?controller=employees&action=edit&id=<?= (int)($employee['id'] ?? 0) ?>" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Modifier</a>
        <a href="index.php?controller=employees&action=index" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Retour</a>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-0 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 w-1/4">Matricule</th><td class="px-6 py-4"><?= htmlspecialchars($employee['employee_code'] ?? '') ?></td></tr>
            <tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Téléphone</th><td class="px-6 py-4"><?= htmlspecialchars($employee['phone'] ?? '') ?></td></tr>
            <tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Département</th><td class="px-6 py-4"><?= htmlspecialchars($department['name'] ?? '-') ?></td></tr>
            <tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Date d'embauche</th><td class="px-6 py-4"><?= htmlspecialchars($employee['hire_date'] ?? '') ?></td></tr>
            <tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Statut</th><td class="px-6 py-4"><?= htmlspecialchars($employee['status'] ?? '') ?></td></tr>
        </table>
    </div>
</div>

<h5 class="text-lg font-semibold text-gray-900 mt-6 mb-4">Historique de pointage</h5>
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-0 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Date</th><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Heure</th><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Type</th><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Source</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            <?php if (empty($history)): ?>
                <tr><td colspan="4" class="px-6 py-4">Aucun pointage.</td></tr>
            <?php else: foreach ($history as $h): ?>
                <tr class="hover:bg-blue-50 transition-colors">
                    <td class="px-6 py-4"><?= htmlspecialchars($h['attendance_date'] ?? '') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($h['attendance_time'] ?? '') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($h['type'] ?? '') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($h['source'] ?? '') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
