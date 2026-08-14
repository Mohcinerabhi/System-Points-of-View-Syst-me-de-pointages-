<?php
/**
 * Employee create form
 */
$departments = $departments ?? [];
$errors      = $errors      ?? [];
$old         = $old         ?? [];
$csrf_token   = $csrf_token   ?? '';

function val($field, $old) { return htmlspecialchars($old[$field] ?? ''); }
function err($field, $errors) { return !empty($errors[$field]) ? '<div class="text-red-600 text-sm mt-1">'.$errors[$field].'</div>' : ''; }
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h4 class="text-xl font-semibold text-gray-900"><i class="fas fa-user-plus text-blue-600 mr-2"></i>Nouvel employé</h4>
        <div class="text-gray-500 text-sm">Renseignez les informations de l'employé</div>
    </div>
    <a href="index.php" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"><i class="fas fa-arrow-left mr-1"></i>Retour</a>
</div>

<?php if (!empty($errors) && empty($errors['_field'])): ?>
    <div class="flex items-center gap-2 p-4 bg-red-50 text-red-800 rounded-lg mb-4"><i class="fas fa-exclamation-circle"></i><span>Veuillez corriger les erreurs ci-dessous.</span></div>
<?php endif; ?>

<form method="post" action="" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-6 py-4 border-b border-gray-200"><h5 class="font-semibold text-gray-900"><i class="fas fa-id-card mr-2"></i>Informations personnelles</h5></div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Prénom <span class="text-red-600">*</span></label>
                    <input type="text" name="first_name" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= val('first_name', $old) ?>" required>
                    <?= err('first_name', $errors) ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-600">*</span></label>
                    <input type="text" name="last_name" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= val('last_name', $old) ?>" required>
                    <?= err('last_name', $errors) ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Matricule <span class="text-red-600">*</span></label>
                    <input type="text" name="employee_code" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= val('employee_code', $old) ?>" required>
                    <?= err('employee_code', $errors) ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                    <input type="text" name="phone" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= val('phone', $old) ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date d'embauche</label>
                    <input type="date" name="hire_date" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= val('hire_date', $old) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-6 py-4 border-b border-gray-200"><h5 class="font-semibold text-gray-900"><i class="fas fa-briefcase mr-2"></i>Informations professionnelles</h5></div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Département <span class="text-red-600">*</span></label>
                    <select name="department_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <option value="">Sélectionner...</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= (($old['department_id'] ?? '') == $d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= err('department_id', $errors) ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                    <select name="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="active" <?= (($old['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Actif</option>
                        <option value="inactive" <?= (($old['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactif</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ID Badge</label>
                    <input type="text" name="badge_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= val('badge_id', $old) ?>" placeholder="B001">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Code Badge</label>
                    <input type="text" name="badge_code" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= val('badge_code', $old) ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Photo</label>
                    <input type="file" name="photo" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" accept="image/*">
                </div>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-2">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium"><i class="fas fa-save mr-1"></i>Enregistrer</button>
        <a href="index.php" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"><i class="fas fa-times mr-1"></i>Annuler</a>
    </div>
</form>
