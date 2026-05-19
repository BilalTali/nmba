<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a sync failure is unrecoverable:
 * portal form validation rejections, bad admin credentials,
 * or critical structural markup modifications. These failures are dead-lettered.
 */
class PermanentSyncException extends Exception {}
