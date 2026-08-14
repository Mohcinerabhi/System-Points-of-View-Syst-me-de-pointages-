<?php
/**
 * Departments management
 */
$departments    = $departments    ?? [];
$editDepartment = $editDepartment ?? null;
$csrf_token      = $csrf_token      ?? '';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h4 class="text-xl font-semibold text-gray-900"><i class="fas fa-sitemap text-blue-600 mr-2"></i>Départements</h4>
        <div class="text-gray-500 text-sm">Organisation de l'entreprise</div>
    </div>
    <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium" id="btnOpenDeptModal">
        <i class="fas fa-plus mr-1"></i>Nouveau département
    </button>
</div>

<?php if (empty($departments)): ?>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200"><div class="p-6 text-center text-gray-500 py-5">Aucun département</div></div>
<?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($departments as $d): ?>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-9 h-9 rounded-lg flex items-center justify-center bg-blue-100 text-blue-600"><i class="fas fa-building"></i></span>
                    <div>
                        <div class="font-bold"><?= htmlspecialchars($d['name']) ?></div>
                        <small class="text-gray-500"><?= htmlspecialchars($d['description'] ?? '') ?></small>
                    </div>
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= ($d['status'] ?? 'active') === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                    <?= ($d['status'] ?? 'active') === 'active' ? 'Actif' : 'Inactif' ?>
                </span>
            </div>
            <div class="flex items-center justify-between mt-3">
                <div>
                    <div class="text-xl font-bold text-blue-600"><?= (int)($d['employee_count'] ?? 0) ?></div>
                    <small class="text-gray-500">employés</small>
                </div>
                <div class="flex items-center gap-1">
                    <button class="p-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200" id="btnEditDept" data-id="<?= $d['id'] ?>" data-name="<?= htmlspecialchars($d['name']) ?>" data-desc="<?= htmlspecialchars($d['description'] ?? '') ?>" data-status="<?= htmlspecialchars($d['status'] ?? 'active') ?>">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="p-2 border border-red-300 text-red-700 rounded-lg hover:bg-red-50" onclick="if(confirmDelete('Supprimer le département &quot;<?= htmlspecialchars(addslashes($d['name'])) ?>&quot; ?')) document.getElementById('deldept-<?= $d['id'] ?>').submit();">
                        <i class="fas fa-trash"></i>
                    </button>
                    <form id="deldept-<?= $d['id'] ?>" method="post" action="index.php?controller=departments&action=delete&id=<?= $d['id'] ?>" style="display:none">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Create / Edit Department Modal -->
<div class="fixed inset-0 z-50 hidden" id="deptModal">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 transition-opacity" id="deptModalOverlay"></div>
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 w-full max-w-md relative z-10">
            <form method="post" action="index.php?controller=departments&action=create" id="deptForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="id" id="deptId" value="">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h5 class="text-lg font-semibold text-gray-900"><i class="fas fa-sitemap mr-2"></i><span id="deptModalTitle">Nouveau département</span></h5>
                    <button type="button" class="p-2 rounded-lg hover:bg-gray-100 text-gray-400" id="btnCloseDeptModal">&times;</button>
                </div>
                <div class="p-6">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-600">*</span></label>
                        <input type="text" name="name" id="deptName" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" id="deptDesc" rows="3" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                        <select name="status" id="deptStatus" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="active">Actif</option>
                            <option value="inactive">Inactif</option>
                        </select>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-end gap-3">
                    <button type="button" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50" id="btnCancelDeptModal">Annuler</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium"><i class="fas fa-save mr-1"></i>Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('deptModal');
    var overlay = document.getElementById('deptModalOverlay');
    var form = document.getElementById('deptForm');
    var btnOpen = document.getElementById('btnOpenDeptModal');
    var btnClose = document.getElementById('btnCloseDeptModal');
    var btnCancel = document.getElementById('btnCancelDeptModal');
    var btnEdits = document.querySelectorAll('[id="btnEditDept"]');

    function openModal() { modal.classList.remove('hidden'); }
    function closeModal() {
        modal.classList.add('hidden');
        form.reset();
        document.getElementById('deptId').value = '';
        document.getElementById('deptModalTitle').textContent = 'Nouveau département';
        form.action = 'index.php?controller=departments&action=create';
    }

    if (btnOpen) btnOpen.addEventListener('click', openModal);
    if (btnClose) btnClose.addEventListener('click', closeModal);
    if (btnCancel) btnCancel.addEventListener('click', closeModal);
    if (overlay) overlay.addEventListener('click', closeModal);

    btnEdits.forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('deptId').value = btn.dataset.id;
            document.getElementById('deptName').value = btn.dataset.name;
            document.getElementById('deptDesc').value = btn.dataset.desc;
            document.getElementById('deptStatus').value = btn.dataset.status;
            document.getElementById('deptModalTitle').textContent = 'Modifier le département';
            form.action = 'index.php?controller=departments&action=edit&id=' + encodeURIComponent(btn.dataset.id);
            openModal();
        });
    });
});
</script>
