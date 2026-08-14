<?php
/**
 * Shifts list
 */
$shifts = $shifts ?? [];
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h4 class="text-xl font-semibold text-gray-900"><i class="fas fa-sun text-yellow-600 mr-2"></i>Shifts</h4>
        <div class="text-gray-500 text-sm">Gestion des équipes et shifts</div>
    </div>
    <a href="index.php?controller=shifts&action=assignments" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium"><i class="fas fa-user-clock mr-1"></i>Affectations</a>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-0 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Nom</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Code</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Début</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Fin</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            <?php if (empty($shifts)): ?>
                <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">Aucun shift configuré</td></tr>
            <?php else: foreach ($shifts as $s): ?>
                <tr>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full" style="background-color: <?= htmlspecialchars($s['color'] ?? '#3b5bdb') ?>"></span>
                            <span class="font-medium"><?= htmlspecialchars($s['name'] ?? '') ?></span>
                        </div>
                    </td>
                    <td class="px-6 py-4"><span class="uppercase font-mono text-sm"><?= htmlspecialchars($s['code'] ?? '') ?></span></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($s['start_time'] ?? '') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($s['end_time'] ?? '') ?></td>
                    <td class="px-6 py-4">
                        <?php if (!empty($s['is_active'])): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Actif</span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Inactif</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
