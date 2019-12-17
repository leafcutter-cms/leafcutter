<?php
namespace Leafcutter\DOM;

use Leafcutter\Leafcutter;
use Leafcutter\Content\Pages\PageInterface;
use Leafcutter\Content\Assets\AssetInterface;

class SystemHooks
{
    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
    }

    public function onDOMElement_code(DomEvent $event)
    {
        $node = $event->getNode();
        $text = $node->textContent;
        $hl = new \Highlight\Highlighter();

        //see if a language-[lang] class is specified in CSS
        $language = null;
        $classes = explode(' ', $node->getAttribute('class')??'');
        foreach ($classes as $class) {
            if (preg_match('/^language-(.+)$/', $class)) {
                $language = substr($class, 9);
                break;
            }
        }
        $classes[] = 'hljs';

        //language is specified
        if ($language) {
            try {
                $highlighted = $hl->highlight($language, $text);
            } catch (\Throwable $th) {
                //an exception means language didn't exist
                //clear language and try to autodetect
                $langauge = null;
            }
        }
        //autodetect language
        if (!$language) {
            $highlighted = $hl->highlightAuto($text);
            $language = $highlighted->language;
            $classes[] = 'language-'.$language;
        }
        //set classes back in
        $classes = array_unique(array_filter($classes));
        $node->setAttribute('class', implode(' ', $classes));
        //replace output HTML
        $attributes = '';
        foreach ($node->attributes as $attr) {
            $attributes .= ' '.$attr->name.'="'.$attr->value.'"';
        }
        $event->setReplacement("<code$attributes>".$highlighted->value."</code>");
    }

    public function onDOMElement_link(DomEvent $event)
    {
        $link = $event->getNode();
        $url = $link->getAttribute('href');
        if (!$url) {
            return;
        }
        $asset = $this->leafcutter
            ->assets()->get($url, $this->leafcutter->dom()->getContext());
        if (!$asset) {
            return;
        }
        // if asset is empty, omit this tag, but place a comment to explain what happened
        if ($asset->isEmpty()) {
            $event->setReplacement('<!-- omitted empty asset: link href="'.$url.'" -->');
            return;
        }
        // we have a link tag that has an href attribute resolved to an asset
        $link->setAttribute('href', $asset->getOutputUrl());
        $link->setAttribute('type', $asset->getMime());
    }

    public function onDOMElement_img(DomEvent $event)
    {
        $img = $event->getNode();
        $url = $img->getAttribute('src');
        $asset = $this->leafcutter
            ->assets()->get($url, $this->leafcutter->dom()->getContext());
        if (!$asset) {
            return;
        }
        // we have an img tag that has a src attribute resolved to an asset
        $img->setAttribute('src', $asset->getOutputUrl());
    }

    /**
     * Attempts to create good/proper links to existing pages/assets
     *
     * @param DOMEvent $event
     * @return void
     */
    public function onDOMElement_a(DOMEvent $event)
    {
        //verify that anchor has an href
        $a = $event->getNode();
        $url = $a->getAttribute('href');
        if (!$url) {
            return;
        }
        //if this is a link to an email address, obfuscate it
        if (substr($url, 0, 7) == 'mailto:') {
            $event->setReplacement($this->leafcutter->dom()->obfuscate($a->ownerDocument->saveHTML($a)));
            return;
        }
        //try to resolve link target within Leafcutter
        $target = $this->leafcutter
            ->get($url, $this->leafcutter->dom()->getContext());
        if (!$target) {
            if (preg_match('@^(https?):?//@', $url)) {
                $a->setAttribute('class', trim($a->getAttribute('class').' externalLink'));
                $a->setAttribute('rel', trim($a->getAttribute('rel').' external noopener noreferrer'));
            } else {
                $a->setAttribute('class', trim($a->getAttribute('class').' potentiallyInvalidLink'));
            }
            return;
        }
        //add class indicating this is a valid Leafcutter link to **something**
        $a->setAttribute('class', trim($a->getAttribute('class').' leafcutterLink'));
        //try to embed if class includes "embed"
        if (in_array('embed', explode(' ', $a->getAttribute('class')))) {
            if (method_exists($target, 'getEmbedHTML')) {
                $classes = array_filter(explode(' ', $a->getAttribute('class')));
                $classes[] = 'leafcutterEmbed';
                if ($embed = $target->getEmbedHTML($classes, $this->leafcutter)) {
                    $event->setReplacement($embed);
                    return;
                }
            }
        }
        //process target if it's a Page
        if ($target instanceof PageInterface) {
            $a->setAttribute('class', trim($a->getAttribute('class').' leafcutterPage'));
            return $this->onDOMElement_a_Page($event, $target);
        }
        //process target if it's an Asset
        if ($target instanceof AssetInterface) {
            $a->setAttribute('class', trim($a->getAttribute('class').' leafcutterAsset'));
            return $this->onDOMElement_a_Asset($event, $target);
        }
    }

    protected function onDOMElement_a_Page(DomEvent $event, PageInterface $page)
    {
        $a = $event->getNode();
        // update href with full URL and page title
        $a->setAttribute('href', $page->getUrl());
        if (!$a->getAttribute('title')) {
            $a->setAttribute('title', $page->getName());
        }
    }

    protected function onDOMElement_a_Asset(DomEvent $event, AssetInterface $asset)
    {
        $a = $event->getNode();
        $a->setAttribute('type', $asset->getMime());
        $a->setAttribute('href', $asset->getOutputUrl());
        if (!$a->getAttribute('title')) {
            $a->setAttribute('title', $asset->getFilename());
        }
        // $a->setAttribute('data-filesize', $asset->getFilesize());
        $a->setAttribute('rel', trim($a->getAttribute('rel').' noopener noreferrer'));
    }
}
