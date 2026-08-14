<?php
$log      = $log      ?? [];
$employee = $employee ?? [];

function attType($t) {
    $m = ['check_in' => ['Entrée','bg-green-100 text-green-800'], 'check_out' => ['Sortie','bg-red-100 text-red-800'], 'manual' => ['Manuel','bg-yellow-100 text-yellow-800'], 'break_start' => ['Début pause','bg-sky-100 text-sky-800'], 'break_end' => ['Fin pause','bg-blue-100 text-blue-800']];
    $v = $m[$t] ?? ['-','bg-gray-100 text-gray-800'];
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium '.$v[1].'">'.$v[0].'</span>';
}
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h4 class="text-xl font-semibold text-gray-900">Détail du pointage</h4>
    <div>
        <a href="index.php?controller=attendance&action=index" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"><i class="fas fa-arrow-left mr-1"></i>Retour</a>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6">
        <table class="min-w-full divide-y divide-gray-200">
            <tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50 w-1/4">Employé</th><td class="px-6 py-4">
                <?= \App\Models\Employee::photoTag($employee['photo'] ?? null, 'w-9 h-9 rounded-full object-cover mr-2 inline-block') ?>
                <strong><?= htmlspecialchars(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')) ?></strong>
            </td></tr>
            <tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Matricule</th><td class="px-6 py-4"><?= htmlspecialchars($employee['employee_code'] ?? '-') ?></td></tr>
            <tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Date</th><td class="px-6 py-4"><?= htmlspecialchars($log['attendance_date'] ?? '') ?></td></tr>
            <tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Heure</th><td class="px-6 py-4">
                        <?php if (($log['type'] ?? '') === 'check_in'): ?>
                            <span class="inline-flex items-center gap-1 text-green-600"><i class="fa-solid fa-arrow-right-to-bracket"></i><?= htmlspecialchars($log['attendance_time'] ?? '') ?></span>
                        <?php elseif (($log['type'] ?? '') === 'check_out'): ?>
                            <span class="inline-flex items-center gap-1 text-red-600"><i class="fa-solid fa-arrow-right-from-bracket"></i><?= htmlspecialchars($log['attendance_time'] ?? '') ?></span>
                        <?php else: ?>
                            <?= htmlspecialchars($log['attendance_time'] ?? '') ?>
                        <?php endif; ?>
                    </td></tr>
            <tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Type</th><td class="px-6 py-4"><?= attType($log['type'] ?? '') ?></td></tr>
            <tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Source</th><td class="px-6 py-4"><?= htmlspecialchars($log['source'] ?? '-') ?></td></tr>
            <tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Terminal / IP</th><td class="px-6 py-4"><?= htmlspecialchars(($log['terminal_ip'] ?? '-')) ?></td></tr>
            <tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Retard (min)</th><td class="px-6 py-4"><?= htmlspecialchars($log['late_minutes'] ?? 0) ?></td></tr>
            <tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Départ anticipé (min)</th><td class="px-6 py-4"><?= htmlspecialchars($log['early_departure_minutes'] ?? 0) ?></td></tr>
            <tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Heures sup. (min)</th><td class="px-6 py-4"><?= htmlspecialchars($log['overtime_minutes'] ?? 0) ?></td></tr>
            <tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Durée travail (min)</th><td class="px-6 py-4"><?= htmlspecialchars($log['work_duration_minutes'] ?? 0) ?></td></tr>
            <tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Notes</th><td class="px-6 py-4"><?= nl2br(htmlspecialchars($log['notes'] ?? '')) ?></td></tr>
        </table>
    </div>
</div>
