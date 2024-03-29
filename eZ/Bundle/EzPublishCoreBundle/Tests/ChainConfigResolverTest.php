<?php

namespace eZ\Bundle\EzPublishCoreBundle\Tests;

use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ChainConfigResolver;
use eZ\Publish\Core\MVC\ConfigResolverInterface;
use eZ\Publish\Core\MVC\Exception\ParameterNotFoundException;
use PHPUnit\Framework\TestCase;

class ChainConfigResolverTest extends TestCase
{
    /** @var \eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ChainConfigResolver */
    private $chainResolver;

    public function setUp()
    {
        $this->chainResolver = new ChainConfigResolver();
    }

    /**
     * @covers \eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ChainConfigResolver::addResolver
     * @covers \eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ChainConfigResolver::sortResolvers
     * @covers \eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ChainConfigResolver::getAllResolvers
     */
    public function testPriority()
    {
        $this->assertEquals([], $this->chainResolver->getAllResolvers());

        list($low, $high) = $this->createResolverMocks();

        $this->chainResolver->addResolver($low, 10);
        $this->chainResolver->addResolver($high, 100);

        $this->assertEquals(
            [
                $high,
                $low,
            ],
            $this->chainResolver->getAllResolvers()
        );
    }

    /**
     * Resolvers are supposed to be sorted only once.
     * This test will check that by trying to get all resolvers several times.
     *
     * @covers \eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ChainConfigResolver::addResolver
     * @covers \eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ChainConfigResolver::sortResolvers
     * @covers \eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ChainConfigResolver::getAllResolvers
     */
    public function testSortResolvers()
    {
        list($low, $medium, $high) = $this->createResolverMocks();
        // We're using a mock here and not $this->chainResolver because we need to ensure that the sorting operation is done only once.
        $resolver = $this->buildMock(
            ChainConfigResolver::class,
            ['sortResolvers']
        );
        $resolver
            ->expects($this->once())
            ->method('sortResolvers')
            ->will(
                $this->returnValue(
                    [$high, $medium, $low]
                )
            );

        $resolver->addResolver($low, 10);
        $resolver->addResolver($medium, 50);
        $resolver->addResolver($high, 100);
        $expectedSortedRouters = [$high, $medium, $low];
        // Let's get all routers 5 times, we should only sort once.
        for ($i = 0; $i < 5; ++$i) {
            $this->assertSame($expectedSortedRouters, $resolver->getAllResolvers());
        }
    }

    /**
     * This test ensures that if a resolver is being added on the fly, the sorting is reset.
     *
     * @covers \eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ChainConfigResolver::sortResolvers
     * @covers \eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ChainConfigResolver::getAllResolvers
     * @covers \eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ChainConfigResolver::addResolver
     */
    public function testReSortResolvers()
    {
        list($low, $medium, $high) = $this->createResolverMocks();
        $highest = clone $high;
        // We're using a mock here and not $this->chainResolver because we need to ensure that the sorting operation is done only once.
        $resolver = $this->buildMock(
            ChainConfigResolver::class,
            ['sortResolvers']
        );
        $resolver
            ->expects($this->at(0))
            ->method('sortResolvers')
            ->will(
                $this->returnValue(
                    [$high, $medium, $low]
                )
            );
        // The second time sortResolvers() is called, we're supposed to get the newly added router ($highest)
        $resolver
            ->expects($this->at(1))
            ->method('sortResolvers')
            ->will(
                $this->returnValue(
                    [$highest, $high, $medium, $low]
                )
            );

        $resolver->addResolver($low, 10);
        $resolver->addResolver($medium, 50);
        $resolver->addResolver($high, 100);
        $this->assertSame(
            [$high, $medium, $low],
            $resolver->getAllResolvers()
        );

        // Now adding another resolver on the fly, sorting must have been reset
        $resolver->addResolver($highest, 101);
        $this->assertSame(
            [$highest, $high, $medium, $low],
            $resolver->getAllResolvers()
        );
    }

    /**
     * @expectedException \LogicException
     * @covers \eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ChainConfigResolver::getDefaultNamespace
     */
    public function testGetDefaultNamespace()
    {
        $this->chainResolver->getDefaultNamespace();
    }

