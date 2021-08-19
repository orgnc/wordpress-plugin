<?php

namespace Empire;

class AmpAdsInjector extends \AMP_Base_Sanitizer {
    public function sanitize() {
        $ampConfig = $this->args['ampConfig'];
        $adsConfig = $this->args['adsConfig'];
        $targeting = $this->args['getTargeting']();

        if ($this->checkAdsBlocked($adsConfig['adRules'], $targeting)) {
            return;
        }

        foreach ($ampConfig['forPlacement'] as $key => $amp) {
            $placement = $adsConfig['forPlacement'][$key];

            [
                'selectors' => $selectors,
                'limit' => $limit,
                'relative' => $relative,
            ] = $placement;

            $adHtml = $this->applyTargeting($amp['component'], $targeting);
            $this->injectAds($adHtml, $relative, $selectors, $limit);
        }
    }

    public function applyTargeting($component, $values) {
        $targeting = [
            'site' => $values['siteDomain'],
            'article' => $values['gamPageId'],
            'targeting_article' => $values['gamExternalId'],
        ];

        $keywords = $values['keywords'];
        if (!empty($keywords)) {
            $targeting['content_keyword'] = $keywords;
            $targeting['targeting_keyword'] = $keywords;
        }

        $category = $values['category'];
        if (!is_null($category)) {
            $targeting['site_section'] = $category->slug;
            $targeting['targeting_section'] = $category->slug;
        }

        $json = json_encode(['targeting' => $targeting]);
        return str_replace('json="{}"', 'json='. $json, $component);
    }

    public function injectAds($adHtml, $relative, $selectors, $limit) {
        $count = 0;
        $transformer = \FluentDOM::getXPathTransformer();
        foreach ($selectors as $selector) {
            $path = $transformer->toXpath($selector);

            foreach ($this->dom->xpath->query($path) as $elem) {
                $ad = $this->nodeFromHtml($adHtml);
                $this->injectAd($ad, $relative, $elem);
                $count++;

                if ($count == $limit) {
                    return;
                }
            }
        }
    }

    public function injectAd($ad, $relative, $elem) {
        switch ($relative) {
            case 'inside_start':
                return $elem->insertBefore($ad, $elem->firstChild);
            case 'inside_end':
                return $elem->appendChild($ad);
            case 'after':
                return $elem->parentNode->insertBefore($ad, $elem->nextSibling);
            case 'before':
                return $elem->parentNode->insertBefore($ad, $elem);
            case 'sticky_footer':
                return $elem->appendChild($ad);
            default:
                return false;
        }
    }

    public function nodeFromHtml($html) {
        $fragment = $this->dom::fromHtmlFragment($html);
        $exportBody = $fragment->getElementsByTagName('body')->item(0);

        $importFragment = $this->dom->createDocumentFragment();
        while ($exportBody->firstChild) {
            $importNode = $exportBody->removeChild($exportBody->firstChild);
            $importNode = $this->dom->importNode($importNode, true);
            $importFragment->appendChild($importNode);
        }
        return $importFragment;
    }

    public function checkAdsBlocked($adRules, $targeting) {
        foreach ($adRules as $rule) {
            if (!$rule['enabled']) {
                continue;
            }

            $url = $targeting['url'];
            $components = [];
            switch ($rule['component']) {
                case 'PATH':
                    $components = [parse_url($url)['path'] ?? '/'];
                    break;
                case 'URL':
                    $components = [$targeting['url']];
                    break;
                case 'TAG':
                    $components = $targeting['keywords'];
                    break;
                case 'CATEGORY':
                    $category = $targeting['category'];
                    if ($category) {
                        $components = [$category->slug];
                    }
                    break;
            }

            $blocked = array_reduce(array_map(function ($component) use ( $rule ) {
                switch ($rule['comparator']) {
                    case 'CONTAINS':
                        return (strpos($component, $rule['value']) !== false);
                    case 'STARTS_WITH':
                        return (strpos($component, $rule['value']) === 0);
                    case 'EXACTLY_MATCHES':
                        return ($component === $rule['value']);
                    default:
                        return false;
                }
            }, $components), function ($accumulator, $matched) {
                return $accumulator || $matched;
            }, false);

            if ($blocked) {
                return true;
            }
        }

        return false;
    }
}

