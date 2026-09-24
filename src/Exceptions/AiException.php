<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Exceptions;

/**
 * The AI surface is off or cannot act. `ai.disabled` means the cabinet has not
 * consented to AI processing; `ai.profile_no_conversation` that there is no
 * conversation with text to read.
 */
class AiException extends LinqelioException {}
