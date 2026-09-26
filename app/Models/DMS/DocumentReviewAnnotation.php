<?php

namespace App\Models\DMS;

use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentReviewAnnotation extends Model
{
    use SoftDeletes;
    use UserActorTrait;

    public const string CREATED_AT = 'CreatedOn';
    public const string UPDATED_AT = 'ModifiedOn';
    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_DocumentReviewAnnotations';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'DocumentReviewId', 'Page', 'X', 'Y', 'Comment',
        'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    protected $casts = [
        'DocumentReviewId' => 'integer',
        'Page' => 'integer',
        'X' => 'float',
        'Y' => 'float',
        'CreatedBy' => 'integer',
        'ModifiedBy' => 'integer',
    ];

    public static function getPrimaryKey(): string
    {
        return 'DocumentReviewAnnotationId';
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(DocumentReview::class, 'DocumentReviewId');
    }
}
