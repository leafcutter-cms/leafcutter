<?php
namespace Leafcutter\tests\units;

use atoum;
use Leafcutter\URL as LeafcutterURL;

class URL extends atoum
{
    public function testNothing()
    {
        $url = new LeafcutterURL("https://www.google.com/foo/bar.html");
    }
}
