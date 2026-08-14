<?php
/**
 * View leave request detail
 */
$leave     = $leave ?? [];
$employee  = $employee ?? null;
$approver  = $approver ?? null;
$csrf_token = $csrf_token ?? '';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h4 class="text-xl font-semibold text-gray-900"><i class="fas fa-calendar-check text-blue-600 mr-2"></i>Détail de la demande de congé</h4>
        <div class="text-gray-500 text-sm">Demande #<?= $leave['id'] ?? '' ?></div>
    </div>
    <div class="flex items-center gap-2">
        <a href="index.php?controller=leaves&action=index" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 shadow-sm font-medium"><i class="fas fa-arrow-left mr-1"></i>Retour</a>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Employé</label>
                <p class="text-gray-900 font-medium"><?= $employee ? htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_code'] . ')') : 'N/A' ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Type de congé</label>
                <p class="text-gray-900"><?= htmlspecialchars(ucfirst($leave['leave_type'] ?? '')) ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Date de début</label>
                <p class="text-gray-900"><?= htmlspecialchars($leave['start_date'] ?? '') ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Date de fin</label>
                <p class="text-gray-900"><?= htmlspecialchars($leave['end_date'] ?? '') ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Statut</label>
                <p>
                    <?php
                    $status = $leave['status'] ?? '';
                    $badgeClass = match ($status) {
                        'approved'  => 'bg-green-100 text-green-800',
                        'rejected'  => 'bg-red-100 text-red-800',
                        'cancelled' => 'bg-gray-100 text-gray-800',
                        default     => 'bg-yellow-100 text-yellow-800',
                    };
                    $statusLabel = match ($status) {
                        'approved'  => 'Approuvé',
                        'rejected'  => 'Rejeté',
                        'cancelled' => 'Annulé',
                        default     => 'En attente',
                    };
                    ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $badgeClass ?>"><?= $statusLabel ?></span>
                </p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Approuvé par</label>
                <p class="text-gray-900"><?= $approver ? htmlspecialchars($approver['first_name'] . ' ' . $approver['last_name']) : 'N/A' ?></p>
            </div>
            <?php if (!empty($leave['reason'])): ?>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-500 mb-1">Motif</label>
                    <p class="text-gray-900"><?= nl2br(htmlspecialchars($leave['reason'])) ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($leave['rejection_reason'])): ?>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-500 mb-1">Motif de rejet</label>
                    <p class="text-red-600"><?= nl2br(htmlspecialchars($leave['rejection_reason'])) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <?php if (($leave['status'] ?? '') === 'pending'): ?>
        <div class="mt-6 pt-6 border-t border-gray-200 flex items-center gap-3">
            <form method="post" action="index.php?controller=leaves&action=approve" class="inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="id" value="<?= $leave['id'] ?>">
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 shadow-sm font-medium"><i class="fas fa-check mr-1"></i>Approuver</button>
            </form>
            <form method="post" action="index.php?controller=leaves&action=reject" class="inline" onsubmit="return confirm('Rejeter cette demande ?')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="id" value="<?= $leave['id'] ?>">
                <input type="text" name="rejection_reason" placeholder="Motif de rejet" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 mr-2" required>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 shadow-sm font-medium"><i class="fas fa-times mr-1"></i>Rejeter</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
