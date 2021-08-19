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
            $component = $this->applyTargeting($amp['component'], $targeting);
            $this->injectAds($component, $placement['selectors'], $placement['limit']);
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

    public function injectAds($ad, $selectors, $limit) {
        $count = 0;
        $transformer = \FluentDOM::getXPathTransformer();
        foreach ($selectors as $selector) {
            $path = $transformer->toXpath($selector);

            foreach ($this->dom->xpath->query($path) as $elem) {
                $component = $this->nodeFromHtml($ad);
                # TODO: respect relative option
                $elem->insertBefore($component);
                $count++;

                if ($count == $limit) {
                    return;
                }
            }
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

