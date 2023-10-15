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
	public const BOOLEAN = 'bool';

	public const INT = 'int';

	public const FLOAT = 'float';

	public const STRING = 'string';

	public const UINT8 = 'uint8';

	public const UINT16 = 'uint16';

	public const UINT32 = 'uint32';

	public const UINT64 = 'uint64';

	public const DATA = 'data';

	public const TLV8 = 'tlv8';

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
