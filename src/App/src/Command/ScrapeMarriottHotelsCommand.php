<?php

namespace App\Command;

use App\Spider\MarriottSpider;
use Redis;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ScrapeMarriottHotelsCommand extends Command
{
    public const COMMAND_NAME  = 'scrape:marriott-hotel';
    private const COMMAND_DESCRIPTION  = 'scrape marriott hotel command';

    private Redis $redis;
    private MarriottSpider $spider;

    public function __construct(Redis $redis, MarriottSpider $spider)
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
        $output->writeln('Bootstrapping...');

        $hotels = $this->spider->reveal();

        return Command::SUCCESS;
    }

}