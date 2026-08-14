<?php
/**
 * Dashboard - Attendance Pro
 */
$stats = $stats ?? [
    'total_employees' => 8, 'present_today' => 7, 'absent' => 1, 'late' => 2,
    'early_departures' => 0, 'currently_present' => 5
];
$recentLogs = $recentLogs ?? [];
$departments = $departments ?? [];
$chart = $chart ?? ['presence_daily' => [], 'delays' => [], 'monthly' => [], 'dept_distribution' => []];
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h4 class="text-xl font-semibold text-gray-900"><i class="fas fa-tachometer-alt text-blue-600 mr-2"></i><?= __('dashboard') ?></h4>
        <div class="text-gray-500 text-sm"><?= __('recent_attendance') ?> &middot; <?= date('d/m/Y') ?></div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 shadow-sm font-medium" id="btnRefreshStats"><i class="fas fa-sync-alt mr-1"></i><?= __('refresh') ?></button>
        <a href="index.php?controller=reports&action=index" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium"><i class="fas fa-chart-bar mr-1"></i><?= __('reports') ?></a>
        <a href="index.php?controller=attendance&action=index" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 border border-transparent font-medium"><i class="fas fa-clock mr-1"></i><?= __('attendance') ?></a>
    </div>
</div>

<!-- Stats cards -->
<div class="grid grid-cols-2 lg:grid-cols-6 gap-4 mb-4">
    <div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 flex items-center gap-4 hover:shadow-md transition-shadow">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center text-xl bg-blue-100 text-blue-600"><i class="fas fa-users"></i></div>
            <div>
                <div class="text-2xl font-bold" data-stat="total_employees"><?= $stats['total_employees'] ?></div>
                <div class="text-gray-500 text-sm"><?= __('employees') ?></div>
                <div class="inline-flex items-center gap-1 text-xs font-semibold mt-1 px-2 py-0.5 rounded-full bg-green-100 text-green-600"><i class="fas fa-arrow-up"></i> 100% actifs</div>
            </div>
        </div>
    </div>
    <div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 flex items-center gap-4 hover:shadow-md transition-shadow">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center text-xl bg-green-100 text-green-600"><i class="fas fa-user-check"></i></div>
            <div>
                <div class="text-2xl font-bold" data-stat="present_today"><?= $stats['present_today'] ?></div>
                <div class="text-gray-500 text-sm"><?= __('present_today', 'Présents auj.') ?></div>
                <div class="inline-flex items-center gap-1 text-xs font-semibold mt-1 px-2 py-0.5 rounded-full bg-green-100 text-green-600"><i class="fas fa-arrow-up"></i> +5% hier</div>
            </div>
        </div>
    </div>
    <div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 flex items-center gap-4 hover:shadow-md transition-shadow">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center text-xl bg-red-100 text-red-600"><i class="fas fa-user-times"></i></div>
            <div>
                <div class="text-2xl font-bold" data-stat="absent"><?= $stats['absent'] ?></div>
                <div class="text-gray-500 text-sm">Absents</div>
                <div class="inline-flex items-center gap-1 text-xs font-semibold mt-1 px-2 py-0.5 rounded-full bg-red-100 text-red-600"><i class="fas fa-arrow-down"></i> -1% hier</div>
            </div>
        </div>
    </div>
    <div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 flex items-center gap-4 hover:shadow-md transition-shadow">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center text-xl bg-yellow-100 text-yellow-600"><i class="fas fa-hourglass-half"></i></div>
            <div>
                <div class="text-2xl font-bold" data-stat="late"><?= $stats['late'] ?></div>
                <div class="text-gray-500 text-sm">Retards</div>
                <div class="inline-flex items-center gap-1 text-xs font-semibold mt-1 px-2 py-0.5 rounded-full bg-red-100 text-red-600"><i class="fas fa-arrow-up"></i> +2 auj.</div>
            </div>
        </div>
    </div>
    <div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 flex items-center gap-4 hover:shadow-md transition-shadow">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center text-xl bg-purple-100 text-purple-600"><i class="fas fa-running"></i></div>
            <div>
                <div class="text-2xl font-bold" data-stat="early_departures"><?= $stats['early_departures'] ?></div>
                <div class="text-gray-500 text-sm">Départs anticipés</div>
                <div class="inline-flex items-center gap-1 text-xs font-semibold mt-1 px-2 py-0.5 rounded-full bg-green-100 text-green-600"><i class="fas fa-arrow-down"></i> stable</div>
            </div>
        </div>
    </div>
    <div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 flex items-center gap-4 hover:shadow-md transition-shadow">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center text-xl bg-sky-100 text-sky-600"><i class="fas fa-door-open"></i></div>
            <div>
                <div class="text-2xl font-bold" data-stat="currently_present"><?= $stats['currently_present'] ?></div>
                <div class="text-gray-500 text-sm">Actuellement présents</div>
                <div class="inline-flex items-center gap-1 text-xs font-semibold mt-1 px-2 py-0.5 rounded-full bg-green-100 text-green-600"><i class="fas fa-circle text-green-600"></i> en ligne</div>
            </div>
        </div>
    </div>
