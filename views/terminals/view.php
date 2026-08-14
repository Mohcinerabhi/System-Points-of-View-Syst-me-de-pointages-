<?php
/**
 * Terminal detail view
 */
$terminal = $terminal ?? [];
$csrf_token = $csrf_token ?? '';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h4 class="text-xl font-semibold text-gray-900"><i class="fas fa-server text-blue-600 mr-2"></i>Détail du terminal</h4>
        <div class="text-gray-500 text-sm"><?= htmlspecialchars($terminal['name'] ?? '') ?></div>
    </div>
    <div class="flex items-center gap-2">
        <a href="index.php?controller=terminals&action=index" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 shadow-sm font-medium"><i class="fas fa-arrow-left mr-1"></i>Retour</a>
    </div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Nom</label>
                <p class="text-gray-900 font-medium"><?= htmlspecialchars($terminal['name'] ?? '') ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Modèle</label>
                <p class="text-gray-900"><?= htmlspecialchars($terminal['model'] ?? '-') ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Adresse MAC</label>
                <p class="text-gray-900 font-medium"><?= htmlspecialchars($terminal['mac_address'] ?? '-') ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Adresse IP</label>
                <p class="text-gray-900"><?= htmlspecialchars($terminal['ip_address'] ?? '') ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Port</label>
                <p class="text-gray-900"><?= (int)($terminal['port'] ?? 80) ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Port RTSP</label>
                <p class="text-gray-900"><?= (int)($terminal['rtsp_port'] ?? 554) ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Utilisateur</label>
                <p class="text-gray-900"><?= htmlspecialchars($terminal['username'] ?? '') ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">N° série</label>
                <p class="text-gray-900"><?= htmlspecialchars($terminal['serial_number'] ?? '-') ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Statut</label>
                <p class="text-gray-900"><?= htmlspecialchars($terminal['connection_status'] ?? 'offline') ?></p>
            </div>
            <div class="md:col-span-2">
                <div class="bg-blue-50 border border-blue-200 rounded-md p-3">
                    <p class="text-sm text-blue-700">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Connexion ISAPI active</strong> — Informations du terminal et users accessibles via HTTP.
                    </p>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Sync auto</label>
                <p class="text-gray-900"><?= !empty($terminal['sync_enabled']) ? 'Activée' : 'Désactivée' ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Dernière synchro</label>
                <p class="text-gray-900"><?= !empty($terminal['last_sync']) ? htmlspecialchars($terminal['last_sync']) : 'Jamais' ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Heure de l'appareil</label>
                <p class="text-gray-900"><?= !empty($terminal['device_time']) ? htmlspecialchars((new DateTime($terminal['device_time']))->format('d-m-Y\TH:i:sP')) : '-' ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Fuseau horaire</label>
                <p class="text-gray-900"><?= htmlspecialchars($terminal['timezone'] ?? '-') ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Créé le</label>
                <p class="text-gray-900"><?= htmlspecialchars($terminal['created_at'] ?? '') ?></p>
            </div>
            <?php if (!empty($terminal['notes'])): ?>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-500 mb-1">Notes</label>
                    <p class="text-gray-900"><?= nl2br(htmlspecialchars($terminal['notes'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>