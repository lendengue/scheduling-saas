<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\StaffProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The professional side of a team member: whether customers can book them,
 * which services they perform, their weekly hours and time off.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $user_id
 * @property string|null $bio
 * @property string $color
 * @property bool $is_bookable
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read User $user
 * @property-read Collection<int, Service> $services
 * @property-read Collection<int, WorkingHour> $workingHours
 * @property-read Collection<int, TimeOff> $timeOffs
 */
#[Fillable(['bio', 'color', 'is_bookable'])]
class StaffProfile extends Model
{
    /** @use HasFactory<StaffProfileFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Agenda colors handed out to new members, in order.
     */
    public const array COLORS = ['#2563eb', '#16a34a', '#db2777', '#ea580c', '#7c3aed', '#0891b2', '#ca8a04', '#dc2626'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_bookable' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_bookable' => 'boolean',
        ];
    }

    /**
     * Next color of the palette for the current tenant.
     */
    public static function nextColor(): string
    {
        return self::COLORS[self::query()->count() % count(self::COLORS)];
    }

    /**
     * The profile of a member of the current tenant, created on first use.
     */
    public static function forUser(User $user, bool $bookable = false): self
    {
        $profile = self::query()->where('user_id', $user->getKey())->first();

        if ($profile === null) {
            $profile = new self(['color' => self::nextColor(), 'is_bookable' => $bookable]);
            $profile->user_id = $user->getKey();
            $profile->save();
        }

        return $profile;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_staff');
    }

    /**
     * @return HasMany<WorkingHour, $this>
     */
    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHour::class)->orderBy('weekday')->orderBy('starts_at');
    }

    /**
     * @return HasMany<TimeOff, $this>
     */
    public function timeOffs(): HasMany
    {
        return $this->hasMany(TimeOff::class)->orderBy('starts_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function bookable(Builder $query): void
    {
        $query->where('is_bookable', true);
    }
}
