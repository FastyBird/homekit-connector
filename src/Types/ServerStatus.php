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

namespace FastyBird\HomeKitConnector\Types;

use Consistence;

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
	public const STATUS_INSUFFICIENT_PRIVILEGES = -70401;
	public const STATUS_SERVICE_COMMUNICATION_FAILURE = -70402;
	public const STATUS_RESOURCE_BUSY = -70403;
	public const STATUS_READ_ONLY_CHARACTERISTIC = -70404;
	public const STATUS_WRITE_ONLY_CHARACTERISTIC = -70405;
	public const STATUS_NOTIFICATION_NOT_SUPPORTED = -70406;
	public const STATUS_OUT_OF_RESOURCE = -70407;
	public const STATUS_OPERATION_TIMED_OUT = -70408;
	public const STATUS_RESOURCE_DOES_NOT_EXIST = -70409;
	public const STATUS_INVALID_VALUE_IN_REQUEST = -70410;
	public const STATUS_INSUFFICIENT_AUTHORIZATION = -70411;

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
