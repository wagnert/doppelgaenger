<?php
/**
 * File containing the RealTest class
 *
 * PHP version 5
 *
 * @category   Php-by-contract
 * @package    AppserverIo\Doppelgaenger
 * @subpackage Tests
 * @author     Bernhard Wick <b.wick@techdivision.com>
 * @copyright  2014 TechDivision GmbH - <info@techdivision.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 *             Open Software License (OSL 3.0)
 * @link       http://www.techdivision.com/
 */

namespace AppserverIo\Doppelgaenger\Tests\Functional;

use AppserverIo\Doppelgaenger\Tests\Data\RealTestClass;

/**
 * AppserverIo\Doppelgaenger\Tests\Functional\RealTest
 *
 * Will test with a real class taken from a random project
 *
 * @category   Php-by-contract
 * @package    AppserverIo\Doppelgaenger
 * @subpackage Tests
 * @author     Bernhard Wick <b.wick@techdivision.com>
 * @copyright  2014 TechDivision GmbH - <info@techdivision.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 *             Open Software License (OSL 3.0)
 * @link       http://www.techdivision.com/
 */
class RealTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Test if we can instantiate the class
     *
     * @return null
     */
    public function testInstantiation()
    {
        // Get the object to test
        new RealTestClass();
    }
}