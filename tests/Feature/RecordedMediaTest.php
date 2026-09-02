<?php

declare(strict_types=1);

use Linqelio\Laravel\Data\Enums\MessageType;
use Linqelio\Laravel\Data\MediaContent;
use Linqelio\Laravel\Data\Message;

/**
 * Telling a recorded message from a file of the same type.
 *
 * A voice message and a video note ("кружечок") arrive as ordinary `audio` and
 * `video`: the difference is how they are presented, not what they carry. That
 * makes them indistinguishable from an attached clip unless something says so —
 * and it is the platform's `note` flag that says it. Until this package read it,
 * an incoming round note could not be recognised; until it sent it, one could
 * not be sent at all.
 *
 * The type deliberately stays coarse on the wire: a member of its own would make
 * every channel that has not learned it refuse the message outright, whereas an
 * unread flag only costs the shape.
 */
it('recognises a recorded note arriving on an ordinary media type', function (): void {
    $message = new Message(
        id: 'm-1',
        direction: 'inbound',
        type: MessageType::Video,
        content: ['media' => [
            'url' => 'https://platform.test/v1/messages/m-1/media',
            'mime' => 'video/mp4',
            'note' => true,
        ]],
        timestamp: new DateTimeImmutable('2026-09-02T12:00:00+00:00'),
    );

    expect($message->media()?->note)->toBeTrue();
});

it('reads an attached file as an ordinary attachment', function (): void {
    $media = MediaContent::fromArray([
        'url' => 'https://platform.test/v1/messages/m-2/media',
        'mime' => 'video/mp4',
        'filename' => 'promo.mp4',
    ]);

    expect($media->note)->toBeFalse();
});

it('asks for the recorded presentation when sending one', function (): void {
    $media = new MediaContent(
        url: 'https://platform.test/v1/media/abc',
        mime: 'audio/ogg',
        note: true,
    );

    expect($media->toContent()['media']['note'])->toBeTrue();
});

/*
 * `note: false` is not the same statement as "no note": on an ordinary
 * attachment the field simply does not apply, and sending it would also hand a
 * field to platforms too old to read it.
 */
it('leaves the flag off an ordinary attachment instead of denying it', function (): void {
    $media = new MediaContent(
        url: 'https://platform.test/v1/media/def',
        mime: 'video/mp4',
        filename: 'promo.mp4',
    );

    expect($media->toContent()['media'])->not->toHaveKey('note');
});
