<?php
/**
 * Modèle Paramètre (table `settings`)
 * Fichier: models/Setting.php
 *
 * Gère les paramètres clé/valeur de l'application avec conversion de type.
 *
 * @property int         $id
 * @property string      $key
 * @property string|null $value
 * @property string      $type
 * @property string      $created_at
 * @property string      $updated_at
 */

declare(strict_types=1);

namespace App\Models;

use PDOException;

class Setting extends BaseModel
{
    /**
     * @var string
     */
    protected string $table = 'settings';

    /**
     * @var string[]
     */
    protected array $fillable = [
        'key',
        'value',
        'type',
    ];

    /**
     * Récupère la valeur d'un paramètre (typée), ou une valeur par défaut.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->fetchOneRaw(
            'SELECT `value`, `type` FROM `settings` WHERE `key` = :key LIMIT 1',
            [':key' => $key]
        );

        if ($row === null) {
            return $default;
        }

        return $this->castValue($row['value'] ?? null, (string) ($row['type'] ?? 'text'));
    }

    /**
     * Définit (crée ou met à jour) la valeur d'un paramètre.
     *
     * @param string $key
     * @param mixed  $value
     * @return bool
     */
    public function set(string $key, mixed $value): bool
    {
        [$stored, $type] = $this->prepareValue($value);

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO `settings` (`key`, `value`, `type`)
                 VALUES (:key, :value, :type)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`)'
            );

            return $stmt->execute([
                ':key'   => $key,
                ':value' => $stored,
                ':type'  => $type,
            ]);
        } catch (PDOException $e) {
            $this->handleError($e, __FUNCTION__);
            return false;
        }
    }

    /**
     * Récupère tous les paramètres sous forme de tableau clé => valeur typée.
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        $rows = $this->fetchAllRaw('SELECT `key`, `value`, `type` FROM `settings`');

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['key']] = $this->castValue(
                $row['value'] ?? null,
                (string) ($row['type'] ?? 'text')
            );
        }

        return $result;
    }

    /**
     * Récupère tous les paramètres sous forme de tableau clé => valeur typée.
     *
     * @return array<string, mixed>
     */
    public function allAsKeyValue(): array
    {
        $rows = $this->fetchAllRaw('SELECT `key`, `value`, `type` FROM `settings`');

        $result = [];
        foreach ($rows as $row) {
            $result[(string) ($row['key'] ?? '')] = $this->castValue(
                $row['value'] ?? null,
                (string) ($row['type'] ?? 'text')
            );
        }

        return $result;
    }

    /**
     * Met à jour plusieurs paramètres en une seule opération.
     *
     * @param array<string, mixed> $values
     */
    public function updateMany(array $values): bool
    {
        if ($values === []) {
            return true;
        }

        $ok = true;
        foreach ($values as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $ok = $this->set($key, $value) && $ok;
        }

        return $ok;
    }

    /**
     * Récupère un paramètre par sa clé.
     *
     * @return array<string, mixed>|null
     */
    public function findByKey(string $key): ?array
    {
        return $this->findBy('key', $key);
    }

    /**
     * Récupère et décode un paramètre JSON.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function getJSON(string $key, mixed $default = null): mixed
    {
        $row = $this->fetchOneRaw(
            'SELECT `value` FROM `settings` WHERE `key` = :key LIMIT 1',
            [':key' => $key]
        );

        if ($row === null || $row['value'] === null || $row['value'] === '') {
            return $default;
        }

        $decoded = json_decode((string) $row['value'], true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }

    /**
     * Convertit une valeur brute selon son type déclaré.
     *
     * @param string|null $value
     * @param string      $type
     * @return mixed
     */
    private function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower($type)) {
            'number', 'int', 'integer' => str_contains($value, '.') ? (float) $value : (int) $value,
            'float', 'decimal'         => (float) $value,
            'bool', 'boolean'          => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            'json'                     => $this->decodeJson($value),
            default                    => $value,
        };
    }

    /**
     * Décode une chaîne JSON de manière sûre.
     *
     * @return mixed
     */
    private function decodeJson(string $value): mixed
    {
        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    /**
     * Prépare une valeur pour le stockage et détermine son type.
     *
     * @param mixed $value
     * @return array{0: string|null, 1: string}
     */
    private function prepareValue(mixed $value): array
    {
        return match (true) {
            is_array($value) || is_object($value) => [json_encode($value, JSON_UNESCAPED_UNICODE), 'json'],
            is_bool($value)                       => [$value ? '1' : '0', 'boolean'],
            is_int($value)                        => [(string) $value, 'number'],
            is_float($value)                      => [(string) $value, 'float'],
            $value === null                       => [null, 'text'],
            default                               => [(string) $value, 'text'],
        };
    }
}
