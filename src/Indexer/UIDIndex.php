<?php
namespace Leafcutter\Indexer;

use Leafcutter\Common\Collection;
use Leafcutter\Pages\Page;
use Leafcutter\URL;

class UIDIndex extends AbstractIndex
{
    public function indexPage(Page $page)
    {
        if ($uid = $page->meta('uid')) {
            $this->save($page->url(), $uid);
        }
    }

    public function onPageGet_namespace_uid(URL $url): ?Page
    {
        $url->fixSlashes();
        $uid = trim($url->sitePath(), '/');
        $results = $this->getByValue($uid);
        $results = array_map(
            function ($i) {
                return $this->leafcutter->pages()->get($i->url());
            },
            $results
        );
        $results = array_filter($results);
        if ($results) {
            if (count($results) == 1) {
                return $this->leafcutter->pages()->get($results[0]->url());
            } else {
                $page = $this->leafcutter->pages()->error($url, 300);
                $page->meta('pages.related', new Collection($results));
                $page->setUrl($url);
                return $page;
            }
        }
        return null;
    }
}
