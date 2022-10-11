<?php declare(strict_types = 1);

/**
 * DataType.php
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
use function strval;

/**
 * HAP data type
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class DataType extends Consistence\Enum\Enum
{

	/**
	 * Define data types
	 */
	public const DATA_TYPE_BOOLEAN = 'bool';

	public const DATA_TYPE_INT = 'int';

	public const DATA_TYPE_FLOAT = 'float';

	public const DATA_TYPE_STRING = 'string';

	public const DATA_TYPE_UINT8 = 'uint8';

	public const DATA_TYPE_UINT16 = 'uint16';

	public const DATA_TYPE_UINT32 = 'uint32';

	public const DATA_TYPE_UINT64 = 'uint64';

	public const DATA_TYPE_DATA = 'data';

	public const DATA_TYPE_TLV8 = 'tlv8';

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
