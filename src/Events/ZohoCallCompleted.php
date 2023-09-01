<?php

namespace Portable\EloquentZoho\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\SerializesModels;

class ZohoCallCompleted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Response $response
    ) {
    }

    public function successful(): bool
    {
        return $this->response->successful();
    }
}
