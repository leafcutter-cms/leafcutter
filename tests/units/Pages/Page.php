<?php
namespace Leafcutter\tests\units\Pages;

use atoum;
use Leafcutter\URL;

class Page extends atoum\test
{
    public function testConstruct()
    {
        $this->newTestedInstance(new URL('https://www.google.com/foo/bar.html'));
    }
}