<?php
/** Vue : import CSV d'employés. */
$csrf_token = $csrf_token ?? '';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h4 class="text-xl font-semibold text-gray-900">Importer des employés</h4>
    <a href="index.php?controller=employees&action=index" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Retour</a>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6">
        <p class="mb-4">Chargez un fichier CSV avec les colonnes suivantes (dans l'ordre) :</p>
        <code class="block bg-gray-50 p-3 rounded-lg border border-gray-200 text-sm mb-4">employee_code, first_name, last_name, phone, department_id, hire_date, badge_id, badge_code</code>

        <form method="post" action="index.php?controller=employees&action=import" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Fichier CSV</label>
                <input type="file" name="csv_file" accept=".csv" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium">Importer</button>
        </form>
    </div>
</div>
