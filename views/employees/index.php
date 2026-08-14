<?php
/**
 * Employees list
 */
$employees   = $employees   ?? [];
$departments = $departments ?? [];
$filters     = $filters     ?? ['search' => '', 'department_id' => '', 'status' => '', 'registration_status' => ''];
$pagination  = $pagination  ?? ['page' => 1, 'total_pages' => 1];
$total       = $total       ?? count($employees);
$csrf_token  = $csrf_token  ?? '';

function empStatusBadge($s) {
    return $s === 'active'
        ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Actif</span>'
        : '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Inactif</span>';
}
function regBadge($s) {
    $m = ['pending' => ['En attente','bg-yellow-100 text-yellow-800'], 'registered' => ['Enregistré','bg-green-100 text-green-800'], 'error' => ['Erreur','bg-red-100 text-red-800']];
    $v = $m[$s] ?? ['-','bg-gray-100 text-gray-800'];
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium '.$v[1].'">'.$v[0].'</span>';
}
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h4 class="text-xl font-semibold text-gray-900"><i class="fas fa-users text-blue-600 mr-2"></i>Employés</h4>
        <div class="text-gray-500 text-sm" id="empCount"><?= (int)$total ?> employé(s) au total</div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="index.php?controller=employees&action=import" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 shadow-sm font-medium"><i class="fas fa-file-import mr-1"></i>Importer</a>
        <a href="index.php?controller=reports&action=export&format=csv" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 shadow-sm font-medium"><i class="fas fa-file-export mr-1"></i>Exporter</a>
        <a href="index.php?controller=employees&action=create" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium"><i class="fas fa-plus mr-1"></i>Ajouter un employé</a>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
    <div class="p-4">
        <form method="get" class="flex flex-wrap items-end gap-3" id="empFilterForm">
            <div class="flex" style="min-width:260px">
                <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500">
                    <i class="fas fa-search text-gray-400"></i>
                </span>
                <input type="text" id="empSearch" class="flex-1 rounded-r-md border-gray-300 border shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Recherche instantanée..."
                       data-search-table="empTable" data-search-cols="0,1,2,3,4"
                       value="<?= htmlspecialchars($filters['search']) ?>">
            </div>
            <select name="department_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" style="min-width:180px">
                <option value="">Tous les départements</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $filters['department_id'] == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" style="min-width:150px">
                <option value="">Tous les statuts</option>
                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Actif</option>
                <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactif</option>
            </select>
            <select name="registration_status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" style="min-width:180px">
                <option value="">Enregistrement terminal</option>
                <option value="pending" <?= $filters['registration_status'] === 'pending' ? 'selected' : '' ?>>En attente</option>
                <option value="registered" <?= $filters['registration_status'] === 'registered' ? 'selected' : '' ?>>Enregistré</option>
                <option value="error" <?= $filters['registration_status'] === 'error' ? 'selected' : '' ?>>Erreur</option>
            </select>
            <button type="button" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium" id="btnApplyFilters"><i class="fas fa-filter mr-1"></i>Filtrer</button>
            <a href="index.php?controller=employees&action=index" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 shadow-sm font-medium">Réinitialiser</a>
        </form>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-0 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200" id="empTable" data-url="index.php?controller=employees&action=ajaxList">
            <thead>
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Photo</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Matricule</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Nom</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Département</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Badge</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Enregistrement</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Statut</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            <?php if (empty($employees)): ?>
                <tr><td colspan="8" class="px-6 py-4 text-center text-gray-500">Aucun employé trouvé</td></tr>
            <?php else: foreach ($employees as $e): ?>
                <tr data-employee-id="<?= $e['id'] ?>">
                     <td class="px-6 py-4"><?= \App\Models\Employee::photoTag($e['photo'] ?? null, 'w-9 h-9 rounded-full object-cover') ?></td>
                    <td class="px-6 py-4"><strong><?= htmlspecialchars($e['employee_code']) ?></strong></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($e['department_name'] ?? '-') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($e['badge_id'] ?? '-') ?></td>
                    <td class="px-6 py-4"><?= regBadge($e['registration_status'] ?? '') ?></td>
                    <td class="px-6 py-4"><?= empStatusBadge($e['status'] ?? '') ?></td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-1">
                            <a href="index.php?controller=employees&action=edit&id=<?= $e['id'] ?>" class="p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm" title="Modifier"><i class="fas fa-edit"></i></a>
                            <button class="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm" title="Supprimer" data-delete-employee="<?= $e['id'] ?>" id="btnDeleteEmp<?= $e['id'] ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="px-6 py-3 border-t border-gray-200 flex items-center justify-between flex-wrap gap-3">
        <span class="text-gray-500 text-sm">Page <?= (int)($pagination['current'] ?? 1) ?> / <?= max(1, (int)($pagination['last'] ?? 1)) ?></span>
        <nav class="flex items-center gap-1">
            <?php for ($p = 1; $p <= max(1, (int)($pagination['last'] ?? 1)); $p++): ?>
                <a href="?page=<?= $p ?>" class="inline-flex items-center justify-center min-w-[34px] h-9 px-2 border border-gray-200 rounded-lg text-sm <?= $p == ($pagination['current'] ?? 1) ? 'bg-blue-600 text-white border-blue-600' : 'text-gray-700 hover:bg-gray-50' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </nav>
    </div>
