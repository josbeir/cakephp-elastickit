<?php
declare(strict_types=1);

namespace ElasticKit\Test;

use Cake\TestSuite\TestCase;
use ElasticKit\Document;

class DocumentTest extends TestCase
{
    public function testCreate()
    {
        $params = [
            'name' => 'Test Document',
            'description' => 'This is a test document.',
        ];

        $document = new Document($params);

        $this->assertInstanceOf(Document::class, $document);
        $this->assertEquals('Test Document', $document->get('name'));
        $this->assertEquals('This is a test document.', $document->get('description'));

        $document->name = 'im dirty';
        $this->assertTrue($document->isDirty('name'));

        $document->clean();
        $this->assertFalse($document->isDirty('name'));
    }
}
