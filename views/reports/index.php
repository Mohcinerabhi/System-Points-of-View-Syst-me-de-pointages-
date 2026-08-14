<?php
/**
 * Reports dashboard
 */
$departments = $departments ?? [];
$employees   = $employees   ?? [];
$filters     = $filters     ?? ['from' => '', 'to' => '', 'department_id' => '', 'employee_id' => '', 'type' => 'monthly'];
$results     = $results     ?? [];
$reportType  = $reportType  ?? 'monthly';
$csrf_token  = $csrf_token  ?? '';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h4 class="text-xl font-semibold text-gray-900"><i class="fas fa-chart-bar text-blue-600 mr-2"></i>Rapports</h4>
        <div class="text-gray-500 text-sm">Analysez la présence et les heures travaillées</div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 shadow-sm font-medium" onclick="window.print()"><i class="fas fa-print mr-1"></i>Imprimer</button>
        <button type="button" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 shadow-sm font-medium btn-export" data-format="csv"><i class="fas fa-file-csv mr-1"></i>CSV</button>
        <button type="button" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium btn-export" data-format="excel"><i class="fas fa-file-excel mr-1"></i>Excel</button>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
    <div class="px-6 py-4 border-b border-gray-200"><h5 class="font-semibold text-gray-900"><i class="fas fa-sliders-h mr-2"></i>Filtres du rapport</h5></div>
    <div class="p-6">
        <form method="get" action="index.php" id="reportFiltersForm" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="controller" value="reports">
            <input type="hidden" name="action" value="index">
            <select name="type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="daily" <?= $reportType === 'daily' ? 'selected' : '' ?>>Quotidien</option>
                <option value="weekly" <?= $reportType === 'weekly' ? 'selected' : '' ?>>Hebdomadaire</option>
                <option value="monthly" <?= $reportType === 'monthly' ? 'selected' : '' ?>>Mensuel</option>
                <option value="custom" <?= $reportType === 'custom' ? 'selected' : '' ?>>Personnalisé</option>
                <option value="department" <?= $reportType === 'department' ? 'selected' : '' ?>>Par département</option>
                <option value="employee" <?= $reportType === 'employee' ? 'selected' : '' ?>>Par employé</option>
            </select>
            <div class="flex" style="min-width:200px">
                <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500">
                    <i class="fas fa-calendar text-gray-400"></i>
                </span>
                <input type="date" name="from" class="flex-1 rounded-r-md border-gray-300 border shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($filters['from']) ?>" placeholder="Du">
            </div>
            <div class="flex" style="min-width:200px">
                <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500">
                    <i class="fas fa-calendar text-gray-400"></i>
                </span>
                <input type="date" name="to" class="flex-1 rounded-r-md border-gray-300 border shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($filters['to']) ?>" placeholder="Au">
            </div>
            <select name="department_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Tous départements</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $filters['department_id'] == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="employee_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Tous employés</option>
                <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $filters['employee_id'] == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['first_name'].' '.$e['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium"><i class="fas fa-chart-bar mr-1"></i>Générer</button>
        </form>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <h5 class="font-semibold text-gray-900"><i class="fas fa-table mr-2"></i>Résultats</h5>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"><?= count($results) ?> ligne(s)</span>
    </div>
    <div class="p-0 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Employé</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Département</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">J. travaillés</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Présence</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Absence</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Retards</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Dép. anticipés</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">H. travaillées</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">H. sup.</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">H. manquantes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            <?php if (!empty($filters['from']) && !empty($filters['to'])): ?>
                <?php if (empty($results)): ?>
                    <tr><td colspan="10" class="px-6 py-4 text-center text-gray-500">Aucune donnée pour les critères sélectionnés</td></tr>
                <?php else: foreach ($results as $r): ?>
                <tr class="hover:bg-blue-50 transition-colors">
                    <td class="px-6 py-4"><strong><?= htmlspecialchars(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?></strong></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($r['department_name'] ?? '-') ?></td>
                    <td class="px-6 py-4"><?= (int)($r['worked_days'] ?? 0) ?></td>
                    <td class="px-6 py-4"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"><?= (int)($r['presence'] ?? 0) ?></span></td>
                    <td class="px-6 py-4"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800"><?= (int)($r['absence'] ?? 0) ?></span></td>
                    <td class="px-6 py-4"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"><?= (int)($r['late'] ?? 0) ?></span></td>
                    <td class="px-6 py-4"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800"><?= (int)($r['early_departure'] ?? 0) ?></span></td>
                    <td class="px-6 py-4"><?= number_format((float)($r['hours_worked'] ?? 0), 1) ?> h</td>
                    <td class="px-6 py-4"><?= number_format((float)($r['overtime'] ?? 0), 1) ?> h</td>
                    <td class="px-6 py-4"><?= number_format((float)($r['missing_hours'] ?? 0), 1) ?> h</td>
                </tr>
            <?php endforeach; endif; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('#reportFiltersForm');
    if (!form) return;

    const getParams = function () {
        const params = new URLSearchParams();
        form.querySelectorAll('select, input:not([type="hidden"])').forEach(function (el) {
            if (el.name && el.value) params.set(el.name, el.value);
        });
        return params.toString();
    };

    const base = form.getAttribute('action') || window.location.href.split('?')[0];

    document.querySelectorAll('.btn-export').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const format = this.getAttribute('data-format');
            const url = base + '?controller=reports&action=export&format=' + format + '&' + getParams();
            window.location.href = url;
        });
    });
});
</script>
