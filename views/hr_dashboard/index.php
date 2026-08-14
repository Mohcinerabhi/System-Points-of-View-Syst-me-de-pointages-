<?php
/**
 * HR Dashboard
 */
$stats = $stats ?? [];
$attendanceRate = $attendanceRate ?? 0;
$punctualityRate = $punctualityRate ?? 0;
$overtimeHours = $overtimeHours ?? 0;
$leaveBalance = $leaveBalance ?? [];
$deptDistribution = $deptDistribution ?? [];
$recentActivities = $recentActivities ?? [];
$onLeaveToday = $onLeaveToday ?? 0;
$remoteToday = $remoteToday ?? 0;
$activeToday = $activeToday ?? 0;
$csrf_token = $csrf_token ?? '';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h4 class="text-xl font-semibold text-gray-900"><i class="fas fa-chart-line text-blue-600 mr-2"></i>Tableau de bord RH</h4>
        <div class="text-gray-500 text-sm">Indicateurs de performance RH &middot; <?= date('d/m/Y') ?></div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 shadow-sm font-medium" onclick="location.reload()"><i class="fas fa-sync-alt mr-1"></i>Actualiser</button>
        <a href="index.php?controller=reports&action=index" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium"><i class="fas fa-chart-bar mr-1"></i>Rapports</a>
    </div>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center text-xl bg-blue-100 text-blue-600"><i class="fas fa-users"></i></div>
            <div>
                <div class="text-2xl font-bold"><?= (int)($stats['total_employees'] ?? 0) ?></div>
                <div class="text-gray-500 text-sm">Employés actifs</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center text-xl bg-green-100 text-green-600"><i class="fas fa-user-check"></i></div>
            <div>
                <div class="text-2xl font-bold"><?= (int)$activeToday ?></div>
                <div class="text-gray-500 text-sm">Présents aujourd'hui</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center text-xl bg-red-100 text-red-600"><i class="fas fa-user-times"></i></div>
            <div>
                <div class="text-2xl font-bold"><?= (int)$onLeaveToday ?></div>
                <div class="text-gray-500 text-sm">En congé aujourd'hui</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center text-xl bg-indigo-100 text-indigo-600"><i class="fas fa-house-laptop"></i></div>
            <div>
                <div class="text-2xl font-bold"><?= (int)$remoteToday ?></div>
                <div class="text-gray-500 text-sm">En télétravail</div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center text-xl bg-purple-100 text-purple-600"><i class="fas fa-percent"></i></div>
            <div>
                <div class="text-2xl font-bold"><?= number_format($attendanceRate, 1) ?>%</div>
                <div class="text-gray-500 text-sm">Taux de présence</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center text-xl bg-yellow-100 text-yellow-600"><i class="fas fa-stopwatch"></i></div>
            <div>
                <div class="text-2xl font-bold"><?= number_format($punctualityRate, 1) ?>%</div>
                <div class="text-gray-500 text-sm">Taux de ponctualité</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center text-xl bg-orange-100 text-orange-600"><i class="fas fa-clock"></i></div>
            <div>
                <div class="text-2xl font-bold"><?= number_format($overtimeHours, 1) ?>h</div>
                <div class="text-gray-500 text-sm">Heures sup. ce mois</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center text-xl bg-sky-100 text-sky-600"><i class="fas fa-calendar-minus"></i></div>
            <div>
                <div class="text-2xl font-bold"><?= (int)($stats['absent'] ?? 0) ?></div>
                <div class="text-gray-500 text-sm">Absents aujourd'hui</div>
            </div>
        </div>
    </div>
</div>

<!-- Department Distribution + Leave Balance -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200"><h5 class="font-semibold text-gray-900"><i class="fas fa-sitemap text-blue-600 mr-2"></i>Répartition par département</h5></div>
        <div class="p-6">
            <?php if (empty($deptDistribution)): ?>
                <p class="text-gray-500 text-center py-3">Aucun département</p>
            <?php else: foreach ($deptDistribution as $d): ?>
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-9 h-9 rounded-lg flex items-center justify-center bg-blue-100 text-blue-600"><i class="fas fa-building"></i></span>
                        <div>
                            <div class="font-semibold"><?= htmlspecialchars($d['name']) ?></div>
                            <small class="text-gray-500"><?= (int)($d['count'] ?? 0) ?> employé(s)</small>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200"><h5 class="font-semibold text-gray-900"><i class="fas fa-calendar-minus text-blue-600 mr-2"></i>Solde de congés (cette année)</h5></div>
        <div class="p-6">
            <?php if (empty($leaveBalance)): ?>
                <p class="text-gray-500 text-center py-3">Aucun congé enregistré</p>
            <?php else: foreach ($leaveBalance as $type => $info): ?>
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-9 h-9 rounded-lg flex items-center justify-center bg-green-100 text-green-600"><i class="fas fa-umbrella-beach"></i></span>
                        <div>
                            <div class="font-semibold"><?= htmlspecialchars($info['label']) ?></div>
                            <small class="text-gray-500"><?= (int)($info['count'] ?? 0) ?> demande(s)</small>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold"><?= (int)($info['days'] ?? 0) ?> jours</div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- Recent Activities -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-200"><h5 class="font-semibold text-gray-900"><i class="fas fa-history text-blue-600 mr-2"></i>Activités récentes</h5></div>
    <div class="p-0 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead><tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Utilisateur</th><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Module</th><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Action</th><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Date</th></tr></thead>
            <tbody class="divide-y divide-gray-200">
            <?php if (empty($recentActivities)): ?>
                <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">Aucune activité récente</td></tr>
            <?php else: foreach ($recentActivities as $a): ?>
                <tr>
                    <td class="px-6 py-4"><?= htmlspecialchars($a['user_name'] ?? '-') ?></td>
                    <td class="px-6 py-4"><span class="uppercase text-xs font-mono bg-gray-100 px-2 py-1 rounded"><?= htmlspecialchars($a['module'] ?? '-') ?></span></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($a['description'] ?? $a['action'] ?? '-') ?></td>
                    <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($a['created_at'] ?? '') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
