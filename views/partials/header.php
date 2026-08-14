<?php
/**
 * Header - Attendance Pro
 * Top navigation bar
 */
$pageTitle = $pageTitle ?? 'Dashboard';
$authUser = $auth_user ?? [];
$authName = $auth_name ?? 'Admin';
$authRole = $auth_role ?? 'Administrator';
?>
<header class="sticky top-0 z-30 h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 sm:px-6 shadow-sm">
    <div class="flex items-center gap-4">
        <button class="p-2 rounded-lg hover:bg-gray-100 text-gray-600 lg:hidden" id="sidebarToggle" type="button">
            <i class="fa-solid fa-bars text-lg"></i>
        </button>
        <button class="p-2 rounded-lg hover:bg-gray-100 text-gray-600 hidden lg:flex" id="sidebarCollapseToggle" type="button" title="Masquer/Afficher le menu">
            <i class="fa-solid fa-angles-left text-lg" id="sidebarCollapseIcon"></i>
        </button>
        <img src="assets/images/logoPouinteuse.jpg" alt="Logo" class="h-8 w-auto hidden sm:block">
        <div class="font-semibold text-gray-900">
            <h2 class="text-base"><?= htmlspecialchars($pageTitle) ?></h2>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <div class="relative">
            <button class="relative w-9 h-9 rounded-full bg-gray-100 text-gray-600 hover:bg-blue-50 hover:text-blue-600 flex items-center justify-center" type="button" id="notificationBtn" title="Notifications">
                <i class="fa-solid fa-bell"></i>
                <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full hidden" id="notificationBadge"></span>
            </button>
            <div class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50" id="notificationDropdown">
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <span class="font-semibold text-gray-900">Notifications</span>
                    <button class="text-xs text-blue-600 hover:text-blue-700" id="btnMarkAllRead">Tout marquer comme lu</button>
                </div>
                <div class="max-h-96 overflow-y-auto" id="notificationList">
                    <div class="px-4 py-6 text-center text-gray-500 text-sm">Chargement...</div>
                </div>
            </div>
        </div>

        <div class="relative">
            <button class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-100" type="button" id="userDropdownBtn">
                <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-medium flex-shrink-0">
                    <?= htmlspecialchars(strtoupper(substr($authName, 0, 2))) ?>
                </div>
                <div class="hidden md:flex flex-col">
                    <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($authName) ?></span>
                    <span class="text-xs text-gray-500"><?= htmlspecialchars($authRole) ?></span>
                </div>
                <i class="fa-solid fa-chevron-down text-xs text-gray-400"></i>
            </button>
            <div class="hidden absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50" id="userDropdownMenu">
                <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Account</div>
                <a href="index.php?controller=profile&action=index" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                    <i class="fa-solid fa-user w-4"></i><?= __('profile') ?>
                </a>
                <a href="index.php?controller=settings&action=index" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                    <i class="fa-solid fa-gear w-4"></i><?= __('settings_link') ?>
                </a>
                <div class="border-t border-gray-200 my-1"></div>
                <a href="login.php?logout=1" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                    <i class="fa-solid fa-arrow-right-from-bracket w-4"></i><?= __('logout') ?>
                </a>
            </div>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarCollapseToggle = document.getElementById('sidebarCollapseToggle');
    const sidebarCollapseIcon = document.getElementById('sidebarCollapseIcon');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (!sidebar || !mainContent) return;

    // Load saved state
    const collapsed = localStorage.getItem('sidebar_collapsed') === 'true';
    applySidebarState(collapsed);

    // Mobile toggle
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            const isHidden = sidebar.classList.contains('-translate-x-full');
            if (isHidden) {
                sidebar.classList.remove('-translate-x-full');
                if (sidebarOverlay) sidebarOverlay.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                if (sidebarOverlay) sidebarOverlay.classList.add('hidden');
            }
        });
    }

    // Desktop collapse
    if (sidebarCollapseToggle) {
        sidebarCollapseToggle.addEventListener('click', function() {
            const isCollapsed = sidebar.classList.contains('lg:-translate-x-full');
            applySidebarState(!isCollapsed);
        });
    }

    // Overlay click closes sidebar on mobile
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
        });
    }

    function applySidebarState(collapsed) {
        if (collapsed) {
            sidebar.classList.add('-translate-x-full', 'lg:-translate-x-full');
            sidebar.classList.remove('lg:translate-x-0');
            mainContent.classList.remove('lg:ml-64');
            mainContent.classList.add('lg:ml-0');
            if (sidebarCollapseIcon) {
                sidebarCollapseIcon.classList.remove('fa-angles-left');
                sidebarCollapseIcon.classList.add('fa-angles-right');
            }
            localStorage.setItem('sidebar_collapsed', 'true');
        } else {
            sidebar.classList.remove('-translate-x-full', 'lg:-translate-x-full');
            sidebar.classList.add('lg:translate-x-0');
            mainContent.classList.remove('lg:ml-0');
            mainContent.classList.add('lg:ml-64');
            if (sidebarCollapseIcon) {
                sidebarCollapseIcon.classList.remove('fa-angles-right');
                sidebarCollapseIcon.classList.add('fa-angles-left');
            }
            localStorage.setItem('sidebar_collapsed', 'false');
        }
    }

    const userBtn = document.getElementById('userDropdownBtn');
    const userMenu = document.getElementById('userDropdownMenu');
    if (userBtn && userMenu) {
        userBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenu.classList.toggle('hidden');
        });
        document.addEventListener('click', function() {
            userMenu.classList.add('hidden');
        });
    }

    const notifBtn = document.getElementById('notificationBtn');
    const notifDropdown = document.getElementById('notificationDropdown');
    const notifList = document.getElementById('notificationList');
    const notifBadge = document.getElementById('notificationBadge');
    const btnMarkAllRead = document.getElementById('btnMarkAllRead');

    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notifDropdown.classList.toggle('hidden');
            if (!notifDropdown.classList.contains('hidden')) {
                loadNotifications();
            }
        });

        document.addEventListener('click', function() {
            notifDropdown.classList.add('hidden');
        });

        if (btnMarkAllRead) {
            btnMarkAllRead.addEventListener('click', function(e) {
                e.stopPropagation();
                fetch('index.php?controller=api&action=markNotificationsRead', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        loadNotifications();
                        if (notifBadge) notifBadge.classList.add('hidden');
                    }
                });
            });
        }
    }

    function loadNotifications() {
        if (!notifList) return;
        notifList.innerHTML = '<div class="px-4 py-6 text-center text-gray-500 text-sm">Chargement...</div>';

        fetch('index.php?controller=api&action=notifications', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.data || !data.data.length) {
                notifList.innerHTML = '<div class="px-4 py-6 text-center text-gray-500 text-sm">Aucune notification</div>';
                if (notifBadge) notifBadge.classList.add('hidden');
                return;
            }

            if (notifBadge) notifBadge.classList.remove('hidden');

            let html = '';
            data.data.forEach(function(n) {
                const icons = {
                    'missing_punch': '<i class="fa-solid fa-user-clock text-orange-600"></i>',
                    'overtime': '<i class="fa-solid fa-clock text-purple-600"></i>',
                    'delay': '<i class="fa-solid fa-triangle-exclamation text-red-600"></i>',
                    'info': '<i class="fa-solid fa-info-circle text-blue-600"></i>'
                };
                const icon = icons[n.type] || icons['info'];
                html += '<div class="px-4 py-3 border-b border-gray-100 hover:bg-gray-50">' +
                    '<div class="flex items-start gap-3">' +
                    '<div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0">' + icon + '</div>' +
                    '<div class="flex-1 min-w-0">' +
                    '<div class="text-sm font-medium text-gray-900">' + escapeHtml(n.title) + '</div>' +
                    '<div class="text-xs text-gray-500 mt-0.5">' + escapeHtml(n.body) + '</div>' +
                    '<div class="text-xs text-gray-400 mt-1">' + escapeHtml(n.created || '') + '</div>' +
                    '</div></div></div>';
            });
            notifList.innerHTML = html;
        })
        .catch(() => {
            notifList.innerHTML = '<div class="px-4 py-6 text-center text-red-500 text-sm">Erreur lors du chargement</div>';
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
