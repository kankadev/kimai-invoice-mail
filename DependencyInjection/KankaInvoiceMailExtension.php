<?php
declare(strict_types=1);
namespace KimaiPlugin\KankaInvoiceMailBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class KankaInvoiceMailExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        (new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config')))->load('services.yaml');
    }
}
