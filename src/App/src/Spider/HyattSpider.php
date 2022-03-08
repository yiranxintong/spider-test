<?php

namespace App\Spider;

use App\Behavior\LoggerAwareTrait;
use App\Container\HttpHelper;
use Nesk\Puphpeteer\Puppeteer;
use Nesk\Rialto\Exceptions\Node;
use Psr\Log\LoggerAwareInterface;

class HyattSpider implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private HttpHelper $http;

    public function __construct(HttpHelper $http)
    {
        $this->http = $http;
    }

    public function reveal(array $options) : array
    {
        $puppeteer = new Puppeteer([
            'read_timeout' => $options['read_timeout'], // In seconds
            'log_browser_console' => false,
            'idle_timeout' => $options['idle_timeout'],
            'ignore_https_errors' => true,
        ]);
        $browser = $puppeteer->launch([
            'env' => [
                'TZ' => 'Asia/Shanghai',
            ],
            'args' => ['--no-sandbox'],
            'headless' => $options['headless'],
            'slowMo' => 500,
            'devtools' => $options['devtools'],
            'defaultViewport' => ['width' => 1200, 'height' => 900],
        ]);

        $page = $browser->newPage();
        try {
            $page->tryCatch->goto('https://www.baidu.com');
            $this->log('do something');
        } catch (Node\Exception $exception) {
            // Handle the exception...
            $this->log($exception->getMessage());
        }

        $browser->close();

        return [];
    }
}