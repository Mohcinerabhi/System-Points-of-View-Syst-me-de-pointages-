<?php
/**
 * Attendance logs list
 */
$logs        = $logs        ?? [];
$employees   = $employees   ?? [];
$departments = $departments ?? [];
$filters     = $filters     ?? ['from' => '', 'to' => '', 'employee_id' => '', 'department_id' => '', 'type' => ''];
$pagination  = $pagination  ?? ['page' => 1, 'total_pages' => 1];
$csrf_token  = $csrf_token  ?? '';

function attType($t) {
    $m = ['check_in' => ['Entrée','bg-green-100 text-green-800'], 'check_out' => ['Sortie','bg-red-100 text-red-800'], 'manual' => ['Manuel','bg-yellow-100 text-yellow-800'], 'break_start' => ['Début pause','bg-sky-100 text-sky-800'], 'break_end' => ['Fin pause','bg-blue-100 text-blue-800']];
    $v = $m[$t] ?? ['-','bg-gray-100 text-gray-800'];
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium '.$v[1].'">'.$v[0].'</span>';
}
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h4 class="text-xl font-semibold text-gray-900"><i class="fas fa-clock text-blue-600 mr-2"></i><?= __('attendance') ?></h4>
        <div class="text-gray-500 text-sm"><?= __('attendance_logs') ?></div>
    </div>
    <a href="index.php?controller=attendance&action=manualEntry" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium"><i class="fas fa-plus mr-1"></i><?= __('manual_entry') ?></a>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
    <div class="p-4">
        <form method="get" class="flex flex-wrap items-end gap-3" id="attendanceFilterForm">
            <div class="flex" style="min-width:200px">
                <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500">
                    <i class="fas fa-calendar text-gray-400"></i>
                </span>
                <input type="date" name="date" class="flex-1 rounded-r-md border-gray-300 border shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($filters['date'] ?? '') ?>">
            </div>
            <select name="employee_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" style="min-width:200px">
                <option value="">Tous les employés</option>
                <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= ($filters['employee_id'] ?? '') == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['first_name'].' '.$e['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="department_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" style="min-width:200px">
                <option value="">Tous les départements</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= ($filters['department_id'] ?? '') == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" style="min-width:160px">
                <option value="">Tous les types</option>
                <option value="check_in" <?= ($filters['type'] ?? '') === 'check_in' ? 'selected' : '' ?>>Entrée</option>
                <option value="check_out" <?= ($filters['type'] ?? '') === 'check_out' ? 'selected' : '' ?>>Sortie</option>
                <option value="manual" <?= ($filters['type'] ?? '') === 'manual' ? 'selected' : '' ?>>Manuel</option>
                <option value="break_start" <?= ($filters['type'] ?? '') === 'break_start' ? 'selected' : '' ?>>Début pause</option>
                <option value="break_end" <?= ($filters['type'] ?? '') === 'break_end' ? 'selected' : '' ?>>Fin pause</option>
            </select>
            <a href="index.php?controller=attendance&action=calendar" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 shadow-sm font-medium"><i class="fas fa-calendar-alt mr-1"></i><?= __('calendar') ?></a>
            <button type="button" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium" id="btnApplyAttendanceFilters"><i class="fas fa-filter mr-1"></i><?= __('filter') ?></button>
        </form>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-0 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200" id="attTable" data-url="index.php?controller=attendance&action=index">
            <thead>
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Employé</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Badge</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Heure entrée / sortie</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Durée travail</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Heures Supplémentaires</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Terminal / IP</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 bg-gray-50">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            <?php if (empty($logs)): ?>
                <tr><td colspan="8" class="px-6 py-4 text-center text-gray-500"><?= __('no_attendance') ?></td></tr>
            <?php else: foreach ($logs as $l): ?>
                <tr class="hover:bg-blue-50 transition-colors">
                    <td class="px-6 py-4">
                        <?= \App\Models\Employee::photoTag($l['photo'] ?? null, 'w-9 h-9 rounded-full object-cover mr-2 inline-block') ?>
                        <strong><?= htmlspecialchars(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')) ?></strong>
                    </td>
                    <td class="px-6 py-4"><?= htmlspecialchars($l['badge_id'] ?? '-') ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($l['attendance_date'] ?? '') ?></td>
                    <td class="px-6 py-4">
                        <div class="text-sm">
                            <?php if (!empty($l['entry_time'])): ?>
                                <div><i class="fa-solid fa-arrow-right-to-bracket text-green-600 mr-1"></i><?= htmlspecialchars($l['entry_time']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($l['exit_time'])): ?>
                                <div><i class="fa-solid fa-arrow-right-from-bracket text-red-600 mr-1"></i><?= htmlspecialchars($l['exit_time']) ?></div>
                            <?php endif; ?>
                            <?php if (empty($l['entry_time']) && empty($l['exit_time'])): ?>
                                <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <?php if (!empty($l['work_duration_human']) && $l['work_duration_human'] !== '-'): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                <i class="fa-solid fa-clock mr-1"></i><?= htmlspecialchars($l['work_duration_human']) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php if (!empty($l['overtime_human']) && $l['overtime_human'] !== '-'): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                <i class="fa-solid fa-clock mr-1"></i><?= htmlspecialchars($l['overtime_human']) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4"><?= htmlspecialchars(($l['terminal_name'] ?? '-') . ' / ' . ($l['terminal_ip'] ?? '-')) ?></td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-1 flex-wrap">
                            <?php if (!empty($l['work_status'])): ?>
                                <?php
                                    $statusClass = match ($l['work_status']) {
                                        'Journée complète' => 'bg-green-100 text-green-800',
                                        'Temps insuffisant' => 'bg-red-100 text-red-800',
                                        'En cours' => 'bg-yellow-100 text-yellow-800',
                                        default => 'bg-gray-100 text-gray-800',
                                    };
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusClass ?>">
                                    <?= htmlspecialchars($l['work_status']) ?>
                                </span>
                                <?php if (!empty($l['work_status_detail'])): ?>
                                    <span class="text-xs text-gray-500">(<?= htmlspecialchars($l['work_status_detail']) ?>)</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ((int)($l['work_duration_minutes'] ?? 0) >= 480): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <i class="fa-solid fa-check mr-1"></i>Journée complète
                                </span>
                            <?php endif; ?>
                            <a href="index.php?controller=attendance&action=view&id=<?= $l['id'] ?>" class="p-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm" title="Voir"><i class="fas fa-eye"></i></a>
                            <button class="p-2 border border-red-300 text-red-700 rounded-lg hover:bg-red-50 text-sm" title="Supprimer"
                                    onclick="if(confirmDelete('Supprimer ce pointage ?')) document.getElementById('delatt-<?= $l['id'] ?>').submit();">
                                <i class="fas fa-trash"></i>
                            </button>
                            <form id="delatt-<?= $l['id'] ?>" method="post" action="index.php?controller=attendance&action=delete&id=<?= $l['id'] ?>" style="display:none">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div id="attPaginationControls" class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-3 px-4 py-3 border-top">
        <div class="text-muted small">
            <?php
            $from = (int)($pagination['from'] ?? 0);
            $to = (int)($pagination['to'] ?? 0);
            $total = (int)($pagination['total'] ?? 0);
            if ($total > 0): ?>
                Affichage du <?= $from ?>–<?= $to ?> sur <strong><?= number_format($total, 0, ',', ' ') ?></strong> résultats
            <?php else: ?>
                Aucun résultat
            <?php endif; ?>
        </div>
        <?php if ($total > 0): ?>
        <nav id="attPagination" aria-label="Pagination">
            <ul id="attPaginationNav" class="pagination mb-0 att-pagination justify-content-center">
                <?php
                $current = (int)($pagination['current'] ?? 1);
                $last = max(1, (int)($pagination['last'] ?? 1));

                $baseUrl = 'index.php?controller=attendance&action=index';
                $filterParams = [];
                if (!empty($filters['date'])) $filterParams['date'] = $filters['date'];
                if (!empty($filters['employee_id'])) $filterParams['employee_id'] = $filters['employee_id'];
                if (!empty($filters['department_id'])) $filterParams['department_id'] = $filters['department_id'];
                if (!empty($filters['type'])) $filterParams['type'] = $filters['type'];
                $filterQuery = http_build_query($filterParams);
                if ($filterQuery) $baseUrl .= '&' . $filterQuery;

                $pageUrl = function ($page) use ($baseUrl) {
                    return $baseUrl . '&page=' . $page;
                };

                $pages = $pagination['pages_with_ellipsis'] ?? [];

                if ($current > 1): ?>
                    <li class="page-item">
                        <a class="page-link rounded-circle px-3 py-2 me-1" href="<?= $pageUrl($current - 1) ?>" data-page="<?= $current - 1 ?>" aria-label="Précédent">
                            <span aria-hidden="true">‹</span>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link rounded-circle px-3 py-2 me-1" aria-label="Précédent">
                            <span aria-hidden="true">‹</span>
                        </span>
                    </li>
                <?php endif;

                foreach ($pages as $p):
                    if ($p === '...'): ?>
                        <li class="page-item disabled">
                            <span class="page-link rounded-circle px-3 py-2" aria-hidden="true">…</span>
                        </li>
                    <?php elseif ($p === $current): ?>
                        <li class="page-item active" aria-current="page">
                            <span class="page-link rounded-circle px-3 py-2"><?= $p ?></span>
                        </li>
                    <?php else: ?>
                        <li class="page-item">
                            <a class="page-link rounded-circle px-3 py-2" href="<?= $pageUrl($p) ?>" data-page="<?= $p ?>"><?= $p ?></a>
                        </li>
                    <?php endif;
                endforeach;

                if ($current < $last): ?>
                    <li class="page-item">
                        <a class="page-link rounded-circle px-3 py-2 ms-1" href="<?= $pageUrl($current + 1) ?>" data-page="<?= $current + 1 ?>" aria-label="Suivant">
                            <span aria-hidden="true">›</span>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link rounded-circle px-3 py-2 ms-1" aria-label="Suivant">
                            <span aria-hidden="true">›</span>
                        </span>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function collectFilterParams() {
        var params = new URLSearchParams();
        document.querySelectorAll('#attendanceFilterForm select, #attendanceFilterForm input[type="date"]').forEach(function (el) {
            if (el.value) params.set(el.name, el.value);
        });
        return params;
    }

    function applyFilters() {
        var params = collectFilterParams();
        params.set('page', '1');
        loadAttendancePage(params);
    }

    function loadAttendancePage(params) {
        var url = 'index.php?controller=attendance&action=index&' + params.toString();

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.text();
            })
            .then(function (html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');

                var newTbody = doc.querySelector('#attTable tbody');
                var currentTbody = document.querySelector('#attTable tbody');
                if (newTbody && currentTbody) {
                    currentTbody.innerHTML = newTbody.innerHTML;
                } else {
                    console.error('[Pagination] tbody not found in AJAX response');
                }

                var newControls = doc.querySelector('#attPaginationControls');
                var currentControls = document.getElementById('attPaginationControls');
                if (newControls && currentControls) {
                    currentControls.innerHTML = newControls.innerHTML;
                } else {
                    console.error('[Pagination] attPaginationControls not found', {
                        inResponse: !!newControls,
                        inDOM: !!currentControls
                    });
                }

                bindPaginationClicks();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .catch(function (err) {
                console.error('[Pagination] AJAX failed:', err);
                window.location.href = url;
            });
    }

    function bindPaginationClicks() {
        var nav = document.getElementById('attPaginationNav');
        if (!nav) {
            console.error('[Pagination] attPaginationNav not found in DOM');
            return;
        }

        var freshNav = nav.cloneNode(true);
        if (nav.parentNode) {
            nav.parentNode.replaceChild(freshNav, nav);
        }

        freshNav.addEventListener('click', function (e) {
            var link = e.target.closest('a[data-page]');
            if (link) {
                e.preventDefault();
                var page = link.getAttribute('data-page');
                var params = collectFilterParams();
                params.set('page', page);
                loadAttendancePage(params);
            }
        });
    }

    var btnApply = document.getElementById('btnApplyAttendanceFilters');
    if (btnApply) {
        btnApply.addEventListener('click', applyFilters);
    }

    document.querySelectorAll('#attendanceFilterForm select').forEach(function (sel) {
        sel.addEventListener('change', applyFilters);
    });

    document.querySelectorAll('#attendanceFilterForm input[type="date"]').forEach(function (inp) {
        inp.addEventListener('change', applyFilters);
    });

    bindPaginationClicks();
});
</script>
