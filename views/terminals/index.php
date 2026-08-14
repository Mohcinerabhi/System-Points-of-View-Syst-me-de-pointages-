<?php
/**
 * Terminal management
 */
$terminals   = $terminals   ?? [];
$syncLogs    = $syncLogs    ?? [];
$editTerminal = $editTerminal ?? null;
$csrf_token   = $csrf_token   ?? '';

function termStatus($s) {
    $m = ['online' => ['En ligne','bg-green-100 text-green-800'], 'offline' => ['Hors ligne','bg-gray-100 text-gray-800'], 'error' => ['Erreur','bg-red-100 text-red-800']];
    $v = $m[$s] ?? ['Inconnu','bg-gray-100 text-gray-800'];
    return '<span class="w-2 h-2 rounded-full inline-block '.($s === 'online' ? 'bg-green-500' : ($s === 'error' ? 'bg-red-500' : 'bg-gray-400')).'"></span> <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium '.$v[1].'">'.$v[0].'</span>';
}
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h4 class="text-xl font-semibold text-gray-900"><i class="fas fa-server text-blue-600 mr-2"></i>Terminaux</h4>
        <div class="text-gray-500 text-sm">Gestion des terminaux biométriques Hikvision</div>
    </div>
    <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium" id="btnOpenTerminalModal">
        <i class="fas fa-plus mr-1"></i>Nouveau terminal
    </button>
</div>

<?php if (empty($terminals)): ?>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200"><div class="p-6 text-center text-gray-500 py-5">Aucun terminal configuré</div></div>
<?php else: ?>
<div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($terminals as $t): ?>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" data-terminal-id="<?= (int)$t['id'] ?>">
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="w-9 h-9 rounded-lg flex items-center justify-center bg-blue-100 text-blue-600"><i class="fas fa-fingerprint"></i></span>
                    <div>
                        <div class="font-bold"><?= htmlspecialchars($t['name']) ?></div>
                         <small class="text-gray-500"><?= htmlspecialchars($t['model'] ?? '-') ?></small>
                    </div>
                </div>
                <?= termStatus($t['connection_status'] ?? 'offline') ?>
            </div>

            <div class="mb-2"><i class="fas fa-network-wired text-gray-400 mr-2"></i><strong><?= htmlspecialchars($t['ip_address']) ?></strong>:<?= (int)($t['port'] ?? 80) ?></div>
            <div class="mb-2"><i class="fas fa-sync text-gray-400 mr-2"></i>Dernière synchro : <strong><?= !empty($t['last_sync']) ? htmlspecialchars($t['last_sync']) : 'Jamais' ?></strong></div>
            <div class="mb-3"><i class="fas fa-circle <?= ($t['sync_enabled'] ?? 0) ? 'text-green-600' : 'text-gray-400' ?> mr-2"></i>Sync auto : <?= ($t['sync_enabled'] ?? 0) ? 'Activée' : 'Désactivée' ?></div>

            <div class="flex flex-wrap items-center gap-2">
                <button class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm font-medium btn-test-connection" data-terminal-id="<?= $t['id'] ?>" data-terminal-name="<?= htmlspecialchars($t['name']) ?>">
                    <i class="fas fa-plug mr-1"></i>Tester
                </button>
                <button class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium btn-sync-users" data-terminal-id="<?= $t['id'] ?>" data-terminal-name="<?= htmlspecialchars($t['name']) ?>">
                    <i class="fas fa-users mr-1"></i>Users
                </button>
                <button class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium btn-sync-attendance" data-terminal-id="<?= $t['id'] ?>" data-terminal-name="<?= htmlspecialchars($t['name']) ?>">
                    <i class="fas fa-clock mr-1"></i>Pointages
                </button>
                <button class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium btn-sync-photos" data-terminal-id="<?= $t['id'] ?>" data-terminal-name="<?= htmlspecialchars($t['name']) ?>">
                    <i class="fas fa-image mr-1"></i>Photos
                </button>
            </div>
            <hr class="my-3 border-gray-200">
            <div class="flex items-center gap-2">
                <a href="index.php?controller=terminals&action=view&id=<?= $t['id'] ?>" class="p-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm" title="Détails"><i class="fas fa-eye"></i></a>
                <a href="index.php?controller=terminals&action=edit&id=<?= $t['id'] ?>" class="p-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm" title="Modifier"><i class="fas fa-edit"></i></a>
                <button class="p-2 border border-red-300 text-red-700 rounded-lg hover:bg-red-50 text-sm" title="Supprimer"
                        onclick="if(confirmDelete('Supprimer ce terminal ?')) document.getElementById('delterm-<?= $t['id'] ?>').submit();">
                    <i class="fas fa-trash"></i>
                </button>
                <form id="delterm-<?= $t['id'] ?>" method="post" action="index.php?controller=terminals&action=delete&id=<?= $t['id'] ?>" style="display:none">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Create / Edit Terminal Modal -->
