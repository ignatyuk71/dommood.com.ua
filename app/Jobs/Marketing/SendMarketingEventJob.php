<?php

namespace App\Jobs\Marketing;

use App\Services\Marketing\MarketingEventService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendMarketingEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly int $outboxId)
    {
        $this->onQueue('integrations');
    }

    public function backoff(): array
    {
        return [30, 120, 300, 900, 1800];
    }

    public function handle(MarketingEventService $events): void
    {
        $events->dispatchOutbox($this->outboxId);
    }

    public function failed(Throwable $exception): void
    {
        app(MarketingEventService::class)->markOutboxAsFailed($this->outboxId, $exception->getMessage());
    }
}
