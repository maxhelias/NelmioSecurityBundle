<?php

/*
 * This file is part of the Nelmio SecurityBundle.
 *
 * (c) Nelmio <hello@nelm.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nelmio\SecurityBundle\Tests\Session;

use Nelmio\SecurityBundle\Session\CookieSessionHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class CookieSessionHandlerTest extends \PHPUnit\Framework\TestCase
{
    private $handler;
    private $kernel;

    protected function setUp(): void
    {
        $this->handler = new CookieSessionHandler('s');

        $this->kernel = $this->getMockBuilder(HttpKernelInterface::class)->getMock();
    }

    public function testOpenWithNoRequest()
    {
        $this->expectException('RuntimeException');

        $this->handler->open('foo', 'bar');
    }

    public function testReadWithNoRequest()
    {
        $this->expectException('RuntimeException');

        $this->handler->read('foo');
    }

    public function testOpenWithoutSessionCookie()
    {
        $request = new Request();
        $response = new Response();
        $session = $this->getMockBuilder(SessionInterface::class)->getMock();
        $session->expects($this->once())->method('save');
        $request->setSession($session);

        if (class_exists(RequestEvent::class)) {
            $class = RequestEvent::class;
        } else {
            $class = 'Symfony\Component\HttpKernel\Event\GetResponseEvent';
        }

        $this->handler->onKernelRequest(new $class($this->kernel, $request, HttpKernelInterface::MASTER_REQUEST));

        $this->assertTrue($this->handler->open('foo', 'bar'));

        $this->handler->write('sessionId', 'mydata');

        if (class_exists(ResponseEvent::class)) {
            $class = ResponseEvent::class;
        } else {
            $class = 'Symfony\Component\HttpKernel\Event\FilterResponseEvent';
        }

        $this->handler->onKernelResponse(new $class($this->kernel, $request, HttpKernelInterface::MASTER_REQUEST, $response));

        $cookies = $response->headers->getCookies();

        $this->assertCount(1, $cookies);
        $this->assertEquals('a:2:{s:6:"expire";i:0;s:4:"data";s:6:"mydata";}', $cookies[0]->getValue());
        $this->assertEquals('s', $cookies[0]->getName());
    }

    public function testWriteDestroy()
    {
        $this->handler->write('sessionId', 'mydata');

        $request = new Request();
        $response = new Response();
        $session = $this->getMockBuilder(SessionInterface::class)->getMock();
        $session->expects($this->exactly(2))->method('save');
        $request->setSession($session);

        if (class_exists(RequestEvent::class)) {
            $class = RequestEvent::class;
        } else {
            $class = 'Symfony\Component\HttpKernel\Event\GetResponseEvent';
        }

        $this->handler->onKernelRequest(new $class($this->kernel, $request, HttpKernelInterface::MASTER_REQUEST));

        if (class_exists(ResponseEvent::class)) {
            $class = ResponseEvent::class;
        } else {
            $class = 'Symfony\Component\HttpKernel\Event\FilterResponseEvent';
        }

        $this->handler->onKernelResponse(new $class($this->kernel, $request, HttpKernelInterface::MASTER_REQUEST, $response));

        $cookies = $response->headers->getCookies();

        $this->assertCount(1, $cookies);
        $this->assertEquals('a:2:{s:6:"expire";i:0;s:4:"data";s:6:"mydata";}', $cookies[0]->getValue());
        $this->assertEquals('s', $cookies[0]->getName());

        $this->handler->destroy('sessionId');

        if (class_exists(ResponseEvent::class)) {
            $class = ResponseEvent::class;
        } else {
            $class = 'Symfony\Component\HttpKernel\Event\FilterResponseEvent';
        }

        $this->handler->onKernelResponse(new $class($this->kernel, $request, HttpKernelInterface::MASTER_REQUEST, $response));

        $cookies = $response->headers->getCookies();

        $this->assertCount(1, $cookies);
        $this->assertEquals('', $cookies[0]->getValue());
        $this->assertEquals('s', $cookies[0]->getName());
    }

    /**
     * Cookie not opened.
     */
    public function testCookieNotOpened()
    {
        $session = $this->getMockBuilder(SessionInterface::class)->getMock();
        $headers = $this->getMockBuilder(ResponseHeaderBag::class)->getMock();
        $headers
            ->method('clearCookie');
        $headers
            ->method('setCookie');

        $response = new Response();
        $request = new Request();
        $request->setSession($session);
        $response->headers = $headers;

        if (class_exists(RequestEvent::class)) {
            $class = RequestEvent::class;
        } else {
            $class = 'Symfony\Component\HttpKernel\Event\GetResponseEvent';
        }

        $this->handler->onKernelRequest(new $class($this->kernel, $request, HttpKernelInterface::MASTER_REQUEST));

        if (class_exists(ResponseEvent::class)) {
            $class = ResponseEvent::class;
        } else {
            $class = 'Symfony\Component\HttpKernel\Event\FilterResponseEvent';
        }

        $this->handler->onKernelResponse(new $class($this->kernel, $request, HttpKernelInterface::MASTER_REQUEST, $response));
    }
}
