<?php
/**
 * Fragment partagé : tableau de rapport agrégé par employé.
 */
$rows = $rows ?? [];
?>
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-0 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Matricule</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Nom</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Département</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Jours présents</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Retards (min)</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Départs anticipés (min)</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Heures sup. (min)</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Travail (min)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            <?php if (empty($rows)): ?>
                <tr><td colspan="8" class="px-6 py-4">Aucune donnée.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr class="hover:bg-blue-50 transition-colors">
                    <td class="px-6 py-4"><?= htmlspecialchars($r['employee_code'] ?? '') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($r['name'] ?? '') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($r['department'] ?? '') ?></td>
                    <td class="px-6 py-4"><?= (int)($r['days_present'] ?? 0) ?></td>
                    <td class="px-6 py-4"><?= (int)($r['total_late'] ?? 0) ?></td>
                    <td class="px-6 py-4"><?= (int)($r['total_early'] ?? 0) ?></td>
                    <td class="px-6 py-4"><?= (int)($r['total_overtime'] ?? 0) ?></td>
                    <td class="px-6 py-4"><?= (int)($r['total_work'] ?? 0) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
