<?php

namespace App\Actions\Funds;

use App\Enums\FundMovementType;
use App\Models\Fund;
use App\Models\FundMovement;
use Illuminate\Support\Facades\DB;

/**
 * Moves a label from one fund to another. No real money changes hands, so
 * there is no account transaction — only a pair of fund movements.
 */
class TransferBetweenFunds
{
    public function handle(
        Fund $from,
        Fund $to,
        int $amount,
        string $date,
        ?string $note = null,
    ): void {
        DB::transaction(function () use ($from, $to, $amount, $date, $note): void {
            FundMovement::query()->create([
                'fund_id' => $from->id,
                'type' => FundMovementType::TransferOut,
                'amount' => -$amount,
                'date' => $date,
                'note' => $note,
            ]);

            FundMovement::query()->create([
                'fund_id' => $to->id,
                'type' => FundMovementType::TransferIn,
                'amount' => $amount,
                'date' => $date,
                'note' => $note,
            ]);
        });
    }
}
