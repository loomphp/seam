<?php

declare(strict_types=1);

namespace LoomTest\Seam;

use Loom\Seam\Exception\EmptyQueueException;
use Loom\Seam\SeamMiddleware;
use Loom\Seam\SeamMiddlewareInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest as Request;

use function get_class;
use function sort;
use function spl_object_hash;
use function strpos;
use function var_export;

class SeamMiddlewareTest extends TestCase
{
    use MiddlewareTrait;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var SeamMiddleware
     */
    private $seamMiddleware;

    protected function setUp()
    {
        $this->request  = new Request([], [], 'http://example.com/', 'GET', 'php://memory');
        $this->seamMiddleware = new SeamMiddleware();
    }

    private function createFinalHandler() : RequestHandlerInterface
    {
        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::any())->willReturn(new Response());

        return $handler->reveal();
    }

    public function testCanStitchPsrMiddleware()
    {
        $handler = $this->prophesize(RequestHandlerInterface::class)->reveal();

        $response = $this->prophesize(ResponseInterface::class)->reveal();
        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware
            ->process(
                Argument::type(ServerRequestInterface::class),
                Argument::type(RequestHandlerInterface::class)
            )
            ->willReturn($response);

        $seamMiddleware = new SeamMiddleware();
        $seamMiddleware->stitch($middleware->reveal());

        $this->assertSame($response, $seamMiddleware->process($this->request, $handler));
    }

    public function testProcessInvokesUntilFirstHandlerThatDoesNotCallNext()
    {
        $this->seamMiddleware->stitch(new class () implements MiddlewareInterface
        {
            public function process(ServerRequestInterface $req, RequestHandlerInterface $handler) : ResponseInterface
            {
                $res = $handler->handle($req);
                $res->getBody()->write("First\n");

                return $res;
            }
        });
        $this->seamMiddleware->stitch(new class () implements MiddlewareInterface
        {
            public function process(ServerRequestInterface $req, RequestHandlerInterface $handler) : ResponseInterface
            {
                $res = $handler->handle($req);
                $res->getBody()->write("Second\n");

                return $res;
            }
        });

        $response = new Response();
        $response->getBody()->write("Third\n");
        $this->seamMiddleware->stitch($this->getMiddlewareWhichReturnsResponse($response));

        $this->seamMiddleware->stitch($this->getNotCalledMiddleware());

        $request = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');
        $response = $this->seamMiddleware->process($request, $this->createFinalHandler());
        $body = (string) $response->getBody();
        $this->assertContains('First', $body);
        $this->assertContains('Second', $body);
        $this->assertContains('Third', $body);
    }

    public function testInvokesHandlerWhenQueueIsExhausted()
    {
        $expected = $this->prophesize(ResponseInterface::class)->reveal();

        $this->seamMiddleware->stitch($this->getPassToHandlerMiddleware());
        $this->seamMiddleware->stitch($this->getPassToHandlerMiddleware());
        $this->seamMiddleware->stitch($this->getPassToHandlerMiddleware());

        $request = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle($request)->willReturn($expected);

        $result = $this->seamMiddleware->process($request, $handler->reveal());

        $this->assertSame($expected, $result);
    }

    public function testReturnsResponseReturnedByQueue()
    {
        $return = new Response();

        $this->seamMiddleware->stitch($this->getPassToHandlerMiddleware());
        $this->seamMiddleware->stitch($this->getPassToHandlerMiddleware());
        $this->seamMiddleware->stitch($this->getMiddlewareWhichReturnsResponse($return));

        $this->seamMiddleware->stitch($this->getNotCalledMiddleware());

        $request = new Request([], [], 'http://local.example.com/foo', 'GET', 'php://memory');
        $result  = $this->seamMiddleware->process($request, $this->createFinalHandler());
        $this->assertSame($return, $result, var_export([
            spl_object_hash($return) => get_class($return),
            spl_object_hash($result) => get_class($result),
        ], true));
    }

    public function testHandleRaisesExceptionIfQueueIsEmpty()
    {
        $request = $this->prophesize(ServerRequestInterface::class)->reveal();

        $this->expectException(EmptyQueueException::class);

        $this->seamMiddleware->handle($request);
    }

    public function testHandleProcessesEnqueuedMiddleware()
    {
        $response = $this->prophesize(ResponseInterface::class)->reveal();
        $middleware1 = $this->prophesize(MiddlewareInterface::class);
        $middleware1
            ->process(
                $this->request,
                Argument::type(RequestHandlerInterface::class)
            )
            ->will(function ($args) {
                $request = $args[0];
                $handler = $args[1];
                return $handler->handle($request);
            });
        $middleware2 = $this->prophesize(MiddlewareInterface::class);
        $middleware2
            ->process(
                $this->request,
                Argument::type(RequestHandlerInterface::class)
            )
            ->willReturn($response);

        $seamMiddleware = new SeamMiddleware();
        $seamMiddleware->stitch($middleware1->reveal());
        $seamMiddleware->stitch($middleware2->reveal());

        $this->assertSame($response, $seamMiddleware->handle($this->request));
    }

    public function testSeamMiddlewareOnlyImplementsSeamMiddlewareInterfaceApi()
    {
        $seamMiddleware = new SeamMiddleware();

        $r = new ReflectionObject($seamMiddleware);
        $methods = $r->getMethods(ReflectionMethod::IS_PUBLIC);
        $actual = [];
        foreach ($methods as $method) {
            if (strpos($method->getName(), '__') !== 0) {
                $actual[] = $method->getName();
            }
        }
        sort($actual);

        $interfaceReflection = new ReflectionClass(SeamMiddlewareInterface::class);
        $interfaceMethods = $interfaceReflection->getMethods(ReflectionMethod::IS_PUBLIC);
        $expected = [];
        foreach ($interfaceMethods as $method) {
            $expected[] = $method->getName();
        }
        sort($expected);

        self::assertTrue($r->isFinal());
        self::assertEquals($expected, $actual);
        self::assertInstanceOf(SeamMiddlewareInterface::class, $seamMiddleware);
    }
}
