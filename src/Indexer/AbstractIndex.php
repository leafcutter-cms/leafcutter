<?php
namespace Leafcutter\Indexer;

use Leafcutter\Leafcutter;
use Leafcutter\Pages\Page;
use Leafcutter\URL;
use PDO;

abstract class AbstractIndex
{
    protected $name;
    protected $pdo;
    protected $leafcutter;

    abstract public function indexPage(Page $page);

    public function __construct(string $name, PDO $pdo, Leafcutter $leafcutter)
    {
        $this->name = $name;
        $this->pdo = $pdo;
        $this->leafcutter = $leafcutter;
        $this->leafcutter->events()->addSubscriber($this);
    }

    public function onErrorPage(Page $page)
    {
        $this->removePage($page);
    }

    public function removePage(Page $page)
    {
        $this->deleteByURL($page->url());
    }

    public function onPageContentGenerated(Page $page)
    {
        if ($page->status() == 200) {
            $this->indexPage($page);
        }
    }

    public static function normalizeURL($url): string
    {
        if ($url instanceof URL) {
            return $url->siteFullPath() . $url->queryString();
        } elseif (is_string($url)) {
            return $url;
        } else {
            throw new \Exception("Malformed URL passed to Index", 1);
        }
    }

    public function getByURL($url): array
    {
        $url = static::normalizeURL($url);
        return $this->query(
            'SELECT * FROM "' . $this->name . '" WHERE url = :url ORDER BY ' . $this->order() . ';',
            [':url' => $url]
        );
    }

    public function getByValue(string $value): array
    {
        return $this->query(
            'SELECT * FROM "' . $this->name . '" WHERE value = :value ORDER BY ' . $this->order() . ';',
            [':value' => $value]
        );
    }

    public function listValues(): array
    {
        $result = $this->pdo->query('SELECT value, count(*) as `count` FROM "' . $this->name . '" GROUP BY value ORDER BY ' . $this->listOrder() . ';');
        $result = array_map(
            function ($e) {
                return new IndexValue($e['value'], $e['count'], $this);
            },
            $result->fetchAll(PDO::FETCH_ASSOC)
        );
        return $result;
    }

    public function listURLs(): array
    {
        $result = $this->pdo->query('SELECT url, count(*) as `count` FROM "' . $this->name . '" GROUP BY url ORDER BY ' . $this->listOrder() . ';');
        $result = array_map(
            function ($e) {
                return new IndexURL($e['url'], $e['count'], $this);
            },
            $result->fetchAll(PDO::FETCH_ASSOC)
        );
        return $result;
    }

    protected function order()
    {
        return '`sort` ASC';
    }

    protected function listOrder()
    {
        return '`count` DESC, `sort` ASC';
    }

    public function deleteByURL($url): array
    {
        $url = static::normalizeURL($url);
        return $this->query(
            'DELETE FROM "' . $this->name . '" WHERE url = :url;',
            [':url' => $url]
        );
    }

    public function deleteByValue(string $value): array
    {
        return $this->query(
            'DELETE FROM "' . $this->name . '" WHERE value = :value;',
            [':value' => $value]
        );
    }

    public function save($url, string $value, string $sort = '', array $data = []): IndexItem
    {
        $item = $this->item($url, $value, $sort, $data);
        $item->save();
        return $item;
    }

    public function item_delete(IndexItem $item)
    {
        $s = $this->pdo->prepare(
            'DELETE FROM "' . $this->name . '" WHERE url = :url AND value = :value;'
        );
        return !!$s->execute([
            ':url' => $item->urlString(),
            ':value' => $item->value(),
        ]);
    }

    public function item_save(IndexItem $item): bool
    {
        if (!$item->url()->inSite()) {
            return false;
        }
        if ($this->item_exists($item)) {
            $s = $this->pdo->prepare(
                'UPDATE "' . $this->name . '" SET data = :data WHERE url = :url AND value = :value;'
            );
        } else {
            $s = $this->pdo->prepare(
                'INSERT INTO "' . $this->name . '" (url,value,sort,data) VALUES (:url,:value,:sort,:data);'
            );
        }
        return !!$s->execute([
            ':url' => $item->urlString(),
            ':value' => $item->value(),
            ':sort' => $item->sort(),
            ':data' => json_encode($item->data()),
        ]);
    }

    public function item_exists(IndexItem $item): bool
    {
        if (!$item->url()->inSite()) {
            return false;
        }
        $s = $this->pdo->prepare(
            'SELECT * FROM "' . $this->name . '" WHERE url = :url AND value = :value;'
        );
        if ($s && $s->execute([
            ':url' => $item->urlString(),
            ':value' => $item->value(),
        ])) {
            return !!$s->fetch();
        } else {
            return false;
        }
    }

    public function item($url, string $value, string $sort = "", array $data = []): IndexItem
    {
        $url = static::normalizeURL($url);
        return new IndexItem($url, $value, $sort, $data, $this);
    }

    public function create()
    {
        //create table
        $this->pdo->exec(
            $this->createTableSQL($this->name)
        );
        //create indexes
        $this->pdo->exec('CREATE INDEX "url_idx" ON "' . $this->name . '" ("url");');
        $this->pdo->exec('CREATE INDEX "value_idx" ON "' . $this->name . '" ("value");');
        $this->pdo->exec('CREATE INDEX "sort_idx" ON "' . $this->name . '" ("sort");');
    }

    protected function query($sql, $params): array
    {
        $s = $this->pdo->prepare($sql);
        if ($s && $s->execute($params)) {
            return array_map(
                function ($e) {
                    return $this->item($e['url'], $e['value'], $e['sort'], json_decode($e['data'], true));
                },
                $s->fetchAll(PDO::FETCH_ASSOC)
            );
        } else {
            return [];
        }
    }

    protected function createTableSQL(string $name): string
    {
        return <<<EOD
CREATE TABLE "$name" (
	"url"	TEXT NOT NULL,
	"value"	TEXT NOT NULL,
	"sort"	TEXT NOT NULL,
	"data"	TEXT NOT NULL,
	PRIMARY KEY("url","value")
);
EOD;
    }
}
