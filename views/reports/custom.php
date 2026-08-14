<?php
/**
 * Vue : rapport personnalisé.
 */
$start = $start ?? date('Y-m-01');
$end   = $end   ?? date('Y-m-t');
$rows  = $rows  ?? [];
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h4 class="text-xl font-semibold text-gray-900">Rapport personnalisé</h4>
    <a href="index.php?controller=reports&action=index" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Retour</a>
</div>

<form method="get" action="index.php" class="flex flex-wrap items-end gap-3 mb-4">
    <input type="hidden" name="controller" value="reports">
    <input type="hidden" name="action" value="custom">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Du</label>
        <input type="date" name="start" value="<?= htmlspecialchars($start) ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Au</label>
        <input type="date" name="end" value="<?= htmlspecialchars($end) ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
    </div>
    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium">Afficher</button>
    <a href="index.php?controller=reports&action=export&format=csv&start=<?= urlencode($start) ?>&end=<?= urlencode($end) ?>" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Export CSV</a>
</form>

<?php include __DIR__ . '/_table.php'; ?>
