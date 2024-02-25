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

/**
 * HAP server statuses types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum ServerStatus: int
{

	case SUCCESS = 0;

	case INSUFFICIENT_PRIVILEGES = -70_401;

	case SERVICE_COMMUNICATION_FAILURE = -70_402;

	case RESOURCE_BUSY = -70_403;

	case READ_ONLY_CHARACTERISTIC = -70_404;

	case WRITE_ONLY_CHARACTERISTIC = -70_405;

	case NOTIFICATION_NOT_SUPPORTED = -70_406;

	case OUT_OF_RESOURCE = -70_407;

	case OPERATION_TIMED_OUT = -70_408;

	case RESOURCE_DOES_NOT_EXIST = -70_409;

	case INVALID_VALUE_IN_REQUEST = -70_410;

	case INSUFFICIENT_AUTHORIZATION = -70_411;

}
