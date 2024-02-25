<?php declare(strict_types = 1);

/**
 * Battery.php
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
 * Battery channel entity hydrator
 *
 * @template  T of Entities\Channels\Battery
 * @extends   Channel<T>
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Hydrators
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Battery extends Channel
{

	/**
	 * @return class-string<Entities\Channels\Battery>
	 */
	public function getEntityName(): string
	{
		return Entities\Channels\Battery::class;
	}

}
