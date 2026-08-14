<?php
/**
 * Génère un avatar SVG (initiales) pour chaque employé sans photo et
 * l'enregistre dans employees.photo (uploads/employees/emp_<code>.svg).
 *
 *   php attendance/import_employee_avatars.php
 */
declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'constants.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'autoload.php';
\App\Helpers\Logger::init();

use App\Models\Employee;
use App\Core\Database;

$db = Database::connection();

$dir = rtrim(UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . 'employees' . DIRECTORY_SEPARATOR;
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$stmt = $db->query(
    "SELECT id, employee_code, first_name, last_name, photo, badge_code "
    . "FROM employees WHERE status = 'active' ORDER BY id"
);
$employees = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$processed = 0;
$generated = 0;
$skipped = 0;
$failed = 0;

foreach ($employees as $e) {
    $id = (int) $e['id'];
    $code = (string) ($e['employee_code'] ?? '');
    $photo = (string) ($e['photo'] ?? '');
    $processed++;

    if ($photo !== '') {
        // Déjà une photo enregistrée : on ne touche pas (ne pas écraser une vraie photo).
        $skipped++;
        echo sprintf("skip #%d %s %s (photo déjà présente: %s)\n", $id, $e['first_name'] ?? '', $e['last_name'] ?? '', $photo);
        continue;
    }

    $svg = Employee::generateAvatarSvg((string) ($e['first_name'] ?? ''), (string) ($e['last_name'] ?? ''));
    $safeCode = $code !== '' ? preg_replace('/[^A-Za-z0-9_-]/', '', $code) : '';
    $base = $safeCode !== '' ? 'emp_' . $safeCode : 'emp_' . $id;
    $filename = $base . '.svg';
    $destRel = 'uploads/employees/' . $filename;
    $destPath = $dir . $filename;

    if (@file_put_contents($destPath, $svg) === false) {
        $failed++;
        echo sprintf("fail #%d %s %s\n", $id, $e['first_name'] ?? '', $e['last_name'] ?? '');
        continue;
    }

    try {
        $upd = $db->prepare('UPDATE employees SET photo = :photo, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $upd->execute(['photo' => $destRel, 'id' => $id]);
        $generated++;
        echo sprintf("gen  #%d %s %s -> %s\n", $id, $e['first_name'] ?? '', $e['last_name'] ?? '', $destRel);
    } catch (\Throwable $e2) {
        @unlink($destPath);
        $failed++;
        echo sprintf("db-fail #%d: %s\n", $id, $e2->getMessage());
    }
}

echo PHP_EOL . sprintf('TOTAL: %d employés, %d avatar(s) généré(s), %d ignoré(s), %d échec(s)' . PHP_EOL, $processed, $generated, $skipped, $failed);
