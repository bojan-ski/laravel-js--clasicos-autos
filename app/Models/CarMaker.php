<?php

namespace App\Models;

use Database\Factories\CarMakerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class CarMaker extends Model
{
    /** @use HasFactory<CarMakerFactory> */
    use HasFactory;

    /**
     * @var array<string, bool>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<CarModel, $this>
     */
    public function models(): HasMany
    {
        return $this->hasMany(CarModel::class);
    }

    /**
     * @return HasMany<CarModel, $this>
     */
    public function activeModels(): HasMany
    {
        return $this->hasMany(CarModel::class)->active()->ordered();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('name');
    }

    public function isDeletable(): bool
    {
        return ! $this->models()->exists();
    }
}
