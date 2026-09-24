<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Exceptions;

/**
 * A WhatsApp Business template send or sync was refused: the template is not
 * found (sync the channel), not approved, not sendable, or its params do not fit.
 */
class TemplateException extends LinqelioException {}
