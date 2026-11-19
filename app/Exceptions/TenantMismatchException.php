<?php

namespace App\Exceptions;

use LogicException;

/**
 * Thrown when code tries to write a tenant-scoped record that belongs to a
 * tenant other than the current one, or to move a record between tenants.
 */
class TenantMismatchException extends LogicException {}
