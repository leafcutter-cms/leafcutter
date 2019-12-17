<?php
namespace Leafcutter\DOM;

use Leafcutter\Leafcutter;
use Leafcutter\Pages\PageInterface;
use Leafcutter\Assets\AssetInterface;

class DOMProvider
{
    protected $leafcutter;
    protected $systemHooks;
    protected $context = [];
    protected $cache;

    public function __construct(Leafcutter $leafcutter)
    {
        $this->leafcutter = $leafcutter;
        $this->systemHooks = new SystemHooks($this->leafcutter);
        $this->leafcutter->hooks()->addSubscriber($this->systemHooks);
        $this->cache = $this->leafcutter->cache(
            'DOMProvider',
            $this->leafcutter->config('cache.ttl.dom_provider')
        );
    }

    public function obfuscate(string $html) : string
    {
        $html = \base64_encode($html);
        return '<script>document.write(atob("'.$html.'"));</script><noscript><em>[javascript required]</em></noscript>';
    }

    public function html(string $html) : string
    {
        return $this->cache->get(
            'html.'.hash('crc32', $html),
            function () use ($html) {
                $html = $this->leafcutter->hooks()->dispatchAll('onDOMProcess', $html);

                // set up DOMDocument
                $dom = new \DOMDocument();
                if (!@$dom->loadHTML($html, \LIBXML_NOERROR & \LIBXML_NOWARNING & \LIBXML_NOBLANKS)) {
                    $this->leafcutter->logger()->error('Error loading HTML into DOMDocument');
                    return $html;
                }
                // dispatch events
                $this->dispatchEvents($dom);

                //normalize and output to HTML
                $dom->normalizeDocument();
                $html = trim(preg_replace(
                    '/^<\?.+\?>/',
                    '',
                    $dom->saveHTML()
                ));
                //fix self-closing tags that aren't actually allowed to self-close in HTML
                $html = preg_replace('@(<(a|script|noscript|table|iframe|noframes|canvas|style)[^>]*)/>@ims', '$1></$2>', $html);

                // return after passing through another hook
                return $this->leafcutter->hooks()->dispatchAll('onDOMReady', $html);
            }
        );
    }

    protected function dispatchEvents(\DOMNode $node)
    {
        $context = null;
        //pick event name if applicable
        if ($node instanceof \DOMElement) {
            //skip events on elements with data-leafcutter-dom-events="off"
            if ($node->getAttribute('data-leafcutter-dom-events') == 'off') {
                return;
            }
            //onDOMElement_{tagname} event name
            $eventName = 'onDOMElement_'.$node->tagName;
            //set context from HTML if necessary
            if ($context = $node->getAttribute('data-context-path')) {
                $node->removeAttribute('data-context-path');
                $this->startContext($context);
            }
        } elseif ($node instanceof \DOMComment) {
            //onDOMComment event name
            $eventName = 'onDOMComment';
        } elseif ($node instanceof \DOMText) {
            $eventName = 'onDOMText';
        } else {
            $eventName = null;
        }
        //dispatch event if necessary
        if ($eventName) {
            $event = $this->leafcutter->hooks()->dispatchEvent($eventName, new DomEvent($node));
            //do deletion if event calls for it
            if ($event->getDelete()) {
                $node->parentNode->removeChild($node);
            }
            //else do replacement if event calls for it
            elseif ($html = $event->getReplacement()) {
                $newNode = $node->ownerDocument->createDocumentFragment();
                @$newNode->appendXML($html);
                $node->parentNode->replaceChild($newNode, $node);
                $node = $newNode;
                $this->dispatchEvents($newNode);
            }
        }
        //recurse into children if found
        if ($node && $node->hasChildNodes()) {
            //build an array of children, disconnected from childNodes object
            //we need to do this so we can replace them without breaking the
            //order and total coverage of looping through them
            $children = [];
            foreach ($node->childNodes as $child) {
                $children[] = $child;
            }
            //loop through new array of child nodes
            foreach ($children as $child) {
                $this->dispatchEvents($child);
            }
        }
        //end context
        if ($context) {
            $this->endContext();
        }
    }

    protected function startContext(string $path)
    {
        $this->context[] = $path;
    }

    protected function endContext()
    {
        array_pop($this->context);
    }

    public function getContext()
    {
        return @end($this->context) ?? "/";
    }
}
