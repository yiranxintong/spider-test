<?php

namespace App\Spider;

use App\Behavior\LoggerAwareTrait;
use App\Container\HttpHelper;
use Nesk\Puphpeteer\Puppeteer;
use Nesk\Rialto\Exceptions\Node;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;

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
            'ignoreDefaultArgs' => [
                '--enable-automation',
            ],
        ]);

        $page = $browser->newPage();
        $page->setUserAgent($options['headers']['User-Agent']);
        $page->setExtraHTTPHeaders([
            'cookie' => 'tkrm_alpekz_s1.3-ssn=03qoODHVjGQWOEollIX9z2HCsKloVQFFFLRL8kfSFIOwLpovYzF315a58Zf10CWRvNZCngiG2mguhXW6xOJfaLEQVVIdfu2Tkgv5QYKrsi4cvJqFcQ5Qoj6uI8JUTX7yJ9Hv69dMLP23xqxzTXUl4GI'
        ]);

        try {
            $page->tryCatch->goto('https://www.hyatt.com/zh-CN/search/%E4%B8%8A%E6%B5%B7?checkinDate=2022-03-09&checkoutDate=2022-03-10&rooms=1&adults=1&kids=0&rate=Standard&rateFilter=woh', [
                'timeout'      => 240000, // In milliseconds
                'idle_timeout' => 240000,
            ]);
            $this->log('do something');
        } catch (Node\Exception $exception) {
            // Handle the exception...
            $this->log($exception->getMessage());
        }

        $browser->close();

        return [];
    }

    public function crawl(array $options) : array
    {
        $httpClient = HttpClient::create($options);
        $browser = new HttpBrowser($httpClient);

        $crawler = $browser->request('GET', 'https://www.hyatt.com/zh-CN/search/%E5%8C%97%E4%BA%AC?checkinDate=2022-03-09&checkoutDate=2022-03-10&rooms=1&adults=1&kids=0&rate=Standard&rateFilter=woh');
        $html = $crawler->html();
        return [];
    }
}