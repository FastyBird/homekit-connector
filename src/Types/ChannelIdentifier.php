<?php declare(strict_types = 1);

/**
 * ChannelPropertyIdentifier.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 * @since          0.19.0
 *
 * @date           06.10.22
 */

namespace FastyBird\HomeKitConnector\Types;

use Consistence;
use function strval;

/**
 * Channel identifier types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ChannelIdentifier extends Consistence\Enum\Enum
{

	/**
	 * Define channel identifiers
	 */
	public const IDENTIFIER_ACCESSORY_INFORMATION = 'accessory_information';

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
