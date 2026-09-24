<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Data\Enums;

/**
 * What an alert is about. The set grows: an alert of a type newer than this
 * package still arrives, with `Alert::$type` null and the raw value in
 * `Alert::$typeValue`.
 */
enum AlertType: string
{
    case ChannelHealthLow = 'channel.health_low';
    case ChannelDisconnected = 'channel.disconnected';
    case ChannelFailureSpike = 'channel.failure_spike';
    case ChannelDailyLimit = 'channel.daily_limit';
    case QuotaMonthlyNear = 'quota.monthly_near';
    case ComplianceViolation = 'compliance.violation';
    case BitrixDispatchFailed = 'bitrix.dispatch_failed';
    case ChannelRatingDrop = 'channel.rating_drop';
}
