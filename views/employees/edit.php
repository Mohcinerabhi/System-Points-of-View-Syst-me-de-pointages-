<?php
/**
 * Employee edit form
 */
$employee    = $employee    ?? [];
$departments = $departments ?? [];
$errors      = $errors      ?? [];
$csrf_token   = $csrf_token   ?? '';
$e           = $employee;

if (!is_array($e)) {
    $e = [];
}
if (!is_array($departments)) {
    $departments = [];
}
if (!is_array($errors)) {
    $errors = [];
}
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h4 class="text-xl font-semibold text-gray-900"><i class="fas fa-user-edit text-blue-600 mr-2"></i>Modifier l'employé</h4>
        <div class="text-gray-500 text-sm"><?= htmlspecialchars($e['first_name'] ?? '') ?> <?= htmlspecialchars($e['last_name'] ?? '') ?> &middot; <?= htmlspecialchars($e['employee_code'] ?? '') ?></div>
    </div>
    <a href="index.php" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"><i class="fas fa-arrow-left mr-1"></i>Retour</a>
</div>

<?php if (!empty($errors) && empty($errors['_field'])): ?>
    <div class="flex items-center gap-2 p-4 bg-red-50 text-red-800 rounded-lg mb-4"><i class="fas fa-exclamation-circle"></i><span>Veuillez corriger les erreurs ci-dessous.</span></div>
<?php endif; ?>

<form method="post" action="" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token) ?>">
    <input type="hidden" name="id" value="<?= (int)($e['id'] ?? 0) ?>">

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
        <div class="px-6 py-4 border-b border-gray-200"><h5 class="font-semibold text-gray-900"><i class="fas fa-id-card mr-2"></i>Informations personnelles</h5></div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-1 text-center">
                     <?= \App\Models\Employee::photoTag($e['photo'] ?? null, 'w-20 h-20 rounded-full object-cover mb-2 mx-auto', 'photo') ?>
                    <div>
                        <label class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm cursor-pointer inline-flex items-center">
                            <i class="fas fa-camera mr-1"></i>Photo
                            <input type="file" name="photo" class="hidden" accept="image/*">
                        </label>
                    </div>
                </div>
                <div class="md:col-span-1">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prénom <span class="text-red-600">*</span></label>
                            <input type="text" name="first_name" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($e['first_name'] ?? '') ?>" required>
                            <?php if (!empty($errors['first_name'])): ?>
                                <div class="text-red-600 text-sm mt-1"><?= htmlspecialchars($errors['first_name']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-600">*</span></label>
                            <input type="text" name="last_name" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($e['last_name'] ?? '') ?>" required>
                            <?php if (!empty($errors['last_name'])): ?>
                                <div class="text-red-600 text-sm mt-1"><?= htmlspecialchars($errors['last_name']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Matricule <span class="text-red-600">*</span></label>
                            <input type="text" name="employee_code" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($e['employee_code'] ?? '') ?>" required>
                            <?php if (!empty($errors['employee_code'])): ?>
                                <div class="text-red-600 text-sm mt-1"><?= htmlspecialchars($errors['employee_code']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                            <input type="text" name="phone" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($e['phone'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date d'embauche</label>
                            <input type="date" name="hire_date" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($e['hire_date'] ?? '') ?>">
                        </div>
                    </div>
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
                        <?php if (!empty($departments)): foreach ($departments as $d): ?>
                            <option value="<?= (int)$d['id'] ?>" <?= (($e['department_id'] ?? '') == $d['id']) ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                    <?php if (!empty($errors['department_id'])): ?>
                        <div class="text-red-600 text-sm mt-1"><?= htmlspecialchars($errors['department_id']) ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                    <select name="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="active" <?= (($e['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Actif</option>
                        <option value="inactive" <?= (($e['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactif</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ID Badge</label>
                    <input type="text" name="badge_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($e['badge_id'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Code Badge</label>
                    <input type="text" name="badge_code" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($e['badge_code'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Statut enregistrement</label>
                    <select name="registration_status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="pending" <?= (($e['registration_status'] ?? '') === 'pending') ? 'selected' : '' ?>>En attente</option>
                        <option value="registered" <?= (($e['registration_status'] ?? '') === 'registered') ? 'selected' : '' ?>>Enregistré</option>
                        <option value="error" <?= (($e['registration_status'] ?? '') === 'error') ? 'selected' : '' ?>>Erreur</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-2">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium"><i class="fas fa-save mr-1"></i>Enregistrer</button>
        <a href="index.php" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"><i class="fas fa-times mr-1"></i>Annuler</a>
    </div>
</form>
