<?php

namespace App\Models\DMS;

use App\Enums\DMS\DocumentReviewStatusEnum;
use App\Models\Auth\Team;
use App\Models\Auth\User;
use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentReview extends Model
{
    use SoftDeletes;
    use UserActorTrait;

    public const string CREATED_AT = 'CreatedOn';
    public const string UPDATED_AT = 'ModifiedOn';
    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_DocumentReviews';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'DocumentId', 'VersionId', 'ReviewerId', 'ReviewerTeamId', 'Status', 'RequestNote', 'ReviewComment', 'ReviewedOn',
        'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    protected $casts = [
        'DocumentId' => 'integer',
        'VersionId' => 'integer',
        'ReviewerId' => 'integer',
        'ReviewerTeamId' => 'integer',
        'CreatedBy' => 'integer',
        'ModifiedBy' => 'integer',
        'Status' => DocumentReviewStatusEnum::class,
        'ReviewedOn' => 'datetime',
    ];

    public static function getPrimaryKey(): string
    {
        return 'DocumentReviewId';
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'DocumentId');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'VersionId');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ReviewerId');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'ReviewerTeamId', 'TeamID');
    }

    /**
     * Whether this user is a reviewer of this round: the named reviewer, or a member of the
     * team it was assigned to.
     */
    public function isReviewer(User $user): bool
    {
        if ($this->ReviewerId !== null && $this->ReviewerId === $user->Id) {
            return true;
        }

        // Membership always counts, also after a member has completed the review and so become ReviewerId.
        return $this->ReviewerTeamId !== null
            && $this->team?->users()->where('t_Users.Id', $user->Id)->exists() === true;
    }

    /** Who the review is with, for display: the reviewer's name, or the team's. */
    public function reviewerName(): string
    {
        return $this->reviewer?->Name ?? ($this->team ? $this->team->Name . ' (team)' : '');
    }

    public function annotations(): HasMany
    {
        return $this->hasMany(DocumentReviewAnnotation::class, 'DocumentReviewId')->orderBy('Id');
    }

    public function isReviewed(): bool
    {
        return $this->Status === DocumentReviewStatusEnum::Reviewed;
    }
}
