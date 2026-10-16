<?php

namespace App\Models;

use App\Enums\BodyType;
use App\Enums\DocumentationStatus;
use App\Enums\EngineCondition;
use App\Enums\EngineHistory;
use App\Enums\FuelType;
use App\Enums\LicensePlateType;
use App\Enums\ListingStatus;
use App\Enums\Odometer;
use App\Enums\RestorationHistory;
use App\Enums\SeatMaterial;
use App\Enums\Transmission;
use App\Enums\UserStatus;
use App\Enums\VehicleCondition;
use App\Support\Seo\CanonicalUrl;
use Database\Factories\CarListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'car_maker_id',
    'car_model_id',
    'name',
    'model',
    'description',
    'year',
    'mileage',
    'price',
    'transmission',
    'fuel_type',
    'engine_size',
    'engine_type',
    'engine_history',
    'engine_condition',
    'restoration_history',
    'original_parts_percentage',
    'body_type',
    'seat_material',
    'odometer',
    'license_plate_type',
    'documentation_status',
    'exterior_color',
    'exterior_condition',
    'interior_color',
    'interior_condition',
    'location_city',
    'location_state',
    'location_zipcode',
])]
class CarListing extends Model
{
    /** @use HasFactory<CarListingFactory> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ListingStatus::class,
            'transmission' => Transmission::class,
            'fuel_type' => FuelType::class,
            'engine_history' => EngineHistory::class,
            'engine_condition' => EngineCondition::class,
            'restoration_history' => RestorationHistory::class,
            'body_type' => BodyType::class,
            'seat_material' => SeatMaterial::class,
            'odometer' => Odometer::class,
            'license_plate_type' => LicensePlateType::class,
            'documentation_status' => DocumentationStatus::class,
            'exterior_condition' => VehicleCondition::class,
            'interior_condition' => VehicleCondition::class,
            'year' => 'integer',
            'mileage' => 'integer',
            'price' => 'integer',
            'original_parts_percentage' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<CarMaker, $this>
     */
    public function maker(): BelongsTo
    {
        return $this->belongsTo(CarMaker::class, 'car_maker_id');
    }

    /**
     * @return BelongsTo<CarModel, $this>
     */
    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class, 'car_model_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /**
     * @return HasMany<ListingImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('position');
    }

    /**
     * @return HasOne<ListingImage, $this>
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ListingImage::class)->ofMany('position', 'min');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function publiclyVisible(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), ListingStatus::Approved)
            ->whereHas('owner', fn (Builder $owner) => $owner->where('status', UserStatus::Active));
    }

    public function isPending(): bool
    {
        return $this->status === ListingStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === ListingStatus::Approved;
    }

    public function isRejected(): bool
    {
        return $this->status === ListingStatus::Rejected;
    }

    public function isPublic(): bool
    {
        return $this->isApproved() && $this->owner->status === UserStatus::Active;
    }

    public function isOwnedBy(?User $user): bool
    {
        return $this->owner()->is($user);
    }

    /**
     * @return array{loc: string, lastmod: ?string}
     */
    public function toSitemapArray(): array
    {
        return [
            'loc' => CanonicalUrl::route('listings.show', $this),
            'lastmod' => $this->updated_at?->toAtomString(),
        ];
    }

    public function hasCustomModel(): bool
    {
        return $this->car_model_id === null;
    }

    public function canBeApproved(): bool
    {
        return $this->isPending();
    }

    public function canBeRejected(): bool
    {
        return $this->isPending();
    }

    public function canPromoteModel(): bool
    {
        return $this->isPending() && $this->hasCustomModel();
    }

    public function shouldReturnToPendingOnEdit(): bool
    {
        return $this->isApproved() || $this->isRejected();
    }

    public function moderationVersion(): int
    {
        return (int) $this->updated_at?->getTimestamp();
    }
}
