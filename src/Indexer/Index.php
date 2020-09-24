<?php
namespace Leafcutter\Indexer;

use Leafcutter\Leafcutter;
use Leafcutter\URL;
use PDO;

class Index
{
    protected $name;
    protected $pdo;
    protected $leafcutter;

    public function __construct(string $name, PDO $pdo, Leafcutter $leafcutter)
    {
        $this->name = $name;
        $this->pdo = $pdo;
        $this->leafcutter = $leafcutter;
    }

    public function getByURL($url): array
    {
        if ($url instanceof URL) {
            $url = $url->siteFullPath() . $url->queryString();
        }
        return $this->query(
            'SELECT * FROM "' . $this->name . '" WHERE url = :url;',
            [':url' => $url]
        );
    }

    public function getByValue(string $value): array
    {
        return $this->query(
            'SELECT * FROM "' . $this->name . '" WHERE value = :value;',
            [':value' => $value]
        );
    }

    public function save($url, string $value, array $data = []): IndexItem
    {
        $item = $this->item($url, $value, $data);
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
                'INSERT INTO "' . $this->name . '" (url,value,data) VALUES (:url,:value,:data);'
            );
        }
        return !!$s->execute([
            ':url' => $item->urlString(),
            ':value' => $item->value(),
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

    public function item($url, string $value, array $data = []): IndexItem
    {
        if ($url instanceof URL) {
            $url = $url->siteFullPath() . $url->queryString();
        }
        return new IndexItem($url, $value, $data, $this);
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
    }

    protected function query($sql, $params): array
    {
        $s = $this->pdo->prepare($sql);
        if ($s && $s->execute($params)) {
            return array_map(
                function ($e) {
                    return $this->item($e['url'], $e['value'], json_decode($e['data'], true));
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
	"data"	TEXT NOT NULL,
	PRIMARY KEY("url","value")
);
EOD;
    }
}
