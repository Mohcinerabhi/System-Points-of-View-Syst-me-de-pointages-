<?php
/**
 * Classe de base pour tous les modèles (couche d'accès aux données)
 * Fichier: models/Model.php
 *
 * Fournit les opérations CRUD génériques ainsi que des utilitaires
 * de recherche, de comptage et de pagination via PDO en requêtes préparées.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use PDOException;
use JsonSerializable;
use ArrayAccess;
use InvalidArgumentException;
use Throwable;

abstract class Model implements JsonSerializable, ArrayAccess
{
    /**
     * Connexion PDO partagée (Singleton).
     *
     * @var PDO
     */
    protected PDO $pdo;

    /**
     * Nom de la table associée au modèle.
     *
     * @var string
     */
    protected string $table = '';

    /**
     * Nom de la clé primaire.
     *
     * @var string
     */
    protected string $primaryKey = 'id';

    /**
     * Colonnes autorisées en écriture (insertion / mise à jour).
     *
     * @var string[]
     */
    protected array $fillable = [];

    /**
     * Colonnes masquées lors de la sérialisation (toArray / json).
     *
     * @var string[]
     */
    protected array $hidden = [];

    /**
     * Attributs de l'enregistrement courant.
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

    /**
     * Opérateurs SQL autorisés dans la méthode where().
     */
    protected const ALLOWED_OPERATORS = [
        '=', '!=', '<>', '<', '<=', '>', '>=',
        'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS', 'IS NOT',
    ];

    /**
     * Constructeur.
     *
     * @param array<string, mixed> $attributes Attributs initiaux éventuels.
     */
    public function __construct(array $attributes = [])
    {
        $this->pdo = Database::connection();

        if ($attributes !== []) {
            $this->attributes = $attributes;
        }
    }

    // =========================================================
    // OPÉRATIONS CRUD
    // =========================================================

    /**
     * Récupère un enregistrement par sa clé primaire.
     *
     * @param int|string $id Valeur de la clé primaire.
     * @return static|null   Instance hydratée ou null si introuvable.
     */
    public function find(int|string $id): ?static
    {
        if ($id === '' ) {
            return null;
        }

        try {
            $sql = sprintf(
                'SELECT * FROM %s WHERE %s = :id LIMIT 1',
                $this->wrap($this->table),
                $this->wrap($this->primaryKey)
            );

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':id', $id, $this->paramType($id));
            $stmt->execute();

            $row = $stmt->fetch();

            return $row ? $this->newFromRow($row) : null;
        } catch (PDOException $e) {
            $this->handleError($e, __FUNCTION__);
            return null;
        }
    }

    /**
     * Récupère une liste d'enregistrements.
     *
     * @param array<string, mixed> $conditions Conditions d'égalité (ou IN si valeur = tableau).
     * @param string               $order      Clause ORDER BY (ex: "created_at DESC").
     * @param int                  $limit      Nombre maximum de résultats (0 = illimité).
     * @param int                  $offset     Décalage.
     * @return static[]
     */
    public function findAll(array $conditions = [], string $order = '', int $limit = 0, int $offset = 0): array
    {
        try {
            [$where, $params] = $this->buildWhere($conditions);

            $sql = 'SELECT * FROM ' . $this->wrap($this->table);
            if ($where !== '') {
                $sql .= ' WHERE ' . $where;
            }
            $sql .= $this->buildOrder($order);
            $sql .= $this->buildLimit($limit, $offset);

            $stmt = $this->pdo->prepare($sql);
            $this->bindParams($stmt, $params);
            $stmt->execute();

            return array_map(fn (array $row): static => $this->newFromRow($row), $stmt->fetchAll());
        } catch (PDOException $e) {
            $this->handleError($e, __FUNCTION__);
            return [];
        }
    }

    /**
     * Insère un nouvel enregistrement.
     *
     * @param array<string, mixed> $data Données à insérer (filtrées par $fillable).
     * @return static|null               Instance créée ou null en cas d'échec.
     *
     * @throws InvalidArgumentException Si aucune donnée valide n'est fournie.
     */
    public function create(array $data): ?static
    {
        $data = $this->filterFillable($data);

        if ($data === []) {
            throw new InvalidArgumentException('Aucune donnée valide à insérer.');
        }

        try {
            $columns      = array_keys($data);
            $wrappedCols  = implode(', ', array_map([$this, 'wrap'], $columns));
            $placeholders = implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns));

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $this->wrap($this->table),
                $wrappedCols,
                $placeholders
            );

            $stmt = $this->pdo->prepare($sql);
            foreach ($data as $column => $value) {
                $stmt->bindValue(':' . $column, $value, $this->paramType($value));
            }
            $stmt->execute();

            $id = $this->pdo->lastInsertId();

            return ($id !== false && $id !== '0' && $id !== '')
                ? $this->find($id)
                : $this->newFromRow($data);
        } catch (PDOException $e) {
            $this->handleError($e, __FUNCTION__);
            return null;
        }
    }

    /**
     * Met à jour un enregistrement existant.
     *
     * @param int|string           $id   Clé primaire.
     * @param array<string, mixed> $data Données à mettre à jour (filtrées par $fillable).
     * @return bool                       Succès de l'opération.
     */
    public function update(int|string $id, array $data): bool
    {
        $data = $this->filterFillable($data);

        if ($data === []) {
            return false;
        }

        try {
            $assignments = implode(
                ', ',
                array_map(fn (string $c): string => $this->wrap($c) . ' = :' . $c, array_keys($data))
            );

            $sql = sprintf(
                'UPDATE %s SET %s WHERE %s = :__pk_id',
                $this->wrap($this->table),
                $assignments,
                $this->wrap($this->primaryKey)
            );

            $stmt = $this->pdo->prepare($sql);
            foreach ($data as $column => $value) {
                $stmt->bindValue(':' . $column, $value, $this->paramType($value));
            }
            $stmt->bindValue(':__pk_id', $id, $this->paramType($id));

            return $stmt->execute();
        } catch (PDOException $e) {
            $this->handleError($e, __FUNCTION__);
            return false;
        }
    }

    /**
     * Supprime un enregistrement par sa clé primaire.
     *
     * @param int|string $id Clé primaire.
     * @return bool          Succès de l'opération.
     */
    public function delete(int|string $id): bool
    {
        try {
            $sql = sprintf(
                'DELETE FROM %s WHERE %s = :id',
                $this->wrap($this->table),
                $this->wrap($this->primaryKey)
            );

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':id', $id, $this->paramType($id));

            return $stmt->execute();
        } catch (PDOException $e) {
            $this->handleError($e, __FUNCTION__);
            return false;
        }
    }

    // =========================================================
    // REQUÊTES AVANCÉES
    // =========================================================

    /**
     * Récupère les enregistrements correspondant à une condition simple.
     *
     * @param string $field    Colonne concernée.
     * @param string $operator Opérateur SQL (=, !=, LIKE, IN, ...).
     * @param mixed  $value    Valeur (tableau pour IN / NOT IN).
     * @return static[]
     */
    public function where(string $field, string $operator, mixed $value): array
    {
        $operator = strtoupper(trim($operator));

        if (!in_array($operator, self::ALLOWED_OPERATORS, true)) {
            throw new InvalidArgumentException("Opérateur SQL non autorisé: {$operator}");
        }

        try {
            $column = $this->wrap($field);
            $params = [];

            if (in_array($operator, ['IN', 'NOT IN'], true)) {
                $values = is_array($value) ? array_values($value) : [$value];
                if ($values === []) {
                    return [];
                }
                $placeholders = [];
                foreach ($values as $index => $val) {
                    $ph = ':val' . $index;
                    $placeholders[] = $ph;
                    $params[$ph] = $val;
                }
                $clause = $column . ' ' . $operator . ' (' . implode(', ', $placeholders) . ')';
            } elseif ($value === null && in_array($operator, ['IS', 'IS NOT', '=', '!=', '<>'], true)) {
                $clause = $column . ' ' . ($operator === '=' ? 'IS' : ($operator === '!=' || $operator === '<>' ? 'IS NOT' : $operator)) . ' NULL';
            } else {
                $clause = $column . ' ' . $operator . ' :val';
                $params[':val'] = $value;
            }

            $sql  = 'SELECT * FROM ' . $this->wrap($this->table) . ' WHERE ' . $clause;
            $stmt = $this->pdo->prepare($sql);
            $this->bindParams($stmt, $params);
            $stmt->execute();

            return array_map(fn (array $row): static => $this->newFromRow($row), $stmt->fetchAll());
        } catch (PDOException $e) {
            $this->handleError($e, __FUNCTION__);
            return [];
        }
    }

    /**
     * Compte les enregistrements, avec conditions et recherche facultatives.
     *
     * @param array<string, mixed> $conditions Conditions d'égalité.
     * @param string               $search     Terme recherché sur les colonnes $fillable.
     * @return int
     */
    public function count(array $conditions = [], string $search = ''): int
    {
        try {
            [$where, $params] = $this->buildWhere($conditions);

            $clauses = [];
            if ($where !== '') {
                $clauses[] = $where;
            }

            $search = trim($search);
            if ($search !== '' && $this->fillable !== []) {
                [$searchSql, $searchParams] = $this->buildSearch($search, $this->fillable);
                if ($searchSql !== '') {
                    $clauses[] = $searchSql;
                    $params    = array_merge($params, $searchParams);
                }
            }

            $sql = 'SELECT COUNT(*) FROM ' . $this->wrap($this->table);
            if ($clauses !== []) {
                $sql .= ' WHERE ' . implode(' AND ', $clauses);
            }

            $stmt = $this->pdo->prepare($sql);
            $this->bindParams($stmt, $params);
            $stmt->execute();

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->handleError($e, __FUNCTION__);
            return 0;
        }
    }

    /**
     * Recherche des enregistrements par terme sur plusieurs colonnes.
     *
     * @param string               $term       Terme recherché.
     * @param string[]             $fields     Colonnes à interroger (défaut: $fillable).
     * @param array<string, mixed> $conditions Conditions supplémentaires.
     * @return static[]
     */
    public function search(string $term, array $fields = [], array $conditions = []): array
    {
        $fields = $fields !== [] ? $fields : $this->fillable;
        $term   = trim($term);

        try {
            $clauses = [];
            $params  = [];

            [$where, $whereParams] = $this->buildWhere($conditions);
            if ($where !== '') {
                $clauses[] = $where;
                $params    = array_merge($params, $whereParams);
            }

            if ($term !== '' && $fields !== []) {
                [$searchSql, $searchParams] = $this->buildSearch($term, $fields);
                if ($searchSql !== '') {
                    $clauses[] = $searchSql;
                    $params    = array_merge($params, $searchParams);
                }
            }

            $sql = 'SELECT * FROM ' . $this->wrap($this->table);
            if ($clauses !== []) {
                $sql .= ' WHERE ' . implode(' AND ', $clauses);
            }

            $stmt = $this->pdo->prepare($sql);
            $this->bindParams($stmt, $params);
            $stmt->execute();

            return array_map(fn (array $row): static => $this->newFromRow($row), $stmt->fetchAll());
        } catch (PDOException $e) {
            $this->handleError($e, __FUNCTION__);
            return [];
        }
    }

    /**
     * Pagine les résultats.
     *
     * @param int                  $page       Numéro de page (>= 1).
     * @param int                  $perPage    Éléments par page (>= 1).
     * @param array<string, mixed> $conditions Conditions d'égalité.
     * @param string               $order      Clause ORDER BY.
     * @return array{data: static[], total: int, page: int, per_page: int, total_pages: int, from: int, to: int}
     */
    public function paginate(int $page = 1, int $perPage = 20, array $conditions = [], string $order = ''): array
    {
        $page    = max(1, $page);
        $perPage = max(1, $perPage);
        $offset  = ($page - 1) * $perPage;

        $total      = $this->count($conditions);
        $data       = $this->findAll($conditions, $order, $perPage, $offset);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;

        return [
            'data'        => $data,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
            'from'        => $total > 0 ? $offset + 1 : 0,
            'to'          => min($offset + $perPage, $total),
        ];
    }

    // =========================================================
    // UTILITAIRES INTERNES
    // =========================================================

    /**
     * Crée une nouvelle instance hydratée à partir d'une ligne SQL.
     *
     * @param array<string, mixed> $row
     * @return static
     */
    protected function newFromRow(array $row): static
    {
        $model = new static();
        $model->attributes = $row;
        $model->exists     = true;

        return $model;
    }

    /**
     * Exécute une requête brute et retourne toutes les lignes.
     *
     * @param string               $sql
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    protected function fetchAllRaw(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindParams($stmt, $params);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->handleError($e, __FUNCTION__);
            return [];
        }
    }

    /**
     * Exécute une requête brute et retourne la première ligne.
     *
     * @param string               $sql
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    protected function fetchOneRaw(string $sql, array $params = []): ?array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindParams($stmt, $params);
            $stmt->execute();

            $row = $stmt->fetch();

            return $row !== false ? $row : null;
        } catch (PDOException $e) {
            $this->handleError($e, __FUNCTION__);
            return null;
        }
    }

    /**
     * Construit une clause WHERE d'égalités (ou IN pour les tableaux).
     *
     * @param array<string, mixed> $conditions
     * @param string               $prefix     Préfixe des paramètres nommés.
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function buildWhere(array $conditions, string $prefix = 'w'): array
    {
        if ($conditions === []) {
            return ['', []];
        }

        $clauses = [];
        $params  = [];
        $index   = 0;

        foreach ($conditions as $field => $value) {
            $placeholder = ':' . $prefix . $index++;
            $column      = $this->wrap((string) $field);

            if (is_array($value)) {
                if ($value === []) {
                    $clauses[] = '0 = 1';
                    continue;
                }
                $ins = [];
                $j   = 0;
                foreach ($value as $v) {
                    $ph        = $placeholder . '_' . $j++;
                    $ins[]     = $ph;
                    $params[$ph] = $v;
                }
                $clauses[] = $column . ' IN (' . implode(', ', $ins) . ')';
            } elseif ($value === null) {
                $clauses[] = $column . ' IS NULL';
            } else {
                $clauses[]           = $column . ' = ' . $placeholder;
                $params[$placeholder] = $value;
            }
        }

        return [implode(' AND ', $clauses), $params];
    }

    /**
     * Construit une clause de recherche LIKE sur plusieurs colonnes.
     *
     * @param string   $term
     * @param string[] $fields
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function buildSearch(string $term, array $fields): array
    {
        if ($term === '' || $fields === []) {
            return ['', []];
        }

        $like    = '%' . $term . '%';
        $clauses = [];
        $params  = [];
        $index   = 0;

        foreach ($fields as $field) {
            $ph          = ':s' . $index++;
            $clauses[]   = $this->wrap($field) . ' LIKE ' . $ph;
            $params[$ph] = $like;
        }

        return ['(' . implode(' OR ', $clauses) . ')', $params];
    }

    /**
     * Construit une clause ORDER BY sécurisée.
     */
    protected function buildOrder(string $order): string
    {
        $order = trim($order);
        if ($order === '') {
            return '';
        }

        $safe = [];
        foreach (explode(',', $order) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $segments = preg_split('/\s+/', $part) ?: [];
            $column   = preg_replace('/[^A-Za-z0-9_]/', '', $segments[0] ?? '');
            if ($column === '' || $column === null) {
                continue;
            }
            $direction = (isset($segments[1]) && strtoupper($segments[1]) === 'DESC') ? 'DESC' : 'ASC';
            $safe[]    = '`' . $column . '` ' . $direction;
        }

        return $safe !== [] ? ' ORDER BY ' . implode(', ', $safe) : '';
    }

    /**
     * Construit une clause LIMIT / OFFSET (valeurs entières sécurisées).
     */
    protected function buildLimit(int $limit, int $offset): string
    {
        $sql = '';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
            if ($offset > 0) {
                $sql .= ' OFFSET ' . $offset;
            }
        }

        return $sql;
    }

    /**
     * Filtre les données selon la liste blanche $fillable.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function filterFillable(array $data): array
    {
        if ($this->fillable === []) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Sécurise un identifiant SQL (table / colonne).
     */
    protected function wrap(string $identifier): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_]/', '', $identifier);

        if ($clean === '' || $clean === null) {
            throw new InvalidArgumentException("Identifiant SQL invalide: {$identifier}");
        }

        return '`' . $clean . '`';
    }

    /**
     * Détermine le type PDO à partir d'une valeur.
     */
    protected function paramType(mixed $value): int
    {
        return match (true) {
            is_bool($value) => PDO::PARAM_BOOL,
            is_int($value)  => PDO::PARAM_INT,
            $value === null => PDO::PARAM_NULL,
            default         => PDO::PARAM_STR,
        };
    }

    /**
     * Lie un tableau de paramètres nommés à une requête préparée.
     *
     * @param \PDOStatement        $stmt
     * @param array<string, mixed> $params
     */
    protected function bindParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, $this->paramType($value));
        }
    }

    /**
     * Journalise une erreur de base de données.
     */
    protected function handleError(Throwable $e, string $context): void
    {
        error_log(sprintf('[%s::%s] %s', static::class, $context, $e->getMessage()));
    }

    // =========================================================
    // ACCÈS AUX ATTRIBUTS / SÉRIALISATION
    // =========================================================

    /**
     * Nom de la table du modèle.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Nom de la clé primaire.
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Valeur de la clé primaire de l'instance courante.
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
     * Remplit les attributs de l'instance.
     *
     * @param array<string, mixed> $attributes
     * @return static
     */
    public function fill(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    /**
     * Retourne les attributs sous forme de tableau (sans les champs masqués).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = $this->attributes;
        foreach ($this->hidden as $hidden) {
            unset($data[$hidden]);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
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

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->attributes[] = $value;
        } else {
            $this->attributes[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }
}
