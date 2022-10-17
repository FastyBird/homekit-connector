<?php declare(strict_types = 1);

/**
 * InvalidArgument.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Exceptions
 * @since          0.19.0
 *
 * @date           13.09.22
 */

namespace FastyBird\Connector\HomeKit\Exceptions;

use InvalidArgumentException as PHPInvalidArgumentException;

class InvalidArgument extends PHPInvalidArgumentException implements Exception
{

}
