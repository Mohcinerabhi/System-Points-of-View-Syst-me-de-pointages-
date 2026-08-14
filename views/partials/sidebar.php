<?php
/**
 * Sidebar - Attendance Pro
 * Modern dark sidebar with navigation
 */
$activeMenu = $activeMenu ?? '';
$authUser = $auth_user ?? [];
$authName = $auth_name ?? '';
$authRole = $auth_role ?? '';
?>
<aside class="fixed inset-y-0 left-0 w-64 bg-gray-900 text-white flex flex-col z-30 transform -translate-x-full lg:translate-x-0 transition-transform duration-300" id="sidebar">
    <div class="flex items-center gap-3 p-4 border-b border-gray-800">
        <img src="assets/images/logoPouinteuse.jpg" alt="Logo" class="h-9 w-auto rounded">
        <div class="flex flex-col">
            <span class="font-bold text-lg">AttendPro</span>
            <small class="text-gray-400 text-xs">Attendance Management</small>
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto py-4">
        <div class="px-4 mb-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Main Menu</div>

<a href="index.php?controller=dashboard&action=index" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white transition-colors <?= $activeMenu === 'dashboard' ? 'bg-gray-800 text-white border-r-4 border-blue-500' : '' ?>">
                <span class="w-5 h-5 flex items-center justify-center"><i class="fa-solid fa-gauge-high"></i></span>
                <span><?= __('dashboard') ?></span>
            </a>

<a href="index.php?controller=employees&action=index" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white transition-colors <?= $activeMenu === 'employees' ? 'bg-gray-800 text-white border-r-4 border-blue-500' : '' ?>">
                <span class="w-5 h-5 flex items-center justify-center"><i class="fa-solid fa-users"></i></span>
                <span><?= __('employees') ?></span>
            </a>

<a href="index.php?controller=attendance&action=index" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white transition-colors <?= $activeMenu === 'attendance' ? 'bg-gray-800 text-white border-r-4 border-blue-500' : '' ?>">
                <span class="w-5 h-5 flex items-center justify-center"><i class="fa-solid fa-clock"></i></span>
                <span><?= __('attendance') ?></span>
            </a>

        <a href="index.php?controller=attendance&action=calendar" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white transition-colors <?= $activeMenu === 'calendar' ? 'bg-gray-800 text-white border-r-4 border-blue-500' : '' ?>">
            <span class="w-5 h-5 flex items-center justify-center"><i class="fa-solid fa-calendar-days"></i></span>
            <span>Calendar</span>
        </a>

<a href="index.php?controller=reports&action=index" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white transition-colors <?= $activeMenu === 'reports' ? 'bg-gray-800 text-white border-r-4 border-blue-500' : '' ?>">
                <span class="w-5 h-5 flex items-center justify-center"><i class="fa-solid fa-chart-column"></i></span>
                <span><?= __('reports') ?></span>
            </a>

<a href="index.php?controller=schedules&action=index" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white transition-colors <?= $activeMenu === 'schedules' ? 'bg-gray-800 text-white border-r-4 border-blue-500' : '' ?>">
                <span class="w-5 h-5 flex items-center justify-center"><i class="fa-solid fa-business-time"></i></span>
                <span><?= __('schedules') ?></span>
            </a>

        <div class="px-4 mt-4 mb-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">RH</div>

        <a href="index.php?controller=hr_dashboard&action=index" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white transition-colors <?= $activeMenu === 'hr_dashboard' ? 'bg-gray-800 text-white border-r-4 border-blue-500' : '' ?>">
            <span class="w-5 h-5 flex items-center justify-center"><i class="fa-solid fa-chart-pie"></i></span>
            <span><?= __('hr_dashboard') ?></span>
        </a>

        <a href="index.php?controller=leaves&action=index" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white transition-colors <?= $activeMenu === 'leaves' ? 'bg-gray-800 text-white border-r-4 border-blue-500' : '' ?>">
            <span class="w-5 h-5 flex items-center justify-center"><i class="fa-solid fa-calendar-check"></i></span>
            <span><?= __('leaves') ?></span>
        </a>

        <a href="index.php?controller=overtime&action=index" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white transition-colors <?= $activeMenu === 'overtime' ? 'bg-gray-800 text-white border-r-4 border-blue-500' : '' ?>">
            <span class="w-5 h-5 flex items-center justify-center"><i class="fa-solid fa-clock"></i></span>
            <span><?= __('overtime') ?></span>
        </a>

        <a href="index.php?controller=shifts&action=index" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white transition-colors <?= $activeMenu === 'shifts' ? 'bg-gray-800 text-white border-r-4 border-blue-500' : '' ?>">
            <span class="w-5 h-5 flex items-center justify-center"><i class="fa-solid fa-sun"></i></span>
            <span><?= __('shifts') ?></span>
        </a>

        <a href="index.php?controller=remote_work&action=index" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white transition-colors <?= $activeMenu === 'remote_work' ? 'bg-gray-800 text-white border-r-4 border-blue-500' : '' ?>">
            <span class="w-5 h-5 flex items-center justify-center"><i class="fa-solid fa-house-laptop"></i></span>
            <span><?= __('remote_work') ?></span>
        </a>

        <div class="px-4 mt-4 mb-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">System</div>

<a href="index.php?controller=terminals&action=index" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white transition-colors <?= $activeMenu === 'terminals' ? 'bg-gray-800 text-white border-r-4 border-blue-500' : '' ?>">
                <span class="w-5 h-5 flex items-center justify-center"><i class="fa-solid fa-computer"></i></span>
                <span><?= __('terminals') ?></span>
            </a>

<a href="index.php?controller=settings&action=index" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white transition-colors <?= $activeMenu === 'settings' ? 'bg-gray-800 text-white border-r-4 border-blue-500' : '' ?>">
                <span class="w-5 h-5 flex items-center justify-center"><i class="fa-solid fa-gear"></i></span>
                <span><?= __('settings') ?></span>
            </a>

<a href="index.php?controller=logs&action=index" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white transition-colors <?= $activeMenu === 'logs' ? 'bg-gray-800 text-white border-r-4 border-blue-500' : '' ?>">
                <span class="w-5 h-5 flex items-center justify-center"><i class="fa-solid fa-list-ul"></i></span>
                <span><?= __('logs') ?></span>
            </a>
    </nav>

    <div class="p-4 border-t border-gray-800">
<a href="login.php?logout=1" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-gray-800 hover:text-white transition-colors">
                <span class="w-5 h-5 flex items-center justify-center"><i class="fa-solid fa-arrow-right-from-bracket"></i></span>
                <span><?= __('logout') ?></span>
            </a>
    </div>
</aside>
