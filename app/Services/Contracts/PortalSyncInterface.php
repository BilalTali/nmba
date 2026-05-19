<?php

namespace App\Services\Contracts;

use App\Models\Event;

/**
 * Contract for the portal synchronization service.
 * Concrete implementations must handle stateful HTTP session management,
 * CSRF token extraction, and multipart form submission to the target portal.
 */
interface PortalSyncInterface
{
    /**
     * Synchronize a local event record to the external portal.
     *
     * @param Event $event The event model to synchronize.
     * @return bool True on confirmed successful submission.
     * @throws \App\Exceptions\TransientSyncException For retriable network/session faults.
     * @throws \App\Exceptions\PermanentSyncException For unrecoverable validation/config faults.
     */
    public function sync(Event $event): bool;
}
