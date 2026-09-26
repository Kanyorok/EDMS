<?php

namespace App\Models\DMS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentDashboardWidgets extends Model
{ 
    use SoftDeletes;

    protected $table = 't_DocumentDashboardWidgets';
    public const CREATED_AT = 'CreatedOn';
    public const UPDATED_AT = 'ModifiedOn';
    public const DELETED_AT = 'DeletedOn';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'WidgetTitle',
        'WidgetKey',
        'WidgetType',
        'WidgetSize',
        'WidgetView',
        'WidgetConfig',
        'CreatedBy',
        'ModifiedBy',
        'DeletedBy',
    ];

    protected $casts = [
        'WidgetConfig' => 'array',
        'WidgetSize' => 'string',
    ];

}
