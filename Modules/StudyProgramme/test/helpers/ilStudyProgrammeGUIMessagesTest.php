<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ilStudyProgrammeGUIMessagesTest extends TestCase
{
    protected ilPRGMessagePrinter $messages;
    protected string $topic;

    public function setUp() : void
    {
        $collection = new ilPRGMessageCollection();
        $lng = $this->createMock(ilLanguage::class);
        $tpl = $this->createMock(ilGlobalTemplateInterface::class);
        $this->messages = new ilPRGMessagePrinter($collection, $lng, $tpl);
        $this->topic = 'a test topic';

        $this->collection = $this->messages->getMessageCollection($this->topic);
        $this->collection2 = $this->messages->getMessageCollection($this->topic);
    }

    public function testMessageFactory() : void
    {
        $this->assertInstanceOf(ilPRGMessageCollection::class, $this->collection);
        $this->assertEquals($this->collection, $this->collection2);
        $this->assertNotSame($this->collection, $this->collection2);
    }
    
    public function testCollectionDefaults() : void
    {
        $this->assertEquals($this->topic, $this->collection->getDescription());

        $this->assertFalse($this->collection->hasErrors());
        $this->assertEquals([], $this->collection->getErrors());

        $this->assertFalse($this->collection->hasSuccess());
        $this->assertEquals([], $this->collection->getSuccess());
        
        $this->assertFalse($this->collection->hasAnyMessages());
    }

    public function testAddMessages() : void
    {
        $ok_message = 'looks good';
        $ok_id = 'some good record';
        $this->collection->add(true, $ok_message, $ok_id);

        $this->assertTrue($this->collection->hasAnyMessages());
        
        $this->assertFalse($this->collection->hasErrors());
        $this->assertEquals([], $this->collection->getErrors());

        $this->assertTrue($this->collection->hasSuccess());
        $this->assertEquals(
            [[$ok_message, $ok_id]],
            $this->collection->getSuccess()
        );
    }

    public function testAddErrorMessages() : void
    {
        $message = 'looks bad';
        $id = 'some record';

        $this->assertFalse($this->collection->hasAnyMessages());
        $collection = $this->collection->withNewTopic($this->topic);
        $this->assertFalse($collection->hasAnyMessages());

        $collection->add(false, $message, $id);
        $collection->add(false, $message, $id);
        
        $this->assertTrue($collection->hasAnyMessages());

        $this->assertTrue($collection->hasErrors());
        $this->assertEquals(
            [[$message, $id],[$message, $id]],
            $collection->getErrors()
        );
        
        $this->assertEquals([], $collection->getSuccess());
        $this->assertFalse($collection->hasSuccess());
    }
}