    /**
     * @covers \eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ChainConfigResolver::setDefaultNamespace
     */
    public function testSetDefaultNamespace()
    {
        $namespace = 'foo';
        foreach ($this->createResolverMocks() as $i => $resolver) {
            $resolver
                ->expects($this->once())
                ->method('setDefaultNamespace')
                ->with($namespace);
            $this->chainResolver->addResolver($resolver, $i);
        }

        $this->chainResolver->setDefaultNamespace($namespace);
    }

    /**
     * @expectedException \eZ\Publish\Core\MVC\Exception\ParameterNotFoundException
     * @covers \eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ChainConfigResolver::getParameter
     */
    public function testGetParameterInvalid()
    {
        $paramName = 'foo';
        $namespace = 'namespace';
        $scope = 'scope';
        foreach ($this->createResolverMocks() as $resolver) {
            $resolver
                ->expects($this->once())
                ->method('getParameter')
                ->with($paramName, $namespace, $scope)
                ->will($this->throwException(new ParameterNotFoundException($paramName, $namespace)));
            $this->chainResolver->addResolver($resolver);
        }

        $this->chainResolver->getParameter($paramName, $namespace, $scope);
    }

    /**
     * @dataProvider getParameterProvider
     * @covers \eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ChainConfigResolver::addResolver
     * @covers \eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ChainConfigResolver::getParameter
     *
     * @param string $paramName
     * @param string $namespace
     * @param string $scope
     * @param mixed $expectedValue
     */
    public function testGetParameter($paramName, $namespace, $scope, $expectedValue)
    {
        $resolver = $this->createMock(ConfigResolverInterface::class);
        $resolver
            ->expects($this->once())
            ->method('getParameter')
            ->with($paramName, $namespace, $scope)
            ->will($this->returnValue($expectedValue));

        $this->chainResolver->addResolver($resolver);
        $this->assertSame($expectedValue, $this->chainResolver->getParameter($paramName, $namespace, $scope));
    }

    public function getParameterProvider()
    {
        return [
            ['foo', 'namespace', 'scope', 'someValue'],
            ['some.parameter', 'wowNamespace', 'mySiteaccess', ['foo', 'bar']],
            ['another.parameter.but.longer.name', 'yetAnotherNamespace', 'anotherSiteaccess', ['foo', ['fruit' => 'apple']]],
            ['boolean.parameter', 'yetAnotherNamespace', 'admin', false],
        ];
    }

    /**
     * @covers \eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ChainConfigResolver::addResolver
     * @covers \eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ChainConfigResolver::hasParameter
     */
    public function testHasParameterTrue()
    {
        $paramName = 'foo';
        $namespace = 'yetAnotherNamespace';
        $scope = 'mySiteaccess';

        $resolver1 = $this->createMock(ConfigResolverInterface::class);
        $resolver1
            ->expects($this->once())
            ->method('hasParameter')
            ->with($paramName, $namespace, $scope)
            ->will($this->returnValue(false));
        $this->chainResolver->addResolver($resolver1);

        $resolver2 = $this->createMock(ConfigResolverInterface::class);
        $resolver2
            ->expects($this->once())
            ->method('hasParameter')
            ->with($paramName, $namespace, $scope)
            ->will($this->returnValue(true));
        $this->chainResolver->addResolver($resolver2);

        $resolver3 = $this->createMock(ConfigResolverInterface::class);
        $resolver3
            ->expects($this->never())
            ->method('hasParameter');
        $this->chainResolver->addResolver($resolver3);

        $this->assertTrue($this->chainResolver->hasParameter($paramName, $namespace, $scope));
    }

    public function testHasParameterFalse()
    {
        $paramName = 'foo';
        $namespace = 'yetAnotherNamespace';
        $scope = 'mySiteaccess';

        $resolver = $this->createMock(ConfigResolverInterface::class);
        $resolver
            ->expects($this->once())
            ->method('hasParameter')
            ->with($paramName, $namespace, $scope)
            ->will($this->returnValue(false));
        $this->chainResolver->addResolver($resolver);

        $this->assertFalse($this->chainResolver->hasParameter($paramName, $namespace, $scope));
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject[]
     */
    private function createResolverMocks()
    {
        return [
            $this->createMock(ConfigResolverInterface::class),
            $this->createMock(ConfigResolverInterface::class),
            $this->createMock(ConfigResolverInterface::class),
        ];
    }

    private function buildMock($class, array $methods = [])
    {
        return $this
            ->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();
    }
}
