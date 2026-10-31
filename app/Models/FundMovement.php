<?php

namespace App\Models;

use App\Enums\FundMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $fund_id
 * @property int|null $transaction_id
 * @property FundMovementType $type
 * @property int $amount Signed: positive into the fund, negative out of it.
 * @property string|null $note
 * @property Carbon $date
 */
#[Fillable(['fund_id', 'transaction_id', 'type', 'amount', 'note', 'date'])]
class FundMovement extends Model
{
    protected function casts(): array
    {
        return [
            'type' => FundMovementType::class,
            'amount' => 'integer',
            'date' => 'date',
        ];
    }

    /** @return BelongsTo<Fund, $this> */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