<div class="fixed inset-0 z-50 hidden" id="terminalModal">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" id="terminalModalOverlay"></div>
        <div class="bg-white rounded-lg shadow-lg border border-gray-200 w-full max-w-2xl relative z-10 max-h-[90vh] flex flex-col">
            <form method="post" action="<?= $editTerminal ? 'index.php?controller=terminals&action=edit&id=' . $editTerminal['id'] : 'index.php?controller=terminals&action=create' ?>" id="terminalForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <?php if ($editTerminal): ?><input type="hidden" name="id" value="<?= $editTerminal['id'] ?>"><?php endif; ?>
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h5 class="text-lg font-semibold text-gray-900"><i class="fas fa-server mr-2"></i><?= $editTerminal ? 'Modifier' : 'Nouveau' ?> terminal</h5>
                    <button type="button" class="p-2 rounded-lg hover:bg-gray-100 text-gray-400 text-2xl leading-none" id="btnCloseTerminalModal">&times;</button>
                </div>
                <div class="p-6 overflow-y-auto flex-1">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nom</label>
                            <input type="text" name="name" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($editTerminal['name'] ?? '') ?>" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Modèle</label>
                                 <input type="text" name="model" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($editTerminal['model'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Adresse IP</label>
                            <input type="text" name="ip_address" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($editTerminal['ip_address'] ?? '') ?>" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                            <input type="number" name="port" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= (int)($editTerminal['port'] ?? 80) ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">N° série</label>
                            <input type="text" name="serial_number" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($editTerminal['serial_number'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Utilisateur</label>
                            <input type="text" name="username" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= htmlspecialchars($editTerminal['username'] ?? 'admin') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                            <input type="password" name="password" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea name="notes" rows="2" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?= htmlspecialchars($editTerminal['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-end gap-3">
                    <button type="button" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50" id="btnCancelTerminalModal">Annuler</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium"><i class="fas fa-save mr-1"></i>Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.content;
        return '';
    }

    function updateTerminalStatus(terminalId) {
        const card = document.querySelector('.grid > div[data-terminal-id="' + terminalId + '"]');
        if (!card) return;
        const statusEl = card.querySelector('.rounded-full');
        if (!statusEl) return;
        
        fetch('index.php?controller=terminals&action=status&id=' + terminalId, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.status) {
                const colors = {
                    'online': 'bg-green-500',
                    'offline': 'bg-gray-400',
                    'error': 'bg-red-500'
                };
                const labels = {
                    'online': 'En ligne',
                    'offline': 'Hors ligne',
                    'error': 'Erreur'
                };
                const badgeClasses = {
                    'online': 'bg-green-100 text-green-800',
                    'offline': 'bg-gray-100 text-gray-800',
                    'error': 'bg-red-100 text-red-800'
                };
                
                statusEl.className = 'w-2 h-2 rounded-full inline-block ' + (colors[data.status] || 'bg-gray-400');
                const badge = statusEl.nextElementSibling;
                if (badge && badge.classList.contains('inline-flex')) {
                    badge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' + (badgeClasses[data.status] || 'bg-gray-100 text-gray-800');
                    badge.textContent = labels[data.status] || 'Inconnu';
                }
            }
        })
        .catch(() => {});
    }

    document.querySelectorAll('.btn-test-connection').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const terminalId = this.getAttribute('data-terminal-id');
            const terminalName = this.getAttribute('data-terminal-name');
            const originalText = this.innerHTML;
            
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Test...';
            
            fetch('index.php?controller=terminals&action=testConnection&id=' + terminalId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    App.toast('Connexion réussie vers ' + terminalName, 'success');
                } else {
                    App.toast('Échec de connexion: ' + (data.message || 'Terminal inaccessible'), 'error');
                }
                updateTerminalStatus(terminalId);
            })
            .catch(() => {
                App.toast('Erreur lors du test de connexion', 'error');
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = originalText;
            });
        });
    });

    document.querySelectorAll('.btn-sync-users').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const terminalId = this.getAttribute('data-terminal-id');
            const terminalName = this.getAttribute('data-terminal-name');
            const originalText = this.innerHTML;
            
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Sync...';
            
            fetch('index.php?controller=terminals&action=syncUsers&id=' + terminalId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    App.toast('Utilisateurs synchronisés: ' + (data.synced || 0) + ' ajoutés, ' + (data.failed || 0) + ' échoués', 'success');
                } else {
                    App.toast('Échec de synchronisation: ' + (data.message || 'Erreur inconnue'), 'error');
                }
                updateTerminalStatus(terminalId);
            })
            .catch(() => {
                App.toast('Erreur lors de la synchronisation', 'error');
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = originalText;
            });
        });
    });

     document.querySelectorAll('.btn-sync-attendance').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const terminalId = this.getAttribute('data-terminal-id');
            const terminalName = this.getAttribute('data-terminal-name');
            const originalText = this.innerHTML;
            
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Sync...';
            
            fetch('index.php?controller=terminals&action=syncAttendance&id=' + terminalId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    App.toast('Pointages synchronisés depuis ' + terminalName, 'success');
                } else {
                    App.toast('Échec de synchronisation: ' + (data.message || 'Erreur inconnue'), 'error');
                }
                updateTerminalStatus(terminalId);
            })
            .catch(() => {
                App.toast('Erreur lors de la synchronisation', 'error');
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = originalText;
            });
        });
    });

    document.querySelectorAll('.btn-sync-photos').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const terminalId = this.getAttribute('data-terminal-id');
            const terminalName = this.getAttribute('data-terminal-name');
            const originalText = this.innerHTML;

            if (!confirm('Importer les photos de profil des employés depuis ' + terminalName + ' ?')) {
                return;
            }

            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Import...';

            fetch('index.php?controller=terminals&action=importPhotos&id=' + terminalId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if ((data.synced || 0) > 0) {
                        App.toast('Photos importées: ' + (data.synced || 0) + ' importée(s)/mise(s) à jour, ' + (data.skipped || 0) + ' ignorée(s), ' + (data.failed || 0) + ' erreur(s)', 'success');
                    } else {
                        const hint = (data.skipped || 0) > 0
                            ? ' Le terminal ne fournit peut-être pas de photos d\'employés (endpoint ISAPI non supporté).'
                            : '';
                        App.toast('Aucune photo importée (' + (data.skipped || 0) + ' ignorée(s), ' + (data.failed || 0) + ' erreur(s)).' + hint, 'info');
                    }
                } else {
                    App.toast('Échec de l\'import des photos: ' + (data.message || 'Erreur inconnue'), 'error');
                }
                updateTerminalStatus(terminalId);
            })
            .catch(() => {
                App.toast('Erreur lors de l\'import des photos', 'error');
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = originalText;
            });
        });
    });

    const modal = document.getElementById('terminalModal');
    const overlay = document.getElementById('terminalModalOverlay');
    const btnOpen = document.getElementById('btnOpenTerminalModal');
    const btnClose = document.getElementById('btnCloseTerminalModal');
    const btnCancel = document.getElementById('btnCancelTerminalModal');

    function openModal() { modal.classList.remove('hidden'); }
    function closeModal() { modal.classList.add('hidden'); }

    if (btnOpen) btnOpen.addEventListener('click', openModal);
    if (btnClose) btnClose.addEventListener('click', closeModal);
    if (btnCancel) btnCancel.addEventListener('click', closeModal);
    if (overlay) overlay.addEventListener('click', closeModal);
});
</script>
