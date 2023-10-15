<?php declare(strict_types = 1);

/**
 * CharacteristicUnit.php
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
	public const CELSIUS = 'celsius';

	public const PERCENTAGE = 'percentage';

	public const ARC_DEGREES = 'arcdegrees';

	public const LUX = 'lux';

	public const SECONDS = 'seconds';

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
