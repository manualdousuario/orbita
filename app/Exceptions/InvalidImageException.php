<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an uploaded file is not an acceptable image.
 */
class InvalidImageException extends RuntimeException {}
