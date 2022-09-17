<?php declare(strict_types = 1);

/**
 * CharacteristicPropertyIdentifier.php
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
 * HAP characteristic property identifier type
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class CharacteristicPropertyIdentifier extends Consistence\Enum\Enum
{

	/**
	 * Define properties identifiers
	 */
	public const IDENTIFIER_FORMAT = 'Format';
	public const IDENTIFIER_MAX_VALUE = 'maxValue';
	public const IDENTIFIER_MIN_STEP = 'minStep';
	public const IDENTIFIER_MIN_VALUE = 'minValue';
	public const IDENTIFIER_PERMISSIONS = 'Permissions';
	public const IDENTIFIER_UNIT = 'unit';
	public const IDENTIFIER_VALID_VALUES = 'ValidValues';

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
