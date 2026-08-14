<?php
namespace App\Models;

/**
 * Modèle Shift (table `shifts`)
 */
class Shift extends BaseModel
{
    protected string $table = 'shifts';
    protected array $fillable = ['name', 'code', 'start_time', 'end_time', 'color', 'description', 'is_active'];

    public function isActive(): bool
    {
        return (int) ($this->is_active ?? 0) === 1;
    }
}
