<?php

use Ekok\Container\Di;
use Ekok\EventDispatcher\Dispatcher;
use Ekok\EventDispatcher\Event;

class DispatcherTest extends \Codeception\Test\Unit
{
    /** @var Dispatcher */
    private $dispatcher;

    /** @var Di */
    private $di;

    public function _before()
    {
        $this->di = new Di();
        $this->dispatcher = new Dispatcher($this->di);
    }

    public function testDispatcher()
    {
        $this->dispatcher->on('foo', static function (Event $event) {
            $event->data[] = 1;
        });
        $this->dispatcher->one('foo', static function (Event $event) {
            $event->data[] = 2;
        });
        $this->dispatcher->on('foo', static function (Event $event) {
            $event->data[] = 3;
            $event->stopPropagation();
        });
        $this->dispatcher->on('foo', static function (Event $event) {
            $event->data[] = 4;
        });
        $this->dispatcher->on('foo', static function (Event $event) {
            $event->data[] = 5;
        }, 99);

        // first call
        $this->dispatcher->dispatch($event = new Event('foo'));
        $this->assertSame(array(5, 1, 2, 3), $event->data);

        // second call
        $this->dispatcher->dispatch($event = new Event(), 'foo', true);
        $this->assertSame(array(5, 1, 3), $event->data);

        // third call
        $this->dispatcher->dispatch($event = new Event('foo'));
        $this->assertObjectNotHasAttribute('data', $event);
    }

    public function testSubscriber()
    {
        $this->dispatcher->addSubscriber(FooSubscriber::class);

        // first call
        $this->dispatcher->dispatch($event = new Event('event_foo'));
        $this->assertSame(array('second', 'first', 'third'), $event->data);

        // second call
        $this->dispatcher->dispatch($event = new Event('event_foo'));
        $this->assertSame(array('second', 'first'), $event->data);

        // eventSameAsMethod
        $this->dispatcher->dispatch($event = new Event('eventSameAsMethod'));
        $this->assertSame(array('eventSameAsMethod'), $event->data);

        // event_bar
        $this->dispatcher->dispatch($event = new Event('event_bar'));
        $this->assertSame(array('method_bar'), $event->data);

        // eventSameAsMethod2
        $this->dispatcher->dispatch($event = new Event('eventSameAsMethod2'));
        $this->assertSame(array('eventSameAsMethod2'), $event->data);
    }

    public function testSubscriberException()
    {
        $this->expectException('LogicException');
        $this->expectExceptionMessage('Subscriber stdClass should implements Ekok\\EventDispatcher\\EventSubscriberInterface');

        $this->dispatcher->addSubscriber(stdClass::class);
    }
}
