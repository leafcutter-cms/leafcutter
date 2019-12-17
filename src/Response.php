<?php
namespace Leafcutter;

class Response extends Common\Url
{
    protected $status = 200;
    protected $content = 'No content';

    public function setStatus(int $status)
    {
        $this->status = $status;
    }

    public function getStatus() : int
    {
        return $this->status;
    }

    public function setContent(string $content)
    {
        $this->content = $content;
    }

    public function getContent() : string
    {
        return $this->content;
    }
}
