<?php
/**
 * Vue : création d'un terminal.
 */
$terminal = $terminal ?? [];
$errors   = $errors   ?? [];
$csrf_token = $csrf_token ?? '';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h4 class="text-xl font-semibold text-gray-900">Nouveau terminal</h4>
    <a href="index.php?controller=terminals&action=index" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Retour</a>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <form method="post" action="index.php?controller=terminals&action=create">
        <div class="p-6">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-600">*</span></label>
                <input type="text" name="name" value="<?= htmlspecialchars($terminal['name'] ?? '') ?>" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <?php if (!empty($errors['name'])): ?><span class="text-red-600 text-sm mt-1 block"><?= htmlspecialchars($errors['name']) ?></span><?php endif; ?>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Adresse IP <span class="text-red-600">*</span></label>
                <input type="text" name="ip_address" value="<?= htmlspecialchars($terminal['ip_address'] ?? '') ?>" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <?php if (!empty($errors['ip_address'])): ?><span class="text-red-600 text-sm mt-1 block"><?= htmlspecialchars($errors['ip_address']) ?></span><?php endif; ?>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                <input type="number" name="port" value="<?= htmlspecialchars($terminal['port'] ?? 80) ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nom d'utilisateur <span class="text-red-600">*</span></label>
                <input type="text" name="username" value="<?= htmlspecialchars($terminal['username'] ?? '') ?>" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <?php if (!empty($errors['username'])): ?><span class="text-red-600 text-sm mt-1 block"><?= htmlspecialchars($errors['username']) ?></span><?php endif; ?>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe <span class="text-red-600">*</span></label>
                <input type="password" name="password" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <?php if (!empty($errors['password'])): ?><span class="text-red-600 text-sm mt-1 block"><?= htmlspecialchars($errors['password']) ?></span><?php endif; ?>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Numéro de série</label>
                <input type="text" name="serial_number" value="<?= htmlspecialchars($terminal['serial_number'] ?? '') ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Modèle</label>
                <input type="text" name="model" value="<?= htmlspecialchars($terminal['model'] ?? '') ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Adresse MAC</label>
                <input type="text" name="mac_address" value="<?= htmlspecialchars($terminal['mac_address'] ?? '') ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="bc:9b:5e:13:05:61">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Port RTSP</label>
                <input type="number" name="rtsp_port" value="<?= (int)($terminal['rtsp_port'] ?? 554) ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Protocole</label>
                <select name="protocol" id="protocol_select" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">-- Par défaut (ISAPI) --</option>
                </select>
            </div>
            <div id="isup_fields" class="hidden" style="display:none">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Adresse IP serveur ISUP</label>
                    <input type="text" name="isup_server_ip" value="<?= htmlspecialchars($terminal['isup_server_ip'] ?? '') ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Port serveur ISUP</label>
                    <input type="number" name="isup_server_port" value="<?= (int)($terminal['isup_server_port'] ?? 8000) ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">ID appareil ISUP</label>
                    <input type="text" name="isup_device_id" value="<?= htmlspecialchars($terminal['isup_device_id'] ?? '') ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Heure de l'appareil</label>
                <input type="text" name="device_time" value="<?= htmlspecialchars($terminal['device_time'] ?? '') ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="jj-mm-aaaaT00:00:00+00:00">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Fuseau horaire</label>
                <input type="text" name="timezone" value="<?= htmlspecialchars($terminal['timezone'] ?? '') ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="+00:00">
            </div>
            <div class="mb-4 flex items-center gap-2">
                <input type="checkbox" name="sync_enabled" value="1" id="sync_enabled" <?= !empty($terminal['sync_enabled']) ? 'checked' : '' ?> class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <label for="sync_enabled" class="text-sm font-medium text-gray-700">Synchronisation activée</label>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                <select name="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="active" <?= (($terminal['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Actif</option>
                    <option value="inactive" <?= (($terminal['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactif</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?= htmlspecialchars($terminal['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-end">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium">Enregistrer</button>
        </div>
    </form>
</div>
<script>
(function(){
    var sel = document.getElementById('protocol_select');
    var box = document.getElementById('isup_fields');
    if (!sel || !box) return;
    function toggle(){
        if (sel.value === 'ISUP' || sel.value === 'EHome') {
            box.classList.remove('hidden');
        } else {
            box.classList.add('hidden');
        }
    }
    toggle();
    sel.addEventListener('change', toggle);
})();
</script>
