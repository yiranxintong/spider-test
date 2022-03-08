<?php

namespace App\Container;

final class UserAgent
{
    public const OSX_DESKTOP = self::OSX_DESKTOP_CHROME;
    public const OSX_DESKTOP_CHROME = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.90 Safari/537.36';
    public const OSX_DESKTOP_SAFARI = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.5 Safari/605.1.15';
    public const IOS_WECHAT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/16D57 MicroMessenger/7.0.3(0x17000321) NetType/WIFI Language/zh_CN';
    public const IOS_WECHAT_MINI_PROGRAM = 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/16D57 MicroMessenger/7.0.3(0x17000321) NetType/WIFI Language/zh_CN MiniProgram';

    private const ANDROID_VERSIONS = ['Android 6.0', 'Android 7.0', 'Android 8.0', 'Android 9.0', 'Android 10.0'];
    private const MOBILE_DEVICES = [
        'Nexus 5', 'Nexus 6', 'Nexus 6p', 'Nexus 7', 'Nexus 10', 'Xiaomi', 'HUAWEI', 'HTC 802t', 'HTC M8St', 'vivo X7',
        'vivo X9', 'vivo X9i', 'vivo X9L', 'OPPO A57', 'vivo Y66', 'Galaxy A3',
    ];
    private const BASE_VERSIONS = ['MRA58N', 'M8974A'];

    public static function randomAndroidDevice() : string
    {
        $androidVersion = static::ANDROID_VERSIONS[array_rand(static::ANDROID_VERSIONS, 1)];
        $mobileDevice = static::MOBILE_DEVICES[array_rand(static::MOBILE_DEVICES, 1)];
        $baseVersion = static::BASE_VERSIONS[array_rand(static::BASE_VERSIONS, 1)];

        $format = 'Mozilla/5.0 (Linux; %s; %s Build/%s) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Mobile Safari/537.36';
        return sprintf($format, $androidVersion, $mobileDevice, $baseVersion);
    }
}
