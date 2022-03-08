<?php

namespace App\Spider;

use DateTimeImmutable;
use Exception;
use Nesk\Puphpeteer\Puppeteer;
use Nesk\Puphpeteer\Resources;
use Nesk\Rialto\Data\JsFunction;
use Nesk\Rialto\Exceptions\Node;
use Psr\Log\LoggerAwareInterface;
use App\Behavior\LoggerAwareTrait;
use App\Container\HttpHelper;

class MarriottSpider implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private HttpHelper $http;

    public function __construct(HttpHelper $http)
    {
        $this->http = $http;
    }

    public function reveal(string $link, string $city, array $options, DateTimeImmutable $startDate) : array
    {
        $puppeteer = new Puppeteer([
            'read_timeout' => $options['read_timeout'], // In seconds
            'log_browser_console' => false,
            'idle_timeout' => $options['idle_timeout'],
            'ignore_https_errors' => true,
        ]);
        /** @var Resources\Browser $browser */
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

        /** @var Resources\Page $page */
        $page = $browser->newPage();

        $page->setUserAgent($options['headers']['User-Agent']);
        $startDate = $startDate->getTimestamp() * 1000;
        $endDate = $startDate + 86400000;
        try {
            $page->tryCatch->goto($link, [
                'timeout'      => 240000, // In milliseconds
                'idle_timeout' => 240000,
            ]);

            $query = ['searchTerm' => $city . ',中国'];
            $response = $this->http->getJson("https://www.marriott.com.cn/aries-search/v2/autoComplete.comp?" . http_build_query($query));
            $response = $response['suggestions'][0] ?? [];
            $placeId = $response['placeId'] ?? '';
            $primaryDescription = $response['primaryDescription'] ?? '';
            $secondaryDescription = $response['secondaryDescription'] ?? '';
            $html = "<li class='autocomplete-listitem  autocomplete-listitem-active  autocomplete-listitem-recently' role='menuitem'>
<a data-index='0' data-place-id='{$placeId}' data-primary-description='{$primaryDescription}' data-secondary-description='{$secondaryDescription}'>
<strong>{$primaryDescription}</strong>, {$secondaryDescription} </a>
</li>";
            $page->tryCatch->click("#advanced-search-form input[name='destinationAddress.destination");
            $searchCal = <<<JS
            return new Promise((resolve, reject) => {
                document.querySelector('#advanced-search-form .autocomplete-scroller-wrapper.custom-wrapper').style.display='block';
                let ul = document.querySelector('#advanced-search-form .autocomplete-scroller-wrapper.custom-wrapper ul');
                ul.style.display='block';
                console.log(html)
                ul.innerHTML= html;
                resolve();
            });
JS;
            $page->tryCatch->evaluate(new JsFunction(['html' => $html], $searchCal));
            $page->tryCatch->click("#advanced-search-form .autocomplete-scroller-wrapper.custom-wrapper ul li");

            $page->tryCatch->click("#advanced-search-form input[name='useRewardsPoints']");
            $page->tryCatch->click("#advanced-search-form .ccheckin-container.date-picker-container");

            $this->checkMonth($page, $endDate);

            $page->tryCatch->click("#advanced-search-form td[data-t-date='{$startDate}']");
            $page->tryCatch->click("#advanced-search-form td[data-t-date='{$endDate}']");
            $page->tryCatch->click("#advanced-search-form button[type='submit']");
            //$page->tryCatch->click("#availabilityFilterToggle");
            $scroll = <<<JS
            return new Promise((resolve, reject) => {
                let totalHeight = 0;
                let distance = 100;
                let timer = setInterval(() => {
                    let scrollHeight = document.body.scrollHeight;
                    window.scrollBy(0, distance);
                    totalHeight += distance;
                     if(totalHeight >= scrollHeight){
                        clearInterval(timer);
                        resolve();
                    }
                }, 100);
            });
JS;
            $page->tryCatch->evaluate(new JsFunction([], $scroll));
            $this->log('Scrolled to the bottom of the page.');

            $hotelBasicInfoCallback = <<<'JS'
            return elements.map(element => {
              let english_name = element.querySelector('.js-hotel-location h2:nth-child(2) span');
              if (english_name) {
                english_name = english_name.innerText;
              }
              let property = JSON.parse(element.getAttribute('data-property'));
              let address = element.querySelector('.t-line-height-m.m-hotel-address');
              let url = null;
              let urlNode = element.querySelector('.js-view-rate-btn-link.analytics-click.l-float-right');
              if (urlNode) {
                url = urlNode.href;
              }
              let rating = 5;
              let ratingNode = element.querySelector('.m-ratings.t-font-xxs');
              if(ratingNode){
                rating = ratingNode.getAttribute('data-rating');
              }
              let _class = null;
              let _classNode = element.querySelector('.js-view-hotel-category.t-alt-link');
              if(_classNode){
                _class = _classNode.innerHTML.replace('类别','').trim();
              }
              let points = null;
              if(element.querySelector('.t-points.t-point-saver-point')){
                points =element.querySelector('.t-points.t-point-saver-point').innerHTML.replace(',','');
              }

              let is_pointsaver = 0;
              if(element.querySelector('.t-point-saver')){
                is_pointsaver =1;
              }
              let price = null;
              let priceNode = element.querySelector(".t-price");
              if(priceNode){
                price = priceNode.innerHTML.replace(',','');
              }

              let image = null;
              let imageNode = element.querySelector('.l-hotel-picture img');
              if(imageNode && imageNode.hasAttribute('data-src')){
                image = imageNode.getAttribute('data-src');
              }else if(imageNode){
                image = element.querySelector('.l-hotel-picture img').src;
              }

              return {
                city : address.getAttribute('data-city'),
                name: property.hotelName,
                english_name : english_name,
                hotel_code : property.marshaCode,
                brand_id : property.brand,
                address : address.innerHTML.trim(),
                tel : address.getAttribute('data-contact'),
                url : url,
                image : image,
                rating : rating,
                class : _class,
                reg_points : points,
                is_pointsaver : is_pointsaver,
                price : price
              }
            });
JS;
            $hotelResults = $page->querySelectorAllEval('#merch-property-results .property-record-item', new JsFunction(['elements'], $hotelBasicInfoCallback));
            $this->log(sprintf('Found %d hotels,', count($hotelResults)));
            $selectHandle = <<<JS
    return elements;
JS;
            $currPage = 1;
            while (count($hotelResults) >= 40 * $currPage) {
                $currPage++;
                $this->log('page ' . $currPage);
                $next = $page->querySelectorAllEval("#results-pagination .m-pagination-next", new JsFunction(['elements'], $selectHandle));
                if (count($next)) {
                    $page->tryCatch->click("#results-pagination .m-pagination-next");
                    $page->tryCatch->evaluate(new JsFunction([], $scroll));
                    $this->log('Scrolled to the bottom of the page.');
                    $hotelResults2 = $page->querySelectorAllEval('#merch-property-results .property-record-item', new JsFunction(['elements'], $hotelBasicInfoCallback));
                    $this->log(sprintf('Found %d hotels,', count($hotelResults2)));
                    foreach ($hotelResults2 as $item) {
                        array_push($hotelResults, $item);
                    }
                }
            }
        } catch (Node\Exception $exception) {
            $this->log(sprintf('Oops! We caught a exception: %s. :(', $exception->getMessage()));
        }
        return $hotelResults ?? [];
    }

    private function checkMonth(Resources\Page $page, string $endDate) : void
    {
        $lastTdCal = <<<JS
    let td = element.querySelectorAll('td:not(.t-disabled)');
    return td[td.length - 1].getAttribute("data-t-date");
JS;
        $lastTd = $page->querySelectorEval("#advanced-search-form", new JsFunction(['element'], $lastTdCal));

        if ((int) $lastTd < (int) $endDate) {
            $nextTh = $page->querySelector("#advanced-search-form .t-arrow.t-next:not(.t-disabled)");
            if (empty($nextTh)) {
                throw new Exception('out of date');
            }
            $nextTh->click();
            $this->checkMonth($page, $endDate);
        }
    }
}
