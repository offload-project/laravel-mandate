<?php

declare(strict_types=1);

namespace OffloadProject\Mandate\Exceptions;

use RuntimeException;

/**
 * Exception thrown when circular role inheritance is detected.
 *
 * This occurs when role A inherits from role B, which inherits from role A
 * (directly or through a chain of other roles).
 */
final class CircularRoleInheritanceException extends RuntimeException {}
