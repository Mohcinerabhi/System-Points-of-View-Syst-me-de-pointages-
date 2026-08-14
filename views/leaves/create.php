<?php
/**
 * Create leave request
 */
$employees = $employees ?? [];
$errors    = $errors ?? [];
$leave     = $leave ?? [];
$csrf_token = $csrf_token ?? '';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h4 class="text-xl font-semibold text-gray-900"><i class="fas fa-calendar-plus text-blue-600 mr-2"></i><?= $pageTitle ?></h4>
        <div class="text-gray-500 text-sm">Remplissez le formulaire ci-dessous</div>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6">
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employé</label>
                    <select name="employee_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <option value="">Sélectionner un employé</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?= $e['id'] ?>" <?= ($leave['employee_id'] ?? '') == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['first_name'].' '.$e['last_name'].' ('.$e['employee_code'].')') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errors['employee_id'])): ?><p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($errors['employee_id']) ?></p><?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type de congé</label>
                    <select name="leave_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <option value="vacation" <?= ($leave['leave_type'] ?? '') === 'vacation' ? 'selected' : '' ?>>Congés payés</option>
                        <option value="sick" <?= ($leave['leave_type'] ?? '') === 'sick' ? 'selected' : '' ?>>Maladie</option>
                        <option value="personal" <?= ($leave['leave_type'] ?? '') === 'personal' ? 'selected' : '' ?>>Personnel</option>
                        <option value="maternity" <?= ($leave['leave_type'] ?? '') === 'maternity' ? 'selected' : '' ?>>Maternité</option>
                        <option value="paternity" <?= ($leave['leave_type'] ?? '') === 'paternity' ? 'selected' : '' ?>>Paternité</option>
                        <option value="other" <?= ($leave['leave_type'] ?? '') === 'other' ? 'selected' : '' ?>>Autre</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date de début</label>
                    <input type="date" name="start_date" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($leave['start_date'] ?? '') ?>" required>
                    <?php if (!empty($errors['start_date'])): ?><p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($errors['start_date']) ?></p><?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date de fin</label>
                    <input type="date" name="end_date" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($leave['end_date'] ?? '') ?>" required>
                    <?php if (!empty($errors['end_date'])): ?><p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($errors['end_date']) ?></p><?php endif; ?>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motif</label>
                    <textarea name="reason" rows="3" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?= htmlspecialchars($leave['reason'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium"><i class="fas fa-save mr-1"></i>Enregistrer</button>
                <a href="index.php?controller=leaves&action=index" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 shadow-sm font-medium">Annuler</a>
            </div>
        </form>
    </div>
</div>
