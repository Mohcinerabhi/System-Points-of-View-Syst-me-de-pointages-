<?php
/**
 * Leave requests list
 */
$leaves     = $leaves     ?? [];
$employees  = $employees  ?? [];
$filters    = $filters    ?? ['status' => '', 'employee_id' => '', 'search' => ''];
$pagination = $pagination ?? ['page' => 1, 'total_pages' => 1];
$csrf_token = $csrf_token ?? '';

function leaveStatusBadge($s) {
    $m = [
        'pending'   => ['En attente','bg-yellow-100 text-yellow-800'],
        'approved'  => ['Approuvé','bg-green-100 text-green-800'],
        'rejected'  => ['Rejeté','bg-red-100 text-red-800'],
        'cancelled' => ['Annulé','bg-gray-100 text-gray-800'],
    ];
    $v = $m[$s] ?? ['-','bg-gray-100 text-gray-800'];
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium '.$v[1].'">'.$v[0].'</span>';
}
function leaveTypeLabel($t) {
    $m = [
        'vacation'  => 'Congés payés',
        'sick'      => 'Maladie',
        'personal'  => 'Personnel',
        'maternity' => 'Maternité',
        'paternity' => 'Paternité',
        'other'     => 'Autre',
    ];
    return $m[$t] ?? $t;
}
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h4 class="text-xl font-semibold text-gray-900"><i class="fas fa-calendar-check text-blue-600 mr-2"></i>Congés</h4>
        <div class="text-gray-500 text-sm">Gestion des demandes de congé</div>
    </div>
    <a href="index.php?controller=leaves&action=create" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium"><i class="fas fa-plus mr-1"></i>Nouvelle demande</a>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
    <div class="p-4">
        <form method="get" class="flex flex-wrap items-end gap-3" id="leaveFilterForm">
            <input type="hidden" name="controller" value="leaves">
            <input type="hidden" name="action" value="index">
            <select name="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" style="min-width:160px">
                <option value="">Tous les statuts</option>
                <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>En attente</option>
                <option value="approved" <?= $filters['status'] === 'approved' ? 'selected' : '' ?>>Approuvé</option>
                <option value="rejected" <?= $filters['status'] === 'rejected' ? 'selected' : '' ?>>Rejeté</option>
                <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>Annulé</option>
            </select>
            <select name="employee_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" style="min-width:200px">
                <option value="">Tous les employés</option>
                <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $filters['employee_id'] == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['first_name'].' '.$e['last_name'].' ('.$e['employee_code'].')') ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" placeholder="Rechercher..." class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" style="min-width:200px" value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
            <button type="button" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium" id="btnApplyLeaveFilters"><i class="fas fa-filter mr-1"></i>Filtrer</button>
            <a href="index.php?controller=leaves&action=index" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 shadow-sm font-medium">Réinitialiser</a>
        </form>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-0 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200" id="leaveTable">
            <thead>
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Employé</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Début</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Fin</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Durée</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Statut</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            <?php if (empty($leaves)): ?>
                <tr><td colspan="7" class="px-6 py-4 text-center text-gray-500">Aucune demande de congé</td></tr>
            <?php else: foreach ($leaves as $l): ?>
                <tr>
                    <td class="px-6 py-4"><?= htmlspecialchars(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')) ?></td>
                    <td class="px-6 py-4"><?= leaveTypeLabel($l['leave_type'] ?? 'other') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($l['start_date'] ?? '') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($l['end_date'] ?? '') ?></td>
                    <td class="px-6 py-4">
                        <?php
                        $start = strtotime($l['start_date'] ?? '');
                        $end = strtotime($l['end_date'] ?? '');
                        $days = ($start && $end && $end >= $start) ? (int) floor(($end - $start) / 86400) + 1 : 0;
                        echo $days . ' jour(s)';
                        ?>
                    </td>
                    <td class="px-6 py-4"><?= leaveStatusBadge($l['status'] ?? '') ?></td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-1 flex-wrap">
                            <a href="index.php?controller=leaves&action=view&id=<?= $l['id'] ?>" class="p-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm" title="Voir"><i class="fas fa-eye"></i></a>
                            <?php if (($l['status'] ?? '') === 'pending'): ?>
                                <form method="post" action="index.php?controller=leaves&action=approve" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                    <button type="submit" class="p-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm" title="Approuver"><i class="fas fa-check"></i></button>
                                </form>
                                <form method="post" action="index.php?controller=leaves&action=reject" class="inline" onsubmit="return confirm('Rejeter cette demande ? Motif requis.')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                    <input type="text" name="rejection_reason" placeholder="Motif" class="text-xs rounded border-gray-300 p-1 w-24 mb-1" required>
                                    <button type="submit" class="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm" title="Rejeter"><i class="fas fa-times"></i></button>
                                </form>
                            <?php endif; ?>
                            <?php if (in_array($l['status'] ?? '', ['pending', 'approved'])): ?>
                                <form method="post" action="index.php?controller=leaves&action=cancel" class="inline" onsubmit="return confirm('Annuler cette demande ?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                    <button type="submit" class="p-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 text-sm" title="Annuler"><i class="fas fa-ban"></i></button>
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
        document.querySelectorAll('#leaveFilterForm select, #leaveFilterForm input[name="search"]').forEach(function (el) {
            if (el.value) params.set(el.name, el.value);
        });
        window.location.href = 'index.php?controller=leaves&action=index&' + params.toString();
    };
    const btn = document.getElementById('btnApplyLeaveFilters');
    if (btn) btn.addEventListener('click', applyFilters);
    document.querySelectorAll('#leaveFilterForm select').forEach(function (sel) {
        sel.addEventListener('change', applyFilters);
    });
});
</script>
