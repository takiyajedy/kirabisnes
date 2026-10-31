<?php

namespace App\Http\Controllers;

use App\Http\Resources\FundResource;
use App\Http\Resources\TransactionResource;
use App\Services\OwnerPayService;
use App\Support\TransactionFormOptions;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class OwnerPayController extends Controller
{
    public function __construct(private OwnerPayService $ownerPay) {}

    public function index(): Response
    {
        $fund = $this->ownerPay->fund();
        $fund->balance = $this->ownerPay->balance($fund);

        return Inertia::render('OwnerPay/Index', [
            'fund' => FundResource::make($fund)->resolve(),
            'suggestion' => [
                'amount' => $this->ownerPay->safeDraw($fund),
                'average_income' => $this->ownerPay->averageMonthlyIncome(),
                'months' => OwnerPayService::MONTHS,
                'percentage' => (float) $fund->percentage,
            ],
            'drawnThisYear' => $this->ownerPay->drawnThisYear(),
            'year' => CarbonImmutable::today()->year,
            'history' => TransactionResource::collection(
                $this->ownerPay->history(),
            )->response()->getData(true),
            'formOptions' => TransactionFormOptions::all(),
        ]);
    }
}