</div>

<!-- Delete Modal -->
<div class="fixed inset-0 z-50 hidden" id="deleteModal">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" id="deleteModalOverlay"></div>
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 w-full max-w-sm relative z-10">
            <div class="px-6 py-4 border-b border-gray-200">
                <h5 class="text-lg font-semibold text-red-600"><i class="fas fa-exclamation-triangle mr-2"></i>Confirmation</h5>
                <button type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-2xl leading-none" id="btnCloseDeleteModal">&times;</button>
            </div>
            <div class="p-6">
                <p>Êtes-vous sûr de vouloir supprimer cet employé ?</p>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-end gap-3">
                <button type="button" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50" id="btnCancelDelete">Annuler</button>
                <form id="deleteForm" method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                    <input type="hidden" name="id" id="deleteEmployeeId" value="">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 shadow-sm font-medium"><i class="fas fa-trash mr-1"></i>Supprimer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const deleteModal = document.getElementById('deleteModal');
    const deleteForm = document.getElementById('deleteForm');
    const deleteEmployeeId = document.getElementById('deleteEmployeeId');

    document.querySelectorAll('[data-delete-employee]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-delete-employee');
            deleteForm.action = 'index.php?controller=employees&action=delete&id=' + id;
            deleteEmployeeId.value = id;
            deleteModal.classList.remove('hidden');
        });
    });

    document.getElementById('btnCloseDeleteModal').addEventListener('click', function() { deleteModal.classList.add('hidden'); });
    document.getElementById('deleteModalOverlay').addEventListener('click', function() { deleteModal.classList.add('hidden'); });
    document.getElementById('btnCancelDelete').addEventListener('click', function() { deleteModal.classList.add('hidden'); });

    const applyFilters = function () {
        const search = document.getElementById('empSearch').value;
        const dept = document.querySelector('select[name="department_id"]').value;
        const status = document.querySelector('select[name="status"]').value;
        const regStatus = document.querySelector('select[name="registration_status"]').value;

        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (dept) params.set('department_id', dept);
        if (status) params.set('status', status);
        if (regStatus) params.set('registration_status', regStatus);

        const url = 'index.php?controller=employees&action=ajaxList&' + params.toString();

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data) {
                    const tbody = document.querySelector('#empTable tbody');
                    if (tbody && data.data.length) {
                        let html = '';
                        data.data.forEach(emp => {
                            html += '<tr data-employee-id="' + emp.id + '">' +
                                '<td class="px-6 py-4">' + emp.photo + '</td>' +
                                '<td class="px-6 py-4"><strong>' + emp.code + '</strong></td>' +
                                '<td class="px-6 py-4">' + emp.name + '</td>' +
                                '<td class="px-6 py-4">' + emp.department + '</td>' +
                                '<td class="px-6 py-4">' + emp.badge + '</td>' +
                                '<td class="px-6 py-4">' + emp.registration + '</td>' +
                                '<td class="px-6 py-4">' + emp.status + '</td>' +
                                '<td class="px-6 py-4"><div class="flex items-center gap-1">' + emp.actions + '</div></td>' +
                                '</tr>';
                        });
                        tbody.innerHTML = html;
                        document.getElementById('empCount').textContent = data.recordsFiltered + ' employé(s) trouvé(s)';
                    } else if (tbody) {
                        tbody.innerHTML = '<tr><td colspan="8" class="px-6 py-4 text-center text-gray-500">Aucun employé trouvé</td></tr>';
                        document.getElementById('empCount').textContent = '0 employé trouvé';
                    }
                }
            })
            .catch(() => {
                if (window.App && App.toast) App.toast('Erreur lors du filtrage', 'error');
            });
    };

    const btnApply = document.getElementById('btnApplyFilters');
    if (btnApply) {
        btnApply.addEventListener('click', applyFilters);
    }

    document.querySelectorAll('select[name="department_id"], select[name="status"], select[name="registration_status"]').forEach(function (sel) {
        sel.addEventListener('change', applyFilters);
    });

    let searchTimer;
    document.getElementById('empSearch').addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applyFilters, 300);
    });
});
</script>
