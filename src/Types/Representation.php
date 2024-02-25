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

/**
 * HAP Representation type
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum Representation: string
{

	case ACCS = 'accessories';

	case AID = 'aid';

	case CHARS = 'characteristics';

	case DESC = 'description';

	case FORMAT = 'format';

	case IID = 'iid';

	case MAX_LEN = 'maxLen';

	case PERM = 'perms';

	case PID = 'pid';

	case PRIMARY = 'primary';

	case HIDDEN = 'hidden';

	case SERVICES = 'services';

	case LINKED = 'linked';

	case STATUS = 'status';

	case TTL = 'ttl';

	case TYPE = 'type';

	case VALUE = 'value';

	case VALID_VALUES = 'valid-values';

	case MAX_VALUE = 'maxValue';

	case MIN_STEP = 'minStep';

	case MIN_VALUE = 'minValue';

	case UNIT = 'unit';

	case META = 'meta';

}
