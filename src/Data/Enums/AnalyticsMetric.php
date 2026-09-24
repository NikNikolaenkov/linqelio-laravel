<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * A metric the analytics reports know.
 */
enum AnalyticsMetric: string
{
    case MessagesIn = 'messages_in';
    case MessagesOut = 'messages_out';
    case MessagesDelivered = 'messages_delivered';
    case MessagesRead = 'messages_read';
    case MessagesFailed = 'messages_failed';
    case DeliveryRate = 'delivery_rate';
    case ReadRate = 'read_rate';
    case FailureRate = 'failure_rate';
    case ConversationsOpened = 'conversations_opened';
    case Responses = 'responses';
    case ResponseTimeAvg = 'response_time_avg';
    case ResponsesWithin5mRate = 'responses_within_5m_rate';
    case PolicyRefusals = 'policy_refusals';
    case PolicyWarnings = 'policy_warnings';
    case HealthAvg = 'health_avg';
    case AiRuns = 'ai_runs';
    case AiTokens = 'ai_tokens';
    case AiCost = 'ai_cost';
}
