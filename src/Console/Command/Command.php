<?php

namespace Trunk\Console\Command;

use Trunk\App;
use Trunk\Console\Command\Interface\CommandInterface;

abstract class Command implements CommandInterface
{
    public function __construct(protected readonly App $app)
    {
    }
}
