<?php
namespace Leafcutter\DOM;

use Leafcutter\Request;
use Leafcutter\Response;

class DOMEvent
{
    protected $node;
    protected $request;
    protected $response;
    protected $delete = false;
    protected $replacement;

    public function __construct(\DOMNode $node)
    {
        $this->node = $node;
    }

    public function setReplacement(string $html=null)
    {
        $this->replacement = $html;
    }

    public function setDelete(bool $set)
    {
        $this->delete = $set;
    }

    public function getNode() : \DOMNode
    {
        return $this->node;
    }

    public function getReplacement() : ?string
    {
        return $this->replacement;
    }

    public function getDelete() : bool
    {
        return $this->delete;
    }
}
