<?php declare(strict_types = 1);

/**
 * TlvState.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 * @since          1.0.0
 *
 * @date           20.09.22
 */

namespace FastyBird\Connector\HomeKit\Types;

/**
 * TLV state value types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum TlvState: int
{

	case M1 = 0x01;

	case M2 = 0x02;

	case M3 = 0x03;

	case M4 = 0x04;

	case M5 = 0x05;

	case M6 = 0x06;

}
