<?php declare(strict_types = 1);

/**
 * CharacteristicUnit.php
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
 * HAP accessory permissions type
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class CharacteristicUnit extends Consistence\Enum\Enum
{

	/**
	 * Define permissions identifiers
	 */
	public const UNIT_CELSIUS = 'celsius';

	public const UNIT_PERCENTAGE = 'percentage';

	public const UNIT_ARCDEGREES = 'arcdegrees';

	public const UNIT_LUX = 'lux';

	public const UNIT_SECONDS = 'seconds';

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
