<?php
declare(strict_types=1);

namespace ElasticKit\Test;

use Cake\TestSuite\TestCase;
use ElasticKit\Document;

class DocumentTest extends TestCase
{
    public function testCreate(): void
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

    public function testDebuginfo()
    {
        $params = [
            'name' => 'Test Document',
            'description' => 'This is a test document.',
        ];

        $document = new Document($params);
        $this->assertArrayHasKey('[reserved]', $document->__debugInfo());
    }

    public function testReservedGettersAndSetters(): void
    {
        $params = [
            'name' => 'Test Document',
            'description' => 'This is a test document.',
        ];

        $document = new Document($params);

        $document->setDocumentId(1);
        $this->assertEquals(1, $document->getDocumentId());

        $document->setScore(1.1);
        $this->assertEquals(1.1, $document->getScore());
    }
}
