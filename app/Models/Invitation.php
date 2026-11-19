<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $email
 * @property Role $role
 * @property string $token_hash
 * @property int|null $invited_by
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read User|null $inviter
 */
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Invitations are created only by InviteTeamMember, which sets every
     * attribute explicitly.
     *
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @var list<string>
     */
    protected $hidden = ['token_hash'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }
}
