<?php declare(strict_types = 1);

/**
 * Channel.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Schemas
 * @since          1.0.0
 *
 * @date           04.03.23
 */

namespace FastyBird\Connector\HomeKit\Schemas\Channels;

use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Module\Devices\Schemas as DevicesSchemas;

/**
 * HomeKit channel entity schema
 *
 * @template T of Entities\Channels\Channel
 * @extends  DevicesSchemas\Channels\Channel<T>
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Schemas
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
abstract class Channel extends DevicesSchemas\Channels\Channel
{

}
