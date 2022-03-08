<?php

namespace App\Command;

use App\Behavior\LoggerAwareTrait;
use Spider\Application\Service\JjkService;
use App\Spider\MarriottSpider;
use Sqzs\Common\Console\DaemonizableCommand;
use App\Container\HttpHelper;
use App\Container\UserAgent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use Redis;

class ScrapeMarriottHotelsCommand extends DaemonizableCommand
{
    use LoggerAwareTrait;

    public const COMMAND_NAME = 'scrape:marriott';
    public const COMMAND_DESCRIPTION = 'scrape marriott hotels';
    public const API_BASE_URL = 'https://api.jijiuka.com';

    private Redis $redis;
    private MarriottSpider $marriottSpider;
    private JjkService $service;
    private HttpHelper $http;

    public function __construct(Redis $redis, MarriottSpider $marriottSpider, JjkService $service, HttpHelper $http)
    {
        parent::__construct(static::COMMAND_NAME);
        $this->redis = $redis;
        $this->marriottSpider = $marriottSpider;
        $this->service = $service;
        $this->redis->select(9);
        $this->http = $http;
    }

    protected function configure() : void
    {
        parent::configure();
        $this->setDescription(static::COMMAND_DESCRIPTION);
    }

    protected function startLoop(InputInterface $input, OutputInterface $output) : void
    {
        if ($this->logger === null) {
            $this->logger = new ConsoleLogger($output);
        }
        $this->marriottSpider->setLogger($this->logger);
        $this->service->setLogger($this->logger);
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $this->log("Bootstraping...");

        if (empty($cityAndDate = $this->popCityAndDate())) {
            return 0;
        }

        $cst = new DateTimeZone('Asia/Shanghai');
        [$cityName, $date] = explode("_", $cityAndDate);
        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' 00:00:00', $cst);
        $url = "https://www.marriott.com.cn/search/default.mi?filter=false";
        try {

            $this->log(sprintf('Scraping city %s date %s', $cityName, $date->format('Y-m-d')));

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
            $hotels = $this->marriottSpider->reveal($url, $cityName, $options, $date);
            foreach ($hotels as $hotel) {
                $hotelCode = $hotel['hotel_code'];
                $hotelName = $hotel['name'];
                $hotelAddress = $hotel['address'];
                $this->log($hotelCode . ' ' . $hotelName . ' ' . $hotelAddress);
                $key = "marriott:hotel:set";
                if (empty($this->redis->hGet($key, $hotelCode))) {
                    $this->log("------> {$hotelCode} {$hotelName} (新酒店)，indexing!");
                    $time = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
                    $this->redis->hSet($key, $hotelCode, "{$hotelName},{$time}");
                }
            }

            if ($savedHotels = $this->saveCashAndPoints($date, $hotels)) {
                $this->log(count($savedHotels) . '家酒店需要上传');
                $this->uploadMarriottHotels($savedHotels, $date);
            } else {
                $this->log('无变化');
            }
        } catch (Throwable $exception) {
            $this->log($exception->getMessage() . ' on ' . $url);
        }

        $randomInt = random_int(10,15);
        $this->log(sprintf('taking a sleep of %ss', $randomInt));
        sleep($randomInt);
        return 0;
    }

    private function uploadMarriottHotels(array $hotels, DateTimeImmutable $startDate) : void
    {
        $citiesAlign = [
            'shanghai' => '上海',
            'beijing' => '北京',
            'chongqing' => '重庆',
            'tianjin' => '天津',
        ];
        $i=0;
        $url = static::API_BASE_URL . '/points/marriott_hotels';
        foreach ($hotels as $hotel) {
            $this->log(++$i);
            $brandId = strtolower($hotel['brand_id']);
            $city = rtrim(strtolower($hotel['city']), '市');
            $hotel['city'] = $citiesAlign[$city] ?? $city;
            $hotel['brand_logo'] = "https://static.sqzs.com/jijiuka/images/hotels/marriott_{$brandId}_logo.jpg";
            $hotel['price_tax_included'] = floor($hotel['price'] * 1.165 * 100);
            $hotel['price'] = floor($hotel['price'] * 100);
            $hotel['date'] = $startDate->format('Y-m-d');

            $hotel['english_name'] = $hotel['english_name'] ?? null;
            $retry = 0;
            do {
                try {
                    $retry++;
                    $this->log('uploading the ' . $retry . ' time');
                    $this->log(json_encode($hotel, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                    $result = $this->http->postJson($url, $hotel);
                    $this->log(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                    break;
                } catch (Throwable $exception) {
                    $this->redis->rPush("error:marriott", $hotel);
                    $this->redis->rPush("error:marriott", $exception->getMessage() . ' on hotel ' . $hotel['name']);
                    $this->log($exception->getMessage() . ' on hotel ' . $hotel['name']);
                }
            } while ($retry < 5);
        }
    }

    private function saveCashAndPoints(DateTimeImmutable $date, array $cashAndPoints = []) : array
    {
        $updated = [];
        $dateFormatted = $date->format('Y-m-d');
        foreach ($cashAndPoints as $cashAndPoint) {
            $hotelCode = $cashAndPoint['hotel_code'];
            $cashAndPoint['date'] = $dateFormatted;
            $key = "marriott:cashAndPoints:{$hotelCode}";
            $hashKey = $hotelCode . '_' . $dateFormatted;
            $encoded = md5(json_encode($cashAndPoint));
            // 如果已保存的产品库里存在此spu信息
            if ($hashValue = $this->redis->hGet($key, $hashKey)) {
                // 如果新抓取的产品库存和已保存的产品库存一致，跳过。
                if ($encoded == $hashValue) {
                    continue;
                }
            }
            // 保存新爬回来的数据。
            $this->redis->hSet($key, $hashKey, $encoded);
            $updated[$hashKey] = $cashAndPoint;
        }
        return $updated;
    }

    private function popCityAndDate()
    {
        $key = 'marriott:cityAndDate:queue';
        if (empty($cityAndDate = $this->redis->lPop($key))) {

            $this->reloadCitiesAndDates();
            if (empty($cityAndDate = $this->redis->lPop($key))) {
                $this->log("no city is popped");
                return null;
            }
        }
        return $cityAndDate;
    }

    private function getCities() : array
    {
        $url = 'https://admin-api.sqzs.com/jijiuka/marriott/cities';
        $response = $this->http->getJson($url);
        return $response ? $response['data'] : [];
    }

    private function reloadCitiesAndDates() : void
    {
        if (empty($cities = $this->redis->hGetAll('marriott:city:set'))) {
            $rawCity = $this->getCities();
            foreach ($rawCity as $city) {
                $cities[$city['name']] = $city;
            }
        }

        $key = 'marriott:cityAndDate:queue';
        $cst = new DateTimeZone('Asia/Shanghai');
        $startDate = (new DateTimeImmutable('now', $cst));
        $endDate = $startDate->modify('1 year');
        while ($startDate < $endDate) {
            foreach ($cities as $city) {
                $this->redis->rPush($key, "{$city['name']}_{$startDate->format('Y-m-d')}");
            }
            $startDate = $startDate->modify('1 day');
        }
    }
}
