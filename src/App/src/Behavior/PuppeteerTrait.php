<?php

namespace App\Behavior;

use Nesk\Puphpeteer\Resources;
use Nesk\Rialto\Data\JsFunction;

trait PuppeteerTrait
{
    public function scrollToBottom($page) : void
    {
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
    }

    public function getPropertyValue($container, string $selector, string $property) : ?string
    {
        if (!$element = $container->querySelector($selector)) {
            return null;
        }

        return $element->getProperty($property)->jsonValue();
    }

    public function getInnerText($container, string $selector) : ?string
    {
        $jsFunction = new JsFunction(['element'], 'return element.innerText');

        return $container->querySelectorEval($selector, $jsFunction);
    }

    public function findElementByContent($container, string $selector, string $content) : ?Resources\ElementHandle
    {
        $jsFunction = new JsFunction(['elements'], 'return elements.map(element => element.innerHTML)');
        $texts = $container->querySelectorAllEval($selector, $jsFunction);

        $itemIndex = null;
        foreach ($texts as $index => $text) {
            if (strpos($text, $content) !== false) {
                $itemIndex = $index;
                break;
            }
        }

        if ($itemIndex === null) {
            return null;
        }

        return $container->querySelectorAll($selector)[$itemIndex];
    }

    public function inputValue($input, string $value) : void
    {
        $currentValue = $input->getProperty('value')->jsonValue();
        if ($currentValue === $value) {
            return;
        }

        $currentLength = strlen($currentValue);
        for ($index = 0; $index < $currentLength; $index++) {
            $input->press('Backspace');
        }

        $input->type($value);
    }
}
