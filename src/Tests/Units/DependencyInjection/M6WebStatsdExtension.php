<?php
namespace M6Web\Bundle\StatsdBundle\DependencyInjection\tests\units;

use mageekguy\atoum;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use M6Web\Bundle\StatsdBundle\DependencyInjection\M6WebStatsdExtension as BaseM6WebStatsdExtension;
use Symfony\Component\EventDispatcher\EventDispatcher;


class M6WebStatsdExtension extends atoum\test
{
    /**
     * @var BaseM6WebStatsdExtension
     */
    protected  $extension;

    /**
     * @var ContainerBuilder
     */
    protected $container;

    protected function initContainer($resource, $debug = false)
    {
        $this->extension = new BaseM6WebStatsdExtension();

        $this->container = new ContainerBuilder();
        $this->container->register('event_dispatcher', new EventDispatcher());
        $this->container->registerExtension($this->extension);

        $this->loadConfiguration($this->container, $resource);

        $this->container->setParameter('kernel.debug', $debug);
        $this->container->compile();
    }

    /**
     * @param ContainerBuilder $container
     * @param                  $resource
     */
    protected function loadConfiguration(ContainerBuilder $container, $resource)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../Fixtures/'));
        $loader->load($resource.'.yml');
    }

    public function testBasicConfiguration()
    {
        $this->initContainer('basic_config', true);

        $this->assert
            ->boolean($this->container->has('m6_statsd'))
                ->isIdenticalTo(true)
            ->and()
                ->object($serviceStatsd = $this->container->get('m6_statsd'))
                    ->isInstanceOf('M6Web\Bundle\StatsdBundle\Client\Client');
        // @TODO : check the client more

        // check datacollector
        $this->assert
            ->object($dataCollector = $this->container->get('m6.data_collector.statsd'))
                ->isInstanceOf('M6Web\Bundle\StatsdBundle\DataCollector\StatsdDataCollector');
    }

    public function testBasicConfigurationWithoutKernelDebug()
    {
        $this->initContainer('basic_config');

        $this->assert
            ->boolean($this->container->has('m6.data_collector.statsd'))
                ->isIdenticalTo(false);
    }

    /**
     * @dataProvider shellPatternConfigDataProvider
     */
    public function testShellPatternConfig($service, $expectedServers)
    {
        $this->initContainer('shell_pattern');

        $this
            ->object($definition = $this
                ->container
                ->getDefinition(sprintf('m6_statsd.%s', $service))
            )
            ->array($arguments = $definition->getArguments())
            ->array($servers = array_pop($arguments))
        ;

        foreach ($expectedServers as $key => $expectedServer) {
            $this
                ->string($servers[$key]['address'])
                    ->isEqualTo(sprintf('udp://%s', $expectedServer));
        }
    }

    public function shellPatternConfigDataProvider()
    {
        return [
            ['wildcard_foo',     ['foo', 'foobar', 'fooa', 'foob']],
            ['all',              ['foo', 'foobar', 'fooa', 'foob', 'bar', 'barfoo']],
            ['all_bis',          ['bar', 'barfoo', 'foo', 'foobar', 'fooa', 'foob']],
            ['foo_plusonechar',  ['fooa', 'foob']],
            ['foo_ab',           ['fooa', 'foob']],
            ['complex_ab',       ['fooa', 'foob']]

        ];
    }
}