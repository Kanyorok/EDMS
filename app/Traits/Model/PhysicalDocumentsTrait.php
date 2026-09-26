<?php

namespace App\Traits\Model;

use App\Models\DMS\PhysicalDocument;
use App\Models\DMS\PhysicalDocumentRelation;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait PhysicalDocumentsTrait
{
    public function physicalDocumentRelations(): MorphMany
    {
        return $this->morphMany(
            PhysicalDocumentRelation::class,
            'related',
            'Related',
            'RelatedID',
            $this->getKeyName()
        );
    }

    public function physicalDocuments(): HasManyThrough
    {
        return $this->hasManyThrough(
            PhysicalDocument::class,
            PhysicalDocumentRelation::class,
            'RelatedID',
            'Id',
            $this->getKeyName(),
            'PhysicalDocumentId'
        )->where('t_DMSPhysicalDocumentRelations.Related', static::getPrimaryKey());
    }
}
