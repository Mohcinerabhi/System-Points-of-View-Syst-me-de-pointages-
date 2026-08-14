<?php
namespace App\Models;

/**
 * Modèle Permission (permissions).
 */
class Permission extends BaseModel
{
    protected string $table = 'permissions';
    protected string $primaryKey = 'id';
    protected array $fillable = ['role_id', 'module', 'can_view', 'can_create', 'can_edit', 'can_delete'];
}
