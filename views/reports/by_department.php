<?php
/**
 * Vue : rapport par département.
 */
$department = $department ?? null;
$start = $start ?? date('Y-m-01');
$end   = $end   ?? date('Y-m-t');
$rows  = $rows  ?? [];
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h4 class="text-xl font-semibold text-gray-900">Rapport par département<?= $department ? ' &mdash; ' . htmlspecialchars($department['name']) : '' ?></h4>
    <a href="index.php?controller=reports&action=index" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Retour</a>
</div>

<p class="text-gray-600 mb-4">Période : <?= htmlspecialchars($start) ?> &rarr; <?= htmlspecialchars($end) ?></p>
<?php include __DIR__ . '/_table.php'; ?>
