<?php

namespace App\Command;

use App\Behavior\LoggerAwareTrait;
use App\Container\UserAgent;
use App\Spider\HyattSpider;
use Redis;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class ScrapeHyattHotelsCommand extends Command
{
    use LoggerAwareTrait;

    public const COMMAND_NAME  = 'scrape:hyatt-hotel';
    private const COMMAND_DESCRIPTION  = 'scrape hyatt hotel command';

    private Redis $redis;
    private HyattSpider $spider;

    public function __construct(Redis $redis, HyattSpider $spider)
    {
        parent::__construct();

        $this->redis = $redis;
        $this->spider = $spider;
    }

    protected function configure() : void
    {
        parent::configure();

        $this->setName(static::COMMAND_NAME);
        $this->setDescription(static::COMMAND_DESCRIPTION);
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $this->logger = new ConsoleLogger($output);
        $this->spider->setLogger($this->logger);

        $this->log('Bootstrapping...');

        $isProduction = getenv('APPLICATION_ENV') === 'production';
        $options = [
            'read_timeout' => 240000,
            'idle_timeout' => 240000,
            'headers' => [
                'User-Agent' => UserAgent::OSX_DESKTOP,
            ],
            'headless' => $isProduction,
            'devtools' => $isProduction,
        ];
        $hotels = $this->spider->reveal($options);

        return Command::SUCCESS;
    }

}