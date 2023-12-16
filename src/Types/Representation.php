<?php declare(strict_types = 1);

/**
 * Representation.php
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
 * HAP Representation type
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Representation extends Consistence\Enum\Enum
{

	/**
	 * Define permissions identifiers
	 */
	public const ACCS = 'accessories';

	public const AID = 'aid';

	public const CHARS = 'characteristics';

	public const DESC = 'description';

	public const FORMAT = 'format';

	public const IID = 'iid';

	public const MAX_LEN = 'maxLen';

	public const PERM = 'perms';

	public const PID = 'pid';

	public const PRIMARY = 'primary';

	public const HIDDEN = 'hidden';

	public const SERVICES = 'services';

	public const LINKED = 'linked';

	public const STATUS = 'status';

	public const TTL = 'ttl';

	public const TYPE = 'type';

	public const VALUE = 'value';

	public const VALID_VALUES = 'valid-values';

	public const MAX_VALUE = 'maxValue';

	public const MIN_STEP = 'minStep';

	public const MIN_VALUE = 'minValue';

	public const UNIT = 'unit';

	public const META = 'meta';

	public function getValue(): string
	{
		return strval(parent::getValue());
	}

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
