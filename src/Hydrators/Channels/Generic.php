<?php declare(strict_types = 1);

/**
 * Generic.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Hydrators
 * @since          1.0.0
 *
 * @date           24.01.23
 */

namespace FastyBird\Connector\HomeKit\Hydrators\Channels;

use FastyBird\Connector\HomeKit\Entities;

/**
 * Generic channel entity hydrator
 *
 * @template  T of Entities\Channels\Generic
 * @extends   Channel<T>
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Hydrators
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Generic extends Channel
{

	/**
	 * @return class-string<Entities\Channels\Generic>
	 */
	public function getEntityName(): string
	{
		return Entities\Channels\Generic::class;
	}

}
