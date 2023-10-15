<?php declare(strict_types = 1);

/**
 * ServerStatus.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 * @since          1.0.0
 *
 * @date           13.09.22
 */

namespace FastyBird\Connector\HomeKit\Types;

use Consistence;
use function strval;

/**
 * HAP server statuses types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ServerStatus extends Consistence\Enum\Enum
{

	/**
	 * Define statuses
	 */
	public const SUCCESS = 0;

	public const INSUFFICIENT_PRIVILEGES = -70_401;

	public const SERVICE_COMMUNICATION_FAILURE = -70_402;

	public const RESOURCE_BUSY = -70_403;

	public const READ_ONLY_CHARACTERISTIC = -70_404;

	public const WRITE_ONLY_CHARACTERISTIC = -70_405;

	public const NOTIFICATION_NOT_SUPPORTED = -70_406;

	public const OUT_OF_RESOURCE = -70_407;

	public const OPERATION_TIMED_OUT = -70_408;

	public const RESOURCE_DOES_NOT_EXIST = -70_409;

	public const INVALID_VALUE_IN_REQUEST = -70_410;

	public const INSUFFICIENT_AUTHORIZATION = -70_411;

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
