<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use RuntimeException;

/**
 * A tenant-supplied URL the server refuses to call: unsupported scheme, a host
 * that does not resolve, or one that resolves to a non-public address.
 */
final class UnsafeOutboundUrlException extends RuntimeException {}
