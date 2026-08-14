<?php
/** @var array $schedules */
/** @var array $assignmentsBySchedule */
/** @var array $activeEmployees */
/** @var string $pageTitle */
/** @var string $flash_success */
/** @var string $flash_error */
/** @var string $csrf_token */
/** @var array $auth_user */
$activeMenu = 'schedules';
?>
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
        <h1 class="mb-0"><?php echo htmlspecialchars($pageTitle); ?></h1>
        <a href="index.php?controller=schedules&action=create" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> Nouvel horaire
        </a>
    </div>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Tableau de bord</a></li>
        <li class="breadcrumb-item active">Horaires de travail</li>
    </ol>

    <?php if ($flash_success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($flash_success); ?>
            <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
        </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($flash_error); ?>
            <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($schedules)): ?>
        <p class="text-muted">Aucun horaire configuré. Veuillez en créer un depuis les paramètres.</p>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="scheduleCards">
            <?php foreach ($schedules as $schedule):
                $sid = (int) $schedule['id'];
                $cardAssignments = $assignmentsBySchedule[$sid] ?? [];
                $count = count($cardAssignments);
            ?>
            <div class="col">
                <div class="card h-100 schedule-card" data-schedule-id="<?php echo $sid; ?>">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="d-flex align-items-center text-truncate">
                            <i class="fas fa-clock me-1 flex-shrink-0"></i>
                            <span class="fw-medium"><?php echo htmlspecialchars($schedule['name']); ?></span>
                        </span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary count-badge"><?php echo $count; ?></span>
                            <a href="index.php?controller=schedules&action=edit&id=<?php echo $sid; ?>" class="btn btn-sm btn-outline-secondary" title="Modifier">
                                <i class="fas fa-pen"></i>
                            </a>
                            <form method="post" action="index.php?controller=schedules&action=delete&id=<?php echo $sid; ?>" class="d-inline" onsubmit="return confirm('Supprimer cet horaire ? Les employés affectés devront d\'abord être retirés.')">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <small class="text-muted d-block mb-2 text-truncate">
                            <?php echo ($schedule['type'] === 'flexible') ? 'Flexible' : 'Fixe'; ?>
                            <?php if (!empty($schedule['description'])): ?>
                                — <?php echo htmlspecialchars($schedule['description']); ?>
                            <?php endif; ?>
                        </small>
                        <?php
                            $daysMap = ['monday'=>'Lun','tuesday'=>'Mar','wednesday'=>'Mer','thursday'=>'Jeu','friday'=>'Ven','saturday'=>'Sam','sunday'=>'Dim'];
                            $activeDays = [];
                            foreach ($daysMap as $dk => $dl) {
                                $s = $schedule[$dk . '_start'] ?? '';
                                $e = $schedule[$dk . '_end'] ?? '';
                                if ($s !== '' && $e !== '') {
                                    $s = preg_replace('/:00$/', '', $s) ?: $s;
                                    $e = preg_replace('/:00$/', '', $e) ?: $e;
                                    $activeDays[] = $dl . ' ' . $s . '-' . $e;
                                }
                            }
                            $breakInfo = '';
                            if (!empty($schedule['break_start']) && !empty($schedule['break_end'])) {
                                $bs = preg_replace('/:00$/', '', $schedule['break_start']) ?: $schedule['break_start'];
                                $be = preg_replace('/:00$/', '', $schedule['break_end']) ?: $schedule['break_end'];
                                $breakInfo = ' (pause ' . $bs . '-' . $be . ')';
                            }
                            $hoursSummary = '';
                            if (!empty($activeDays)) {
                                $hoursSummary = implode(', ', $activeDays) . $breakInfo;
                            }
                        ?>
                        <?php if ($hoursSummary !== ''): ?>
                            <small class="d-block mb-2 text-truncate text-gray-600">
                                <i class="fas fa-calendar-day me-1"></i><?php echo $hoursSummary; ?>
                            </small>
                        <?php endif; ?>

                        <div class="mb-2 emp-list" id="employees-<?php echo $sid; ?>">
                            <?php if ($count > 0): ?>
                                <?php foreach ($cardAssignments as $a): ?>
                                <span class="badge bg-primary me-1 mb-1 d-inline-flex align-items-center"
                                      data-assignment-id="<?php echo (int) ($a['id'] ?? 0); ?>"
                                      data-employee-id="<?php echo (int) ($a['employee_id'] ?? 0); ?>">
                                    <?php echo htmlspecialchars(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))); ?>
                                    <button type="button" class="btn-close btn-close-white btn-sm ms-1 remove-assignment"
                                            data-id="<?php echo (int) ($a['id'] ?? 0); ?>"
                                            title="Retirer" style="font-size:0.6rem;"></button>
                                </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted small">Aucun employé affecté</span>
                            <?php endif; ?>
                        </div>

                        <div class="card-status small" style="display:none;"></div>

                        <div class="input-group input-group-sm mt-2">
                            <select class="form-select employee-select" style="max-width:200px;" disabled>
                                <option value="">-- Charger --</option>
                            </select>
                            <input type="date" class="form-control start-date" value="<?php echo date('Y-m-d'); ?>" style="max-width:130px;">
                            <input type="date" class="form-control end-date" placeholder="Fin" style="max-width:130px;">
                            <button type="button" class="btn btn-outline-primary add-employee-btn" title="Affecter cet employé (+)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    'use strict';
    var csrfToken = <?php echo json_encode($csrf_token ?? ''); ?>;
    var cards = document.querySelectorAll('.schedule-card');
    var employeesCache = [];

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function setStatus(card, type, msg) {
        var el = card.querySelector('.card-status');
        if (!el) return;
        var cls = type === 'success' ? 'text-success' : type === 'error' ? 'text-danger' : type === 'info' ? 'text-info' : '';
        el.className = 'card-status small ' + cls;
        el.style.display = msg ? 'block' : 'none';
        el.textContent = msg || '';
    }

    function toFormData(obj) {
        var fd = new FormData();
        Object.keys(obj).forEach(function (k) {
            var v = obj[k];
            if (v === null || v === undefined) return;
            fd.append(k, String(v));
        });
        fd.append('csrf_token', csrfToken);
        return fd;
    }

    function parseJSONText(t) {
        try { return JSON.parse(t); } catch (e) { throw new Error('Réponse invalide du serveur'); }
    }

    function ajaxPOST(url, data) {
        return fetch(url, {
            method: 'POST',
            body: toFormData(data),
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.text(); }).then(parseJSONText);
    }

    function loadEmployees() {
        return fetch('index.php?controller=schedules&action=ajaxLoadEmployees', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.text(); })
            .then(parseJSONText)
            .then(function (data) {
                if (data.success) { employeesCache = data.employees || []; }
                populateAllDropdowns();
            })
            .catch(function () { populateAllDropdowns(); });
    }

    function employeeLabel(emp) {
        var label = emp.first_name + ' ' + emp.last_name;
        if (emp.employee_code) label = emp.employee_code + ' — ' + label;
        return label;
    }

    function populateDropdown(select) {
        select.disabled = false;
        select.innerHTML = '<option value="">-- Choisir --</option>';
        employeesCache.forEach(function (emp) {
            var opt = document.createElement('option');
            opt.value = emp.id;
            opt.textContent = employeeLabel(emp);
            select.appendChild(opt);
        });
    }

    function populateAllDropdowns() {
        cards.forEach(function (card) {
            populateDropdown(card.querySelector('.employee-select'));
        });
    }

    function updateCount(card, count) {
        var c = card.querySelector('.count-badge');
        if (c) c.textContent = count;
    }

    function emptyRowMessage() {
        var span = document.createElement('span');
        span.className = 'text-muted small';
        span.textContent = 'Aucun employé affecté';
        return span;
    }

    function renderBadge(a) {
        var span = document.createElement('span');
        span.className = 'badge bg-primary me-1 mb-1 d-inline-flex align-items-center';
        span.setAttribute('data-assignment-id', a.id);
        span.setAttribute('data-employee-id', a.employee_id);
        span.innerHTML = esc((a.first_name || '') + ' ' + (a.last_name || '')) +
            ' <button type="button" class="btn-close btn-close-white btn-sm ms-1 remove-assignment" data-id="' + a.id + '" title="Retirer" style="font-size:0.6rem;"></button>';
        return span;
    }

    function addBadge(card, a) {
        var list = card.querySelector('.emp-list');
        if (!list) return;
        var empty = list.querySelector('.text-muted');
        if (empty) empty.remove();
        list.appendChild(renderBadge(a));
    }

    function ensureEmpty(list) {
        if (!list.querySelector('.text-muted')) {
            list.appendChild(emptyRowMessage());
        }
    }

    function bindAdd(card) {
        var btn = card.querySelector('.add-employee-btn');
        var sel = card.querySelector('.employee-select');
        var start = card.querySelector('.start-date');
        var end = card.querySelector('.end-date');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var empId = sel.value;
            if (!empId) { setStatus(card, 'error', 'Sélectionner un employé.'); return; }
            if (!start.value) { setStatus(card, 'error', 'Saisir une date de début.'); return; }

            setStatus(card, 'info', 'Enregistrement…');
            btn.disabled = true;
            ajaxPOST('index.php?controller=schedules&action=ajaxAssign', {
                schedule_id: card.getAttribute('data-schedule-id'),
                employee_id: empId,
                start_date: start.value,
                end_date: end.value,
                notes: ''
            }).then(function (data) {
                btn.disabled = false;
                if (data.success) {
                    addBadge(card, data.assignment);
                    updateCount(card, data.count || 0);
                    sel.value = '';
                    setStatus(card, 'success', data.message || 'Employé affecté.');
                    setTimeout(function () { setStatus(card, '', ''); }, 3000);
                } else {
                    setStatus(card, 'error', data.message || 'Erreur.');
                }
            }).catch(function (e) {
                btn.disabled = false;
                setStatus(card, 'error', e.message || 'Erreur de communication.');
            });
        });
    }

    function bindRemove(card) {
        card.addEventListener('click', function (e) {
            var btn = e.target.closest('.remove-assignment');
            if (!btn) return;
            var id = btn.getAttribute('data-id');
            if (!id || !confirm('Retirer cette affectation ?')) return;

            var badge = btn.closest('[data-assignment-id]');
            var list = card.querySelector('.emp-list');
            setStatus(card, 'info', 'Suppression…');
            ajaxPOST('index.php?controller=schedules&action=ajaxRemoveAssignment', { id: id })
                .then(function (data) {
                    if (data.success) {
                        if (badge) badge.remove();
                        if (list && list.children.length === 0) { list.appendChild(emptyRowMessage()); }
                        updateCount(card, data.count || 0);
                        setStatus(card, 'success', data.message || 'Retiré.');
                        setTimeout(function () { setStatus(card, '', ''); }, 3000);
                    } else {
                        setStatus(card, 'error', data.message || 'Erreur.');
                    }
                }).catch(function (e) {
                    setStatus(card, 'error', e.message || 'Erreur de communication.');
                });
        });
    }

    function init() {
        if (!cards.length) return;
        loadEmployees();
        cards.forEach(function (card) {
            bindAdd(card);
            bindRemove(card);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>

