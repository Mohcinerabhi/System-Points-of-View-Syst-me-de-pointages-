<?php
namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Modèle de base fournissant un CRUD générique et des requêtes SQL.
 */
class BaseModel
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected bool $softDelete = false;
    protected string $softDeleteColumn = 'deleted_at';

    /**
     * Connexion PDO partagée (singleton).
     *
     * @var PDO|null
     */
    protected ?PDO $pdo = null;

    /**
     * Attributs de l'enregistrement courant (utilisé par les méthodes objet).
     *
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * Indique si l'instance correspond à un enregistrement existant.
     *
     * @var bool
     */
    protected bool $exists = false;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * Connexion PDO partagée (singleton).
     *
     * @return PDO
     */
    public function db(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = Database::connection();
        }
        return $this->pdo;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }

    public function find($id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findBy(string $column, $value): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE `{$column}` = ? LIMIT 1";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([$value]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function all(string $orderBy = '', ?int $limit = null): array
    {
        $sql = "SELECT * FROM {$this->table}";
        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $this->escapeOrderBy($orderBy);
        }
        if ($limit !== null) {
            $sql .= " LIMIT " . (int) $limit;
        }
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function where(string $conditions, array $params = []): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$conditions}";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) AS cnt FROM {$this->table}";
        $params = [];
        if (!empty($conditions)) {
            $parts = [];
            foreach ($conditions as $col => $val) {
                $parts[] = "`{$col}` = ?";
                $params[] = $val;
            }
            $sql .= " WHERE " . implode(' AND ', $parts);
        }
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        $data = $this->filterFillable($data);
        if (empty($data)) {
            return 0;
        }
        $columns = array_keys($data);
        $wrapped = array_map(static fn (string $c): string => "`{$c}`", $columns);
        $placeholders = array_fill(0, count($columns), '?');
        $sql = sprintf(
            "INSERT INTO {$this->table} (%s) VALUES (%s)",
            implode(', ', $wrapped),
            implode(', ', $placeholders)
        );
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(array_values($data));
        return (int) $this->db()->lastInsertId();
    }

    public function update($id, array $data): bool
    {
        $data = $this->filterFillable($data);
        if (empty($data)) {
            return false;
        }
        $set = [];
        foreach (array_keys($data) as $col) {
            $set[] = "`{$col}` = ?";
        }
        $sql = sprintf(
            "UPDATE `{$this->table}` SET %s WHERE `{$this->primaryKey}` = ?",
            implode(', ', $set)
        );
        $params = array_values($data);
        $params[] = $id;
        $stmt = $this->db()->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete($id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db()->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Récupère une page de résultats avec recherche et conditions.
     */
    public function paginate(
        int $page = 1,
        int $perPage = 20,
        array $conditions = [],
        array $searchColumns = [],
        ?string $search = null,
        string $orderBy = ''
    ): array {
        $where = [];
        $params = [];

        foreach ($conditions as $col => $val) {
            if ($val !== null && $val !== '') {
                $where[] = "`{$col}` = ?";
                $params[] = $val;
            }
        }

        if ($search !== null && $search !== '' && !empty($searchColumns)) {
            $like = [];
            foreach ($searchColumns as $col) {
                $like[] = "`{$col}` LIKE ?";
                $params[] = '%' . $search . '%';
            }
            $where[] = '(' . implode(' OR ', $like) . ')';
        }

        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM {$this->table}{$sqlWhere}";
        $countStmt = $this->db()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM {$this->table}{$sqlWhere}";
        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $this->escapeOrderBy($orderBy);
        }
        $offset = max(0, ($page - 1) * $perPage);
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data'  => $data,
            'total' => $total,
        ];
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function execute(string $sql, array $params = []): bool
    {
        return $this->db()->prepare($sql)->execute($params);
    }

    // =========================================================
    // ACCÈS AUX ATTRIBUTS / HYDRATATION (compatibilité Model)
    // =========================================================

    /**
     * Remplit les attributs de l'instance.
     *
     * @param array<string, mixed> $attributes
     * @return static
     */
    public function fill(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        $this->exists     = true;

        return $this;
    }

    /**
     * Valeur de la clé primaire de l'instance courante.
     *
     * @return mixed
     */
    public function getKey(): mixed
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    /**
     * Indique si l'instance correspond à un enregistrement existant.
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * Retourne les attributs sous forme de tableau.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function __unset(string $name): void
    {
        unset($this->attributes[$name]);
    }

    /**
     * Exécute une requête brute et retourne toutes les lignes.
     *
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchAllRaw(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            $this->handleError($e, __FUNCTION__);
            return [];
        }
    }

    /**
     * Exécute une requête brute et retourne la première ligne.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOneRaw(string $sql, array $params = []): ?array
    {
        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row !== false ? $row : null;
        } catch (\PDOException $e) {
            $this->handleError($e, __FUNCTION__);
            return null;
        }
    }

    /**
     * Journalise une erreur de base de données.
     */
    protected function handleError(\Throwable $e, string $context): void
    {
        error_log(sprintf('[%s::%s] %s', static::class, $context, $e->getMessage()));
    }

    /**
     * Escape an ORDER BY clause safely.
     *
     * Wraps each column name in backticks while preserving ASC/DESC.
     */
    protected function escapeOrderBy(string $orderBy): string
    {
        $parts = array_map(static function ($part) {
            $part = trim($part);
            if (preg_match('/^([a-zA-Z0-9_]+)(?:\s+(ASC|DESC))?$/i', $part, $m)) {
                return '`' . $m[1] . '`' . (isset($m[2]) ? ' ' . strtoupper($m[2]) : '');
            }
            return $part;
        }, explode(',', $orderBy));

        return implode(', ', $parts);
    }
}
