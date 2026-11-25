<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Exceptions;

/**
 * A conversation or group operation was refused: the conversation is not found
 * or not a group, or the members could not be resolved to addresses — or, for
 * `chats()`, the Telegram chat is not among the account's chats
 * (`chat.not_found`) or the account is not an admin there (`chat.admin_required`).
 */
class ConversationException extends LinqelioException {}
