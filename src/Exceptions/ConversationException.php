<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Exceptions;

/**
 * A conversation or group operation was refused: the conversation is not found
 * or not a group, or the members could not be resolved to addresses.
 */
class ConversationException extends LinqelioException {}
