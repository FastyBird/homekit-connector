<?php declare(strict_types = 1);

/**
 * ServerStatus.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 * @since          0.19.0
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
	public const STATUS_SUCCESS = 0;

	public const STATUS_INSUFFICIENT_PRIVILEGES = -70_401;

	public const STATUS_SERVICE_COMMUNICATION_FAILURE = -70_402;

	public const STATUS_RESOURCE_BUSY = -70_403;

	public const STATUS_READ_ONLY_CHARACTERISTIC = -70_404;

	public const STATUS_WRITE_ONLY_CHARACTERISTIC = -70_405;

	public const STATUS_NOTIFICATION_NOT_SUPPORTED = -70_406;

	public const STATUS_OUT_OF_RESOURCE = -70_407;

	public const STATUS_OPERATION_TIMED_OUT = -70_408;

	public const STATUS_RESOURCE_DOES_NOT_EXIST = -70_409;

	public const STATUS_INVALID_VALUE_IN_REQUEST = -70_410;

	public const STATUS_INSUFFICIENT_AUTHORIZATION = -70_411;

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
