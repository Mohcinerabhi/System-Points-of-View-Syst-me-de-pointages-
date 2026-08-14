<?php
/**
 * Settings - Attendance Pro
 */
$settings  = $settings  ?? [];
$csrf_token = $csrf_token ?? '';
function s($k, $b, $c = '') {
    if (is_array($b)) {
        return htmlspecialchars($b[$k] ?? $c);
    }
    if (func_num_args() === 3 && is_array($c)) {
        return htmlspecialchars($c[$k] ?? $b);
    }
    return htmlspecialchars($b);
}
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h4 class="text-xl font-semibold text-gray-900"><i class="fas fa-cog text-blue-600 mr-2"></i><?= __('settings') ?></h4>
        <div class="text-gray-500 text-sm"><?= __('company_info') ?> et du système</div>
    </div>
</div>

<form method="post" action="index.php?controller=settings&action=update">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Company info -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200"><h5 class="font-semibold text-gray-900"><i class="fas fa-building mr-2"></i><?= __('company_info') ?></h5></div>
            <div class="p-6">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('company_name') ?></label>
                    <input type="text" name="company_name" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= s('company_name', $settings) ?>">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Logo (URL)</label>
                    <input type="text" name="company_logo" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= s('company_logo', $settings) ?>">
                    <?php if (s('company_logo', $settings)): ?><img src="<?= s('company_logo', $settings) ?>" class="mt-2" style="max-height:60px"><?php endif; ?>
                </div>
                <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('address') ?></label>
                    <textarea name="company_address" rows="2" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"><?= s('company_address', $settings) ?></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('phone') ?></label>
                        <input type="text" name="company_phone" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= s('company_phone', $settings) ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="company_email" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= s('company_email', $settings) ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- General settings -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200"><h5 class="font-semibold text-gray-900"><i class="fas fa-sliders-h mr-2"></i><?= __('general_settings') ?></h5></div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fuseau horaire</label>
                        <select name="timezone" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="Africa/Casablanca" <?= s('timezone','Africa/Casablanca', $settings)==='Africa/Casablanca'?'selected':'' ?>>Africa/Casablanca</option>
                            <option value="Europe/Paris" <?= s('timezone', $settings)==='Europe/Paris'?'selected':'' ?>>Europe/Paris</option>
                            <option value="UTC" <?= s('timezone', $settings)==='UTC'?'selected':'' ?>>UTC</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Langue</label>
                        <select name="language" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="fr" <?= s('language','fr', $settings)==='fr'?'selected':'' ?>>Français</option>
                            <option value="en" <?= s('language', $settings)==='en'?'selected':'' ?>>English</option>
                            <option value="ar" <?= s('language', $settings)==='ar'?'selected':'' ?>>العربية</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Format date</label>
                        <input type="text" name="date_format" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= s('date_format','d/m/Y', $settings) ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Format heure</label>
                        <input type="text" name="time_format" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= s('time_format','H:i', $settings) ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Heure début pointage</label>
                        <input type="time" name="attendance_start_time" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= s('attendance_start_time','08:30', $settings) ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Heure fin pointage</label>
                        <input type="time" name="attendance_end_time" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= s('attendance_end_time','17:30', $settings) ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tolérance retard (min)</label>
                        <input type="number" name="default_late_tolerance" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= s('default_late_tolerance','15', $settings) ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tol. départ (min)</label>
                        <input type="number" name="default_early_tolerance" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= s('default_early_tolerance','10', $settings) ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">H/jour</label>
                        <input type="number" step="0.5" name="default_work_hours" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= s('default_work_hours','8', $settings) ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Éléments / page</label>
                        <input type="number" name="items_per_page" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= s('items_per_page','20', $settings) ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Expiration session (s)</label>
                        <input type="number" name="session_timeout" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" value="<?= s('session_timeout','3600', $settings) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm font-medium"><i class="fas fa-save mr-1"></i><?= __('save') ?> <?= __('settings') ?></button>
    </div>
</form>
