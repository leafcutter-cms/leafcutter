<?php
namespace Leafcutter\DOM;

class DOMEvent
{
    protected $node;
    protected $request;
    protected $response;
    protected $delete = false;
    protected $replacement;
    protected $source;

    public function __construct(\DOMNode $node)
    {
        $this->node = $node;
    }

    public function setReplacement(string $html = null)
    {
        $this->replacement = $html;
    }

    public function setSource($source = null)
    {
        $this->source = $source;
    }

    public function setDelete(bool $set)
    {
        $this->delete = $set;
    }

    public function getSource()
    {
        return $this->source;
    }

    public function getNode(): \DOMNode
    {
        return $this->node;
    }

    public function getReplacement(): ?string
    {
        return $this->replacement;
    }

    public function getDelete(): bool
    {
        return $this->delete;
    }
}
