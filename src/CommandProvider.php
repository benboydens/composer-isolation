<?php

namespace Ox6d617474\Isolate;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

final class CommandProvider implements CommandProviderCapability
{
    /**
     * {@inheritdoc}
     */
    public function getCommands(): array
    {
        return [new Command()];
    }
}
