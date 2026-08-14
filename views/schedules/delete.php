<?php
/** @var array $schedule */
/** @var string $csrf_token */
$schedule = $schedule ?? [];
$csrf_token = $csrf_token ?? '';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h4 class="text-xl font-semibold text-gray-900">Supprimer l'horaire</h4>
    <a href="index.php?controller=schedules&action=index" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Retour</a>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6">
        <p class="text-gray-700 mb-4">Êtes-vous sûr de vouloir supprimer l'horaire <strong><?= htmlspecialchars($schedule['name'] ?? '') ?></strong> ?</p>
        <p class="text-red-600 text-sm mb-6">Cette action est irréversible.</p>

        <form method="post" action="index.php?controller=schedules&action=delete&id=<?= (int)($schedule['id'] ?? 0) ?>" onsubmit="return confirm('Supprimer définitivement cet horaire ?')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="flex items-center gap-3">
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 shadow-sm font-medium">Oui, supprimer</button>
                <a href="index.php?controller=schedules&action=index" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Annuler</a>
            </div>
        </form>
    </div>
</div>
