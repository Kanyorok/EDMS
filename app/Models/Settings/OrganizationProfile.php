<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class OrganizationProfile extends Model
{
    protected $table = 't_SystemBankSetting';
    protected $primaryKey = 'Id';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['Configuration' => 'object'];

    public static function current(): ?self
    {
        return static::query()->orderByDesc('Id')->first();
    }
}
