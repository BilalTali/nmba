<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a sync failure is caused by transient infrastructure faults:
 * connection timeouts, socket hangs, Guzzle drops, HTTP 500/503 states,
 * or mid-flight session expirations. These failures are retriable via the queue.
 */
class TransientSyncException extends Exception {}
