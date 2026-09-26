<?php

namespace App\Models\DMS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentDashboardLayouts extends Model
{
    use SoftDeletes;

    protected $table = 't_ProcurementDashboardLayouts';
    public const CREATED_AT = 'CreatedOn';
    public const UPDATED_AT = 'ModifiedOn';
    public const DELETED_AT = 'DeletedOn';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'WidgetKey',
        'x',
        'y',
        'w',
        'h',
        'SortOrder',
        'Config',

        'CreatedBy',
        'ModifiedBy',
        'DeletedBy',
    ];

    /**
     * Cast JSON config into array automatically
     */
    protected $casts = [
        'Config' => 'array',
    ];

    /**
     * OPTIONAL: GridStack compatibility helper
     */
    public function getGridAttribute()
    {
        return [
            'widget_key' => $this->WidgetKey,
            'x' => $this->x,
            'y' => $this->y,
            'w' => $this->w,
            'h' => $this->h,
            'sort_order' => $this->SortOrder,
            'config' => $this->Config ?? [],
        ];
    }

    /**
     * OPTIONAL: Convert to GridStack format directly
     */
    public function toGridStack()
    {
        return [
            'id' => $this->Id,
            'x' => $this->x,
            'y' => $this->y,
            'w' => $this->w,
            'h' => $this->h,
            'content' => $this->WidgetKey,
            'config' => $this->Config,
        ];
    }
}
