<?php
namespace Cyberhouse\DoctrineORM\Tests\Utility;

/*
 * This file is (c) 2018 by Cyberhouse GmbH
 *
 * It is free software; you can redistribute it and/or
 * modify it under the terms of the GPLv3 license
 *
 * For the full copyright and license information see
 * <https://www.gnu.org/licenses/gpl-3.0.html>
 */

use Cyberhouse\DoctrineORM\Utility\ExtensionRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Test the extension registry
 *
 * @author Georg Großberger <georg.grossberger@cyberhouse.at>
 */
class ExtensionRegistryTest extends TestCase
{
    public function testPathsAreSetAndReturned()
    {
        $key = 'doctrine_orm';
        $paths = [__DIR__];

        $registry = new ExtensionRegistry();
        $registry->register($key, ...$paths);
        $actual = $registry->getExtensionPaths($key);

        $this->assertSame($paths, $actual);
    }

    public function testUnkownPathsAreExcluded()
    {
        $expected = [__DIR__];
        $key = 'doctrine_orm';

        $registry = new ExtensionRegistry();
        $registry->register($key, __DIR__, 'unknown');

        $actual = $registry->getExtensionPaths($key);

        $this->assertSame($expected, $actual);
    }

    public function testDefaultPathIsUsedIfNoneGiven()
    {
        $extKey = 'my_ext';
        $expected = [$extKey => ['EXT:' . $extKey . '/Classes/Domain/Model']];

        $registry = new ExtensionRegistry();
        $registry->register($extKey);

        $property = (new \ReflectionObject($registry))->getProperty('registered');
        $property->setAccessible(true);
        $actual = $property->getValue($registry);

        $this->assertSame($expected, $actual);
    }

    public function testNoValidPathsRaiseException()
    {
        $this->expectException(\UnexpectedValueException::class);

        $registry = new ExtensionRegistry();
        $registry->register('doctrine_orm', 'EXT:no_ext/this/does/not/exist');
        $registry->getExtensionPaths('doctrine_orm');
    }

    public function testGetRegisteredExtensionList()
    {
        $ext1 = 'my_ext';
        $ext2 = 'my_better_ext';

        $registry = new ExtensionRegistry();
        $registry->register($ext1);
        $registry->register($ext2);

        $this->assertSame([$ext1, $ext2], $registry->getRegisteredExtensions());
    }
}
