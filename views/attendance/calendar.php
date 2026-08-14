<?php
/**
 * Calendar view
 */
$year    = $year    ?? date('Y');
$month   = $month   ?? date('n');
$monthName = $monthName ?? date('F Y');
$calendar  = $calendar  ?? [];
$prevUrl   = $prevUrl   ?? '?month=' . (($month == 1) ? 12 : $month - 1) . '&year=' . (($month == 1) ? $year - 1 : $year);
$nextUrl   = $nextUrl   ?? '?month=' . (($month == 12) ? 1 : $month + 1) . '&year=' . (($month == 12) ? $year + 1 : $year);
$summary   = $summary   ?? ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'holiday' => 0, 'early' => 0];

$statusColor = [
    'present' => '#2fb344', 'late' => '#f59f00', 'absent' => '#e03131',
    'leave' => '#1c7ed6', 'holiday' => '#f59f00', 'early' => '#7048e8'
];
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h4 class="text-xl font-semibold text-gray-900"><i class="fas fa-calendar-alt text-blue-600 mr-2"></i>Calendrier de présence</h4>
        <div class="text-gray-500 text-sm">Visualisation mensuelle des pointages</div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="index.php?controller=attendance&action=index" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 shadow-sm font-medium"><i class="fas fa-list mr-1"></i>Liste</a>
        <a href="index.php?controller=reports&action=export&format=csv&start=<?= urlencode($year.'-'.$month.'-01') ?>&end=<?= urlencode($year.'-'.$month.'-'.date('t', mktime(0,0,0,$month,1,$year))) ?>" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium"><i class="fas fa-file-export mr-1"></i>Exporter</a>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
    <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <button class="p-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50" id="btnCalPrev" data-url="<?= htmlspecialchars($prevUrl) ?>"><i class="fas fa-chevron-left"></i></button>
            <h5 class="mb-0 font-semibold text-gray-900"><?= htmlspecialchars($monthName) ?></h5>
            <button class="p-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50" id="btnCalNext" data-url="<?= htmlspecialchars($nextUrl) ?>"><i class="fas fa-chevron-right"></i></button>
        </div>
        <div>
            <a href="index.php?controller=attendance&action=calendar&month=<?= date('n') ?>&year=<?= date('Y') ?>" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 border border-transparent font-medium text-sm">Aujourd'hui</a>
        </div>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-7 gap-px bg-gray-200 rounded-lg overflow-hidden">
            <div class="bg-gray-50 px-2 py-2 text-center font-semibold text-xs text-gray-500 uppercase tracking-wider">Lun</div>
            <div class="bg-gray-50 px-2 py-2 text-center font-semibold text-xs text-gray-500 uppercase tracking-wider">Mar</div>
            <div class="bg-gray-50 px-2 py-2 text-center font-semibold text-xs text-gray-500 uppercase tracking-wider">Mer</div>
            <div class="bg-gray-50 px-2 py-2 text-center font-semibold text-xs text-gray-500 uppercase tracking-wider">Jeu</div>
            <div class="bg-gray-50 px-2 py-2 text-center font-semibold text-xs text-gray-500 uppercase tracking-wider">Ven</div>
            <div class="bg-gray-50 px-2 py-2 text-center font-semibold text-xs text-gray-500 uppercase tracking-wider">Sam</div>
            <div class="bg-gray-50 px-2 py-2 text-center font-semibold text-xs text-gray-500 uppercase tracking-wider">Dim</div>
            <?php foreach ($calendar as $week): foreach ($week as $cell): ?>
                <?php if (empty($cell['day'])): ?>
                    <div class="bg-gray-50 min-h-[90px]"></div>
                <?php else:
                    $color = !empty($cell['status']) ? ($statusColor[$cell['status']] ?? '#e6e9f0') : '#e6e9f0';
                    $details = $cell['details'] ?? '<p class="text-gray-500">Aucune donnée.</p>';
                ?>
                    <div class="bg-white min-h-[90px] p-2 flex flex-col gap-1 hover:bg-blue-50 transition-colors cursor-pointer" data-date="<?= htmlspecialchars($cell['date']) ?>" data-modal="dayModal"
                         data-details="<?= htmlspecialchars($details) ?>">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-sm"><?= $cell['day'] ?></span>
                            <?php if (!empty($cell['status'])): ?><span class="w-2 h-2 rounded-full inline-block flex-shrink-0" style="background:<?= $color ?>"></span><?php endif; ?>
                        </div>
                        <?php if (!empty($cell['count'])): ?><div class="text-xs px-2 py-0.5 rounded text-center bg-gray-100 text-gray-600"><?= (int)$cell['count'] ?> pointage(s)</div><?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; endforeach; ?>
        </div>
    </div>
</div>

<!-- Legend -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6 flex flex-wrap items-center gap-4">
        <span class="inline-flex items-center gap-1.5 text-sm"><span class="w-3 h-3 rounded-full inline-block" style="background:#2fb344"></span>Présent (<?= (int)$summary['present'] ?>)</span>
        <span class="inline-flex items-center gap-1.5 text-sm"><span class="w-3 h-3 rounded-full inline-block" style="background:#f59f00"></span>Retard (<?= (int)$summary['late'] ?>)</span>
        <span class="inline-flex items-center gap-1.5 text-sm"><span class="w-3 h-3 rounded-full inline-block" style="background:#e03131"></span>Absent (<?= (int)$summary['absent'] ?>)</span>
        <span class="inline-flex items-center gap-1.5 text-sm"><span class="w-3 h-3 rounded-full inline-block" style="background:#1c7ed6"></span>Congé (<?= (int)$summary['leave'] ?>)</span>
        <span class="inline-flex items-center gap-1.5 text-sm"><span class="w-3 h-3 rounded-full inline-block" style="background:#f59f00"></span>Jour férié (<?= (int)$summary['holiday'] ?>)</span>
        <span class="inline-flex items-center gap-1.5 text-sm"><span class="w-3 h-3 rounded-full inline-block" style="background:#7048e8"></span>Départ anticipé (<?= (int)$summary['early'] ?>)</span>
    </div>
</div>

<!-- Day detail modal -->
<div class="fixed inset-0 z-50 hidden" id="dayModal">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" id="dayModalOverlay"></div>
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 w-full max-w-md relative z-10">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h5 class="text-lg font-semibold text-gray-900" id="dayModalTitle">Détails</h5>
                <button type="button" class="p-2 rounded-lg hover:bg-gray-100 text-gray-400 text-2xl leading-none" id="btnCloseDayModal">&times;</button>
            </div>
            <div class="p-6" id="dayModalBody"></div>
            <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-end">
                <button type="button" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50" id="btnCloseDayModal2">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('dayModal');
    var overlay = document.getElementById('dayModalOverlay');
    var body = document.getElementById('dayModalBody');

    function openModal() { modal.classList.remove('hidden'); }
    function closeModal() { modal.classList.add('hidden'); }

    document.getElementById('btnCloseDayModal').addEventListener('click', closeModal);
    document.getElementById('btnCloseDayModal2').addEventListener('click', closeModal);
    if (overlay) overlay.addEventListener('click', closeModal);

    document.querySelectorAll('[data-modal="dayModal"]').forEach(function(el) {
        el.addEventListener('click', function() {
            body.innerHTML = this.dataset.details || '<p class="text-gray-500">Aucune donnée.</p>';
            openModal();
        });
    });

    document.getElementById('btnCalPrev').addEventListener('click', function() { window.location.href = this.dataset.url; });
    document.getElementById('btnCalNext').addEventListener('click', function() { window.location.href = this.dataset.url; });
});
</script>
