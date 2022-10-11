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
 * Channel property identifier types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ChannelPropertyIdentifier extends Consistence\Enum\Enum
{

	/**
	 * Define channel properties identifiers
	 */
	public const IDENTIFIER_VERSION = 'version';

	public const IDENTIFIER_NAME = 'name';

	public const IDENTIFIER_SERIAL_NUMBER = 'serial_number';

	public const IDENTIFIER_FIRMWARE_REVISION = 'firmware_revision';

	public const IDENTIFIER_MANUFACTURER = 'manufacturer';

	public const IDENTIFIER_MODEL = 'model';

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
