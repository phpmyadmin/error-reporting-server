<?php

namespace App\Test\TestCase;

use Cake\TestSuite\TestSuite;

class AllTestsTest extends TestSuite
{
    public static function suite(): TestSuite
    {
        $suite = new TestSuite('All tests');
        $suite->addTestDirectoryRecursive(TESTS . 'TestCase');

        return $suite;
    }
}
