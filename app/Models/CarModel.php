<?php

namespace App\Models;

use Database\Factories\CarModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'year_from', 'year_to'])]
class CarModel extends Model
{
    /** @use HasFactory<CarModelFactory> */
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
            'year_from' => 'integer',
            'year_to' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CarMaker, $this>
     */
    public function maker(): BelongsTo
    {
        return $this->belongsTo(CarMaker::class, 'car_maker_id');
    }

    /**
     * @return HasMany<CarListing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(CarListing::class);
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

    public function coversYear(int $year): bool
    {
        return $this->year_from <= $year && $year <= $this->year_to;
    }

    public function productionYears(): string
    {
        return "{$this->year_from}–{$this->year_to}";
    }

    public function isDeletable(): bool
    {
        return ! $this->listings()->exists();
    }
}
