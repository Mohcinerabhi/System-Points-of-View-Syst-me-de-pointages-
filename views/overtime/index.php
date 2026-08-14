<?php
/**
 * Overtime requests list
 */
$overtimes  = $overtimes ?? [];
$employees  = $employees ?? [];
$filters    = $filters ?? ['status' => '', 'employee_id' => '', 'search' => ''];
$pagination = $pagination ?? ['page' => 1, 'total_pages' => 1];
$csrf_token = $csrf_token ?? '';

function otStatusBadge($s) {
    $m = [
        'pending'  => ['En attente','bg-yellow-100 text-yellow-800'],
        'approved' => ['Approuvé','bg-green-100 text-green-800'],
        'rejected' => ['Rejeté','bg-red-100 text-red-800'],
    ];
    $v = $m[$s] ?? ['-','bg-gray-100 text-gray-800'];
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium '.$v[1].'">'.$v[0].'</span>';
}
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h4 class="text-xl font-semibold text-gray-900"><i class="fas fa-clock text-orange-600 mr-2"></i>Heures supplémentaires</h4>
        <div class="text-gray-500 text-sm">Gestion et validation des heures supplémentaires</div>
    </div>
    <a href="index.php?controller=overtime&action=request" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium"><i class="fas fa-plus mr-1"></i>Nouvelle demande</a>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
    <div class="p-4">
        <form method="get" class="flex flex-wrap items-end gap-3" id="overtimeFilterForm">
            <input type="hidden" name="controller" value="overtime">
            <input type="hidden" name="action" value="index">
            <select name="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" style="min-width:160px">
                <option value="">Tous les statuts</option>
                <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>En attente</option>
                <option value="approved" <?= $filters['status'] === 'approved' ? 'selected' : '' ?>>Approuvé</option>
                <option value="rejected" <?= $filters['status'] === 'rejected' ? 'selected' : '' ?>>Rejeté</option>
            </select>
            <select name="employee_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" style="min-width:200px">
                <option value="">Tous les employés</option>
                <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $filters['employee_id'] == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['first_name'].' '.$e['last_name'].' ('.$e['employee_code'].')') ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" placeholder="Rechercher..." class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" style="min-width:200px" value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
            <button type="button" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium" id="btnApplyOvertimeFilters"><i class="fas fa-filter mr-1"></i>Filtrer</button>
            <a href="index.php?controller=overtime&action=index" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 shadow-sm font-medium">Réinitialiser</a>
        </form>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-0 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200" id="overtimeTable">
            <thead>
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Employé</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Début</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Fin</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Heures estimées</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Statut</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            <?php if (empty($overtimes)): ?>
                <tr><td colspan="7" class="px-6 py-4 text-center text-gray-500">Aucune demande d'heures supplémentaires</td></tr>
            <?php else: foreach ($overtimes as $o): ?>
                <tr>
                    <td class="px-6 py-4"><?= htmlspecialchars(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')) ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($o['requested_date'] ?? '') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($o['start_time'] ?? '-') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($o['end_time'] ?? '-') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($o['estimated_hours'] ?? '0') ?> h</td>
                    <td class="px-6 py-4"><?= otStatusBadge($o['status'] ?? '') ?></td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-1 flex-wrap">
                            <?php if (($o['status'] ?? '') === 'pending'): ?>
                                <form method="post" action="index.php?controller=overtime&action=approve" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="id" value="<?= $o['id'] ?>">
                                    <button type="submit" class="p-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm" title="Approuver"><i class="fas fa-check"></i></button>
                                </form>
                                <form method="post" action="index.php?controller=overtime&action=reject" class="inline" onsubmit="return confirm('Rejeter cette demande ?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="id" value="<?= $o['id'] ?>">
                                    <input type="text" name="rejection_reason" placeholder="Motif" class="text-xs rounded border-gray-300 p-1 w-24 mb-1" required>
                                    <button type="submit" class="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm" title="Rejeter"><i class="fas fa-times"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const applyFilters = function () {
        const params = new URLSearchParams();
        document.querySelectorAll('#overtimeFilterForm select, #overtimeFilterForm input[name="search"]').forEach(function (el) {
            if (el.value) params.set(el.name, el.value);
        });
        window.location.href = 'index.php?controller=overtime&action=index&' + params.toString();
    };
    const btn = document.getElementById('btnApplyOvertimeFilters');
    if (btn) btn.addEventListener('click', applyFilters);
    document.querySelectorAll('#overtimeFilterForm select').forEach(function (sel) {
        sel.addEventListener('change', applyFilters);
    });
});
</script>
