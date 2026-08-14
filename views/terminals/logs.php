<?php
/**
 * Vue : journaux de synchronisation des terminaux.
 */
$logs      = $logs      ?? [];
$terminal  = $terminal  ?? null;
$terminals = $terminals ?? [];
$csrf_token = $csrf_token ?? '';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h4 class="text-xl font-semibold text-gray-900">Journaux de synchronisation<?= $terminal ? ' &mdash; ' . htmlspecialchars($terminal['name']) : '' ?></h4>
    <a href="index.php?controller=terminals&action=index" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Retour</a>
</div>

<form method="get" action="index.php" class="flex flex-wrap items-end gap-3 mb-4">
    <input type="hidden" name="controller" value="terminals">
    <input type="hidden" name="action" value="logs">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Terminal</label>
        <select name="id" onchange="this.form.submit()" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">-- Tous --</option>
            <?php foreach ($terminals as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= ($terminal && (int)$terminal['id'] === (int)$t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-0 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Type</th><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Statut</th><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Message</th><th class="px-6 py-3 text-left text-xs font-semibold text-gray-50">Début</th><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Fin</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            <?php if (empty($logs)): ?>
                <tr><td colspan="5" class="px-6 py-4">Aucun journal.</td></tr>
            <?php else: foreach ($logs as $l): ?>
                <tr class="hover:bg-blue-50 transition-colors">
                    <td class="px-6 py-4"><?= htmlspecialchars($l['sync_type'] ?? $l['type'] ?? '') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($l['status'] ?? '') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($l['message'] ?? '') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($l['started_at'] ?? '') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($l['finished_at'] ?? $l['completed_at'] ?? '') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
