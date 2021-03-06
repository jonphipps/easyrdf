<?php

/**
 * EasyRdf
 *
 * LICENSE
 *
 * Copyright (c) 2009-2012 Nicholas J Humfrey.  All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 * 3. The name of the author 'Nicholas J Humfrey" may be used to endorse or
 *    promote products derived from this software without specific prior
 *    written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package    EasyRdf
 * @copyright  Copyright (c) 2009-2012 Nicholas J Humfrey
 * @license    http://www.opensource.org/licenses/bsd-license.php
 * @version    $Id$
 */

require_once dirname(dirname(dirname(__FILE__))).
             DIRECTORY_SEPARATOR.'TestHelper.php';

class EasyRdf_Parser_NtriplesTest extends EasyRdf_TestCase
{
    protected $_parser = null;
    protected $_graph = null;
    protected $_data = null;

    public function setUp()
    {
        $this->_graph = new EasyRdf_Graph();
        $this->_parser = new EasyRdf_Parser_Ntriples();
        $this->_data = readFixture('foaf.nt');
    }

    public function testParse()
    {
        $count = $this->_parser->parse($this->_graph, $this->_data, 'ntriples', null);
        $this->assertSame(14, $count);

        $joe = $this->_graph->resource('http://www.example.com/joe#me');
        $this->assertNotNull($joe);
        $this->assertClass('EasyRdf_Resource', $joe);
        $this->assertSame('http://www.example.com/joe#me', $joe->getUri());

        $name = $joe->get('foaf:name');
        $this->assertNotNull($name);
        $this->assertClass('EasyRdf_Literal', $name);
        $this->assertSame('Joe Bloggs', $name->getValue());
        $this->assertSame('en', $name->getLang());
        $this->assertSame(NULL, $name->getDatatype());
    }

    public function testParseBnode()
    {
        $count = $this->_parser->parse(
            $this->_graph,
            "_:a <http://example.com/b> \"c\" . \n".
            "_:d <http://example.com/e> _:a . \n",
            'ntriples', null
        );
        $this->assertSame(2, $count);

        $bnode1 = $this->_graph->resource('_:genid1');
        $this->assertNotNull($bnode1);
        $this->assertSame(true, $bnode1->isBnode());
        $this->assertStringEquals('c', $bnode1->get('<http://example.com/b>'));

        $bnode2 = $this->_graph->resource('_:genid2');
        $this->assertNotNull($bnode2);
        $this->assertSame(true, $bnode2->isBnode());
        $this->assertSame($bnode1, $bnode2->get('<http://example.com/e>'));
    }

    public function testParseAnonymousBnode()
    {
        $count = $this->_parser->parse(
            $this->_graph,
            "_: <http://example.com/a> _: . \n".
            "_: <http://example.com/b> _: . \n",
            'ntriples', null
        );
        $this->assertSame(2, $count);

        $bnode1 = $this->_graph->resource('_:genid1');
        $this->assertSame(true, $bnode1->isBnode());
        $this->assertStringEquals('_:genid2', $bnode1->get('<http://example.com/a>'));

        $bnode2 = $this->_graph->resource('_:genid3');
        $this->assertSame(true, $bnode2->isBnode());
        $this->assertStringEquals('_:genid4', $bnode2->get('<http://example.com/b>'));
    }

    public function testParseLang()
    {
        $count = $this->_parser->parse(
            $this->_graph,
            '<http://example.com/a> <http://example.com/b> "English"@en-gb .',
            'ntriples', null
        );
        $this->assertSame(1, $count);

        $int = $this->_graph->get('http://example.com/a', '<http://example.com/b>');
        $this->assertNotNull($int);
        $this->assertSame('English', $int->getValue());
        $this->assertSame('en-gb', $int->getLang());
        $this->assertSame(NULL, $int->getDatatype());
    }

    public function testParseDatatype()
    {
        $count = $this->_parser->parse(
            $this->_graph,
            '<http://example.com/a> <http://example.com/b> "1"^^<http://www.w3.org/2001/XMLSchema#integer> .',
            'ntriples', null
        );
        $this->assertSame(1, $count);

        $int = $this->_graph->get('http://example.com/a', '<http://example.com/b>');
        $this->assertNotNull($int);
        $this->assertSame(1, $int->getValue());
        $this->assertSame(NULL, $int->getLang());
        $this->assertSame('xsd:integer', $int->getDatatype());
    }

    public function testParseEscaped()
    {
        $count = $this->_parser->parse(
            $this->_graph,
            '<http://example.com/a> <http://example.com/b> "\t" .',
            'ntriples', null
        );
        $this->assertSame(1, $count);

        $a = $this->_graph->resource('http://example.com/a');
        $this->assertNotNull($a);

        $b = $a->get('<http://example.com/b>');
        $this->assertNotNull($b);
        $this->assertSame("\t", $b->getValue());
    }

    public function testParseComment()
    {
        $count = $this->_parser->parse(
            $this->_graph,
            "<http://example.com/a> <http://example.com/a> \"Test 1\" .\n".
            "# <http://example.com/b> <http://example.com/b> \"a comment\" .\n".
            "  # another comment .\n".
            "<http://example.com/c> <http://example.com/c> \"Test 2\" .\n",
            'ntriples', null
        );
        $this->assertSame(2, $count);
        $this->assertCount(2, $this->_graph->resources());
    }

    public function testParseEmpty()
    {
        $count = $this->_parser->parse(
            $this->_graph, '', 'ntriples', null
        );
        $this->assertSame(0, $count);
    }

    public function testParseInvalidSubject()
    {
        $this->setExpectedException(
            'EasyRdf_Exception',
            'Failed to parse subject: foobar'
        );
        $this->_parser->parse(
            $this->_graph,
            "foobar <http://example.com/a> \"Test 1\" .\n",
            'ntriples', null
        );
    }

    public function testParseInvalidObject()
    {
        $this->setExpectedException(
            'EasyRdf_Exception',
            'Failed to parse object: foobar'
        );
        $this->_parser->parse(
            $this->_graph,
            "<http://example.com/b> <http://example.com/a> foobar .\n",
            'ntriples', null
        );
    }

    function testParseUnsupportedFormat()
    {
        $this->setExpectedException(
            'EasyRdf_Exception',
            'EasyRdf_Parser_Ntriples does not support: unsupportedformat'
        );
        $rdf = $this->_parser->parse(
            $this->_graph, $this->_data, 'unsupportedformat', null
        );
    }
}
