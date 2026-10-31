<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The audit trail (PLAN.md phase 6).
 *
 * Read only, by design: a trail anyone can edit answers nothing.
 */
class AuditLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $type = $request->string('type')->value();
        $event = $request->string('event')->value();

        $types = [
            'transaction' => Transaction::class,
            'invoice' => Invoice::class,
        ];

        $logs = AuditLog::query()
            ->with('user')
            ->when(
                isset($types[$type]),
                fn (Builder $q) => $q->where('auditable_type', $types[$type]),
            )
            ->when(
                in_array($event, ['created', 'updated', 'deleted'], true),
                fn (Builder $q) => $q->where('event', $event),
            )
            ->ordered()
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('settings/AuditLog', [
            'logs' => [
                'data' => $logs->getCollection()->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'event' => $log->event,
                    'type' => mb_strtolower($log->subjectType()),
                    'subject' => $log->subject,
                    'user_name' => $log->user_name,
                    'changes' => $log->changes,
                    'created_at' => $log->created_at->toIso8601String(),
                ])->all(),
                'meta' => [
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem(),
                    'total' => $logs->total(),
                    'links' => $logs->linkCollection()->toArray(),
                ],
            ],
            'filters' => [
                'type' => isset($types[$type]) ? $type : null,
                'event' => in_array($event, ['created', 'updated', 'deleted'], true)
                    ? $event
                    : null,
            ],
        ]);
    }
}
