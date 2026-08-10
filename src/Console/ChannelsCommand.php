<?php

declare(strict_types=1);

namespace Linqelio\Laravel\Console;

use Illuminate\Console\Command;
use Linqelio\Laravel\Exceptions\ConfigurationException;
use Linqelio\Laravel\Exceptions\LinqelioException;
use Linqelio\Laravel\Facades\Linqelio;

/**
 * The first thing to run after configuring the package: it proves the URL, the
 * key and the network path all work, and says which channels can carry a message.
 */
final class ChannelsCommand extends Command
{
    protected $signature = 'linqelio:channels';

    protected $description = 'List the channels configured on your Linqelio cabinet';

    public function handle(): int
    {
        try {
            $channels = Linqelio::channels()->list();
        } catch (ConfigurationException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (LinqelioException $e) {
            $this->error(sprintf('[%s] %s', $e->errorCode()->value, $e->getMessage()));

            if ($e->requestId() !== null) {
                $this->line('  request id: '.$e->requestId());
            }

            return self::FAILURE;
        }

        if ($channels === []) {
            $this->warn('No channels yet. Create one with linqelio:channels or in the console.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'kind', 'state', 'name'],
            array_map(static fn ($c): array => [
                $c->id,
                $c->kind->label(),
                $c->isActive() ? '<info>'.$c->state.'</info>' : '<comment>'.$c->state.'</comment>',
                $c->name ?? '—',
            ], $channels),
        );

        return self::SUCCESS;
    }
}
