<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per document kind per year, holding the last number handed out.
 *
 * @property int $id
 * @property DocumentType $type
 * @property int $year
 * @property int $last_number
 */
#[Fillable(['type', 'year', 'last_number'])]
class DocumentSequence extends Model
{
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
