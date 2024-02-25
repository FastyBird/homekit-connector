<?php declare(strict_types = 1);

/**
 * DataType.php
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
 * HAP data type
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum DataType: string
{

	case BOOLEAN = 'bool';

	case INT = 'int';

	case FLOAT = 'float';

	case STRING = 'string';

	case UINT8 = 'uint8';

	case UINT16 = 'uint16';

	case UINT32 = 'uint32';

	case UINT64 = 'uint64';

	case DATA = 'data';

	case TLV8 = 'tlv8';

}
