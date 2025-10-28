<?php
/**
 * StorageException
 *
 * Exception thrown when storage operations cannot be completed.
 */
class StorageException extends \Exception {
    public function __construct(string $reason = 'STORAGE_ERROR', string $message = '') {
        parent::__construct($message ?: $reason);
    }
}

/**
 * Application-specific exceptions
 */
class InvalidInputException extends \Exception {
    public function __construct(string $reason = 'INVALID_INPUT', string $message = '') {
        parent::__construct($message ?: $reason);
    }
}
