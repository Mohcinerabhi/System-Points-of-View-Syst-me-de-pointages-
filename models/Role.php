<?php
/**
 * Modèle Rôle (table `roles`)
 * Fichier: models/Role.php
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $description
 * @property string      $created_at
 * @property string      $updated_at
 */

declare(strict_types=1);

namespace App\Models;

class Role extends BaseModel
{
    /**
     * @var string
     */
    protected string $table = 'roles';

    /**
     * @var string[]
     */
    protected array $fillable = [
        'name',
        'description',
    ];

    /**
     * Récupère les permissions associées au rôle.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPermissions(): array
    {
        $id = $this->getKey();
        if ($id === null) {
            return [];
        }

        return $this->fetchAllRaw(
            'SELECT * FROM `permissions` WHERE `role_id` = :role_id ORDER BY `module` ASC',
            [':role_id' => (int) $id]
        );
    }

    /**
     * Récupère tous les rôles.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        return $this->all('name ASC');
    }
}
