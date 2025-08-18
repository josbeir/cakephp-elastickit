<?php
declare(strict_types=1);

namespace ElasticKit\Test;

use Cake\Core\Exception\CakeException;
use Cake\TestSuite\TestCase;
use ElasticKit\Exception\MissingIndexClassException;
use ElasticKit\Locator\IndexLocator;
use ElasticKit\Locator\IndexLocatorAwareTrait;
use ElasticKit\Test\Trait\ElasticClientTrait;
use TestApp\Model\Index\TestItemsIndex;

class IndexLocatorTest extends TestCase
{
    use ElasticClientTrait;
    use IndexLocatorAwareTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initializeElasticClient();
    }

    protected function tearDown(): void
    {
        $this->getIndexLocator()->clear();
        parent::tearDown();
    }

    public function testCreateInstance(): void
    {
        $locator = new IndexLocator();
        $instance = $locator->get(TestItemsIndex::class);
        $this->assertInstanceOf(TestItemsIndex::class, $instance);
    }

    public function testMissingIndexClassException(): void
    {
        $this->expectException(MissingIndexClassException::class);
        $locator = new IndexLocator();
        $locator->get('NonExistentIndex');
    }

    public function testReconfigureException(): void
    {
        $this->expectException(CakeException::class);
        $locator = new IndexLocator();
        $locator->get(TestItemsIndex::class);
        $locator->get(TestItemsIndex::class, ['other_key' => 'other_value']);
    }

    public function testSetIndex(): void
    {
        $locator = new IndexLocator();
        $instance = new TestItemsIndex();
        $locator->set('MyCustomName', $instance);
        $this->assertSame($instance, $locator->get('MyCustomName'));
    }

    public function testClear(): void
    {
        $locator = new IndexLocator();
        $instance = new TestItemsIndex();
        $locator->set('TestIndex', $instance);
        $this->assertSame($instance, $locator->get('TestIndex'));

        $locator->clear();
        $this->expectException(MissingIndexClassException::class);
        $locator->get('TestIndex');
    }

    public function testExists(): void
    {
        $locator = new IndexLocator();
        $instance = new TestItemsIndex();
        $locator->set('TestIndex', $instance);
        $this->assertTrue($locator->exists('TestIndex'));
        $this->assertFalse($locator->exists('NonExistentIndex'));
    }

    public function testRemove(): void
    {
        $locator = new IndexLocator();
        $instance = new TestItemsIndex();
        $locator->set('TestIndex', $instance);
        $this->assertSame($instance, $locator->get('TestIndex'));

        $locator->remove('TestIndex');
        $this->expectException(MissingIndexClassException::class);
        $locator->get('TestIndex');
    }

    public function testLocatorAwareTrait(): void
    {
        $instance = $this->fetchIndex(TestItemsIndex::class);
        $this->assertInstanceOf(TestItemsIndex::class, $instance);

        $locator = $this->getIndexLocator();
        $this->assertInstanceOf(IndexLocator::class, $locator);
    }
}
