<?php
namespace App\Helpers;

/**
 * Utilitaires d'import / export CSV.
 */
class CsvHelper
{
    /**
     * Lit un fichier CSV uploadé et renvoie un tableau associatif.
     */
    public static function read(string $filePath, array $columns, string $delimiter = ','): array
    {
        if (!is_readable($filePath)) {
            return [];
        }
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return [];
        }

        $rows = [];
        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false) {
            fclose($handle);
            return [];
        }
        $header = array_map('trim', $header);

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (empty(array_filter($data))) {
                continue;
            }
            $row = [];
            foreach ($columns as $i => $col) {
                $row[$col] = $data[$i] ?? null;
            }
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    /**
     * Force le téléchargement d'un fichier CSV.
     */
    public static function export(array $header, array $rows, string $filename = 'export.csv'): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . Sanitize::filename($filename) . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF"); // BOM UTF-8
        fputcsv($output, $header);

        foreach ($rows as $row) {
            $line = [];
            foreach ($header as $key) {
                $line[] = $row[$key] ?? '';
            }
            fputcsv($output, $line);
        }
        fclose($output);
        exit;
    }
}