</div>

<!-- Charts + recent -->
<div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-4">
    <div class="lg:col-span-3">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 h-full">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h5 class="font-semibold text-gray-900"><i class="fas fa-chart-line text-blue-600 mr-2"></i>Présence quotidienne & Retards</h5>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">7 derniers jours</span>
            </div>
            <div class="p-6"><canvas id="presenceChart" height="110"></canvas></div>
        </div>
    </div>
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 h-full">
            <div class="px-6 py-4 border-b border-gray-200"><h5 class="font-semibold text-gray-900"><i class="fas fa-chart-pie text-blue-600 mr-2"></i>Répartition par département</h5></div>
            <div class="p-6"><canvas id="deptChart"></canvas></div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 h-full">
            <div class="px-6 py-4 border-b border-gray-200"><h5 class="font-semibold text-gray-900"><i class="fas fa-chart-area text-blue-600 mr-2"></i>Présence mensuelle</h5></div>
            <div class="p-6"><canvas id="monthlyChart" height="120"></canvas></div>
        </div>
    </div>
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 h-full">
            <div class="px-6 py-4 border-b border-gray-200"><h5 class="font-semibold text-gray-900"><i class="fas fa-exclamation-triangle text-blue-600 mr-2"></i>Retards par jour</h5></div>
            <div class="p-6"><canvas id="delaysChart" height="120"></canvas></div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <!-- Recent attendance -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h5 class="font-semibold text-gray-900"><i class="fas fa-history text-blue-600 mr-2"></i>Derniers pointages</h5>
                <a href="index.php?controller=attendance&action=index" class="px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium">Voir tout</a>
            </div>
            <div class="p-0 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead><tr><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Employé</th><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Département</th><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Date</th><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Heure</th><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Type</th><th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Source</th></tr></thead>
                    <tbody class="divide-y divide-gray-200">
                    <?php if (empty($recentLogs)): ?>
                        <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">Aucun pointage récent</td></tr>
                    <?php else: foreach ($recentLogs as $log): ?>
                        <tr class="hover:bg-blue-50 transition-colors">
                            <td class="px-6 py-4">
                                 <?= \App\Models\Employee::photoTag($log['photo'] ?? null, 'w-9 h-9 rounded-full object-cover mr-2 inline-block') ?>
                                 <strong><?= htmlspecialchars(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?></strong>
                            </td>
                            <td class="px-6 py-4"><?= htmlspecialchars($log['department_name'] ?? '-') ?></td>
                            <td class="px-6 py-4"><?= htmlspecialchars($log['attendance_date'] ?? '') ?></td>
                            <td class="px-6 py-4"><?= htmlspecialchars($log['attendance_time'] ?? '') ?></td>
                            <td class="px-6 py-4">
                                <?php
                                $t = $log['type'] ?? '';
                                $map = ['check_in' => ['Entrée','bg-green-100 text-green-800'], 'check_out' => ['Sortie','bg-red-100 text-red-800'], 'manual' => ['Manuel','bg-yellow-100 text-yellow-800'], 'break_start' => ['Pause','bg-sky-100 text-sky-800'], 'break_end' => ['Fin pause','bg-blue-100 text-blue-800']];
                                $m = $map[$t] ?? ['-','bg-gray-100 text-gray-800'];
                                ?><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $m[1] ?>"><?= $m[0] ?></span>
                            </td>
                            <td class="px-6 py-4"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"><?= htmlspecialchars($log['source'] ?? '-') ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Department breakdown -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 h-full">
            <div class="px-6 py-4 border-b border-gray-200"><h5 class="font-semibold text-gray-900"><i class="fas fa-sitemap text-blue-600 mr-2"></i>Départements</h5></div>
            <div class="p-6">
                <?php if (empty($departments)): ?>
                    <p class="text-gray-500 text-center py-3">Aucun département</p>
                <?php else: foreach ($departments as $d): ?>
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-9 h-9 rounded-lg flex items-center justify-center bg-blue-100 text-blue-600"><i class="fas fa-building"></i></span>
                            <div>
                                <div class="font-semibold"><?= htmlspecialchars($d['name']) ?></div>
                                <small class="text-gray-500"><?= (int)($d['employee_count'] ?? 0) ?> employés</small>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-bold"><?= (int)($d['present_count'] ?? 0) ?>/<?= (int)($d['employee_count'] ?? 0) ?></div>
                            <small class="text-green-600">présents</small>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var presence = <?= json_encode($chart['presence_daily'] ?? []) ?>;
    var delays   = <?= json_encode($chart['delays'] ?? []) ?>;
    var monthly  = <?= json_encode($chart['monthly'] ?? []) ?>;
    var dept     = <?= json_encode($chart['dept_distribution'] ?? []) ?>;

    if (document.getElementById('presenceChart')) {
        new Chart(document.getElementById('presenceChart'), {
            type: 'line',
            data: { labels: presence.map(function (p) { return p.label; }),
                datasets: [
                    { label: 'Présents', data: presence.map(function (p) { return p.present; }), borderColor: '#2fb344', backgroundColor: 'rgba(47,179,68,.1)', tension: .35, fill: true },
                    { label: 'Retards', data: delays.map(function (p) { return p.late; }), borderColor: '#f59f00', backgroundColor: 'rgba(245,159,0,.1)', tension: .35, fill: true }
                ]},
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
    }
    if (document.getElementById('deptChart')) {
        new Chart(document.getElementById('deptChart'), {
            type: 'doughnut',
            data: { labels: dept.map(function (d) { return d.name; }),
                datasets: [{ data: dept.map(function (d) { return d.count; }), backgroundColor: ['#3b5bdb','#2fb344','#f59f00','#e03131','#1c7ed6','#7048e8','#0ca678'] }] },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
    }
    if (document.getElementById('monthlyChart')) {
        new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: { labels: monthly.map(function (m) { return m.label; }),
                datasets: [{ label: 'Jours présents', data: monthly.map(function (m) { return m.days; }), backgroundColor: '#3b5bdb', borderRadius: 6 }] },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });
    }
    if (document.getElementById('delaysChart')) {
        new Chart(document.getElementById('delaysChart'), {
            type: 'line',
            data: { labels: delays.map(function (p) { return p.label; }),
                datasets: [{ label: 'Retards', data: delays.map(function (p) { return p.late; }), borderColor: '#e03131', backgroundColor: 'rgba(224,49,49,.1)', tension: .3, fill: true }] },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });
    }
})();

// Live dashboard refresh
document.addEventListener('DOMContentLoaded', function () {
    const btnRefresh = document.getElementById('btnRefreshStats');
    if (btnRefresh) {
        btnRefresh.addEventListener('click', function () {
            this.querySelector('i').classList.add('fa-spin');
            fetch('index.php?controller=dashboard&action=ajaxStats')
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data) {
                        updateStatCards(data.data.stats);
                        if (window.App && App.toast) App.toast('Statistiques actualisées', 'success');
                    }
                })
                .catch(() => { if (window.App && App.toast) App.toast("Erreur lors de l'actualisation", 'error'); })
                .finally(() => {
                    const icon = btnRefresh.querySelector('i');
                    if (icon) icon.classList.remove('fa-spin');
                });
        });
    }

    setInterval(function () {
        fetch('index.php?controller=dashboard&action=ajaxStats')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data) {
                    updateStatCards(data.data.stats);
                }
            })
            .catch(() => {});
    }, 30000);
});

function updateStatCards(stats) {
    if (stats.total_employees !== undefined) {
        const el = document.querySelector('[data-stat="total_employees"]');
        if (el) el.textContent = stats.total_employees;
    }
    if (stats.present_today !== undefined) {
        const el = document.querySelector('[data-stat="present_today"]');
        if (el) el.textContent = stats.present_today;
    }
    if (stats.absent !== undefined) {
        const el = document.querySelector('[data-stat="absent"]');
        if (el) el.textContent = stats.absent;
    }
    if (stats.late !== undefined) {
        const el = document.querySelector('[data-stat="late"]');
        if (el) el.textContent = stats.late;
    }
    if (stats.early_departures !== undefined) {
        const el = document.querySelector('[data-stat="early_departures"]');
        if (el) el.textContent = stats.early_departures;
    }
    if (stats.currently_present !== undefined) {
        const el = document.querySelector('[data-stat="currently_present"]');
        if (el) el.textContent = stats.currently_present;
    }
}
</script>
