<?php
/**
 * Vue : pointages du jour.
 */
$date  = $date  ?? date('Y-m-d');
$stats = $stats ?? [];
$logs  = $logs  ?? [];
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h4 class="text-xl font-semibold text-gray-900">Pointages du jour &mdash; <?= htmlspecialchars($date) ?></h4>
    <a href="index.php?controller=attendance&action=manualEntry" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Saisie manuelle</a>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
    <div class="p-6">
        <ul class="flex flex-wrap items-center gap-6 text-sm">
            <li>Présents : <strong><?= (int)($stats['present_today'] ?? $stats['present'] ?? 0) ?></strong></li>
            <li>Retards : <strong><?= (int)($stats['late'] ?? 0) ?></strong></li>
            <li>Départs anticipés : <strong><?= (int)($stats['early_departures'] ?? 0) ?></strong></li>
        </ul>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-0 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Employé</th><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Date</th><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Heure</th><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Type</th><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Source</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            <?php if (empty($logs)): ?>
                <tr><td colspan="5" class="px-6 py-4">Aucun pointage.</td></tr>
            <?php else: foreach ($logs as $log): ?>
                <tr class="hover:bg-blue-50 transition-colors">
                    <td class="px-6 py-4"><?= htmlspecialchars(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($log['attendance_date'] ?? '') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($log['attendance_time'] ?? '') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($log['type'] ?? '') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($log['source'] ?? '') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
