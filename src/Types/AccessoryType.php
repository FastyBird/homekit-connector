<?php declare(strict_types = 1);

/**
 * AccessoryType.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 * @since          1.0.0
 *
 * @date           12.04.23
 */

namespace FastyBird\Connector\HomeKit\Types;

use Consistence;
use function strval;

/**
 * HAP accessory type
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class AccessoryType extends Consistence\Enum\Enum
{

	/**
	 * Define categories
	 */
	public const GENERIC = 'generic';

	public const LIGHT_HSB = 'light_hsb';

	public function getValue(): string
	{
		return strval(parent::getValue());
	}

	/**
	 * @return array<int>
	 */
	public static function getValues(): array
	{
		/** @var iterable<int> $availableValues */
		$availableValues = parent::getAvailableValues();

		return (array) $availableValues;
	}

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
