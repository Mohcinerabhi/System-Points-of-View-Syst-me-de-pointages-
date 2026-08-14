<?php
/**
 * Vue : saisie manuelle rapide.
 */
$employees = $employees ?? [];
$errors    = $errors    ?? [];
$types = ['check_in' => 'Entrée', 'check_out' => 'Sortie', 'break_start' => 'Début pause', 'break_end' => 'Fin pause'];
$csrf_token = $csrf_token ?? '';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h4 class="text-xl font-semibold text-gray-900"><i class="fas fa-keyboard text-blue-600 mr-2"></i>Saisie manuelle</h4>
        <div class="text-gray-500 text-sm">Enregistrer un pointage manuellement</div>
    </div>
    <a href="index.php?controller=attendance&action=today" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"><i class="fas fa-list mr-1"></i>Pointages du jour</a>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 max-w-2xl">
    <div class="p-6">
        <form method="post" action="index.php?controller=attendance&action=manualEntry" class="flex flex-col gap-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="employee_id">Employé <span class="text-red-600">*</span></label>
                <select id="employee_id" name="employee_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    <option value="">-- Choisir --</option>
                    <?php foreach ($employees as $e): ?>
                        <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['employee_id'])): ?><span class="text-red-600 text-sm mt-1 block"><?= htmlspecialchars($errors['employee_id']) ?></span><?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="type">Type <span class="text-red-600">*</span></label>
                <select id="type" name="type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    <?php foreach ($types as $k => $v): ?>
                        <option value="<?= $k ?>"><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['type'])): ?><span class="text-red-600 text-sm mt-1 block"><?= htmlspecialchars($errors['type']) ?></span><?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="3" placeholder="Optionnel..." class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
            </div>

            <div class="flex items-start gap-2 p-3 bg-sky-50 text-sky-800 border border-sky-200 rounded-lg text-sm">
                <i class="fas fa-info-circle mt-0.5"></i>
                <span>La date et l'heure actuelles seront enregistrées automatiquement.</span>
            </div>

            <div class="flex items-center gap-3 pt-4 border-t border-gray-200">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium"><i class="fas fa-clock mr-1"></i>Pointer</button>
            </div>
        </form>
    </div>
</div>
