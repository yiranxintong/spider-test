<?php

namespace App\Spider;

use Nesk\Puphpeteer\Puppeteer;
use Nesk\Rialto\Exceptions\Node;

class MarriottSpider
{
    public function reveal() : array
    {
        $puppeteer = new Puppeteer();
        $browser = $puppeteer->launch();

        $page = $browser->newPage();
        try {
            $page->tryCatch->goto('https://www.baidu.com');
        } catch (Node\Exception $exception) {
            // Handle the exception...
        }

        $browser->close();

        return [];
    }
}