<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Something a business sells by the minute: a haircut, a consultation...
 *
 * The time a booking blocks in the agenda is the duration plus the buffer
 * (cleanup, preparation) that follows it.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $description
 * @property int $duration_minutes
 * @property int $buffer_after_minutes
 * @property int $price_cents
 * @property bool $is_active
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 * @property-read Collection<int, StaffProfile> $staffProfiles
 */
#[Fillable(['name', 'description', 'duration_minutes', 'buffer_after_minutes', 'price_cents', 'is_active'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'buffer_after_minutes' => 0,
        'price_cents' => 0,
        'is_active' => true,
        'position' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'buffer_after_minutes' => 'integer',
            'price_cents' => 'integer',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * Minutes a booking of this service blocks in the agenda.
     */
    public function blockedMinutes(): int
    {
        return $this->duration_minutes + $this->buffer_after_minutes;
    }

    /**
     * Professionals who perform this service.
     *
     * @return BelongsToMany<StaffProfile, $this>
     */
    public function staffProfiles(): BelongsToMany
    {
        return $this->belongsToMany(StaffProfile::class, 'service_staff');
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
        $query->orderBy('position')->orderBy('id');
    }
}
