<?php declare(strict_types = 1);

/**
 * TlvMethod.php
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
 * TLV method value types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum TlvMethod: int
{

	case RESERVED = 0;

	case PAIR_SETUP = 1;

	case PAIR_VERIFY = 2;

	case ADD_PAIRING = 3;

	case REMOVE_PAIRING = 4;

	case LIST_PAIRINGS = 5;

}
