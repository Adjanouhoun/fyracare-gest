<?php

namespace App\Tests\Twig;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

final class ControllerTemplateContractTest extends KernelTestCase
{
    public function testControllerTemplatesAndTheirDirectRoutesExist(): void
    {
        self::bootKernel();
        $projectDir = dirname(__DIR__, 2);
        $router = static::getContainer()->get(RouterInterface::class);
        $routes = $router->getRouteCollection();
        $templates = [];

        $controllers = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($projectDir.'/src/Controller'),
        );

        foreach ($controllers as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            preg_match_all(
                '/(?:render|renderView)\(\s*[\'"]([^\'"]+)[\'"]/',
                $source,
                $matches,
            );

            foreach ($matches[1] as $template) {
                $templates[$template] = true;
            }
        }

        self::assertNotEmpty($templates);

        foreach (array_keys($templates) as $template) {
            $path = $projectDir.'/templates/'.$template;
            self::assertFileExists($path, sprintf('Vue introuvable : %s', $template));
            $source = file_get_contents($path);
            self::assertIsString($source);
            preg_match_all('/(?:path|url)\(\s*[\'"]([^\'"]+)[\'"]/', $source, $matches);

            foreach ($matches[1] as $routeName) {
                self::assertNotNull(
                    $routes->get($routeName),
                    sprintf('Route %s introuvable dans %s.', $routeName, $template),
                );
            }
        }
    }

    public function testCanonicalCashExpenseRoutesKeepLegacyCompatibility(): void
    {
        self::bootKernel();
        $routes = static::getContainer()->get(RouterInterface::class)->getRouteCollection();

        self::assertSame('/cash/expense/{id}', $routes->get('cash_expense_show')?->getPath());
        self::assertSame('/cash/cash/expense/{id}', $routes->get('cash_expense_show_legacy')?->getPath());
        self::assertSame('/cash/expense/{id}/cancel', $routes->get('cash_expense_cancel')?->getPath());
        self::assertSame('/cash/cash/expense/{id}/cancel', $routes->get('cash_expense_cancel_legacy')?->getPath());
    }
}
