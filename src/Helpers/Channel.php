<?php declare(strict_types = 1);

/**
 * Channel.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Helpers
 * @since          1.0.0
 *
 * @date           18.11.23
 */

namespace FastyBird\Connector\HomeKit\Helpers;

use FastyBird\Connector\HomeKit\Documents;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Types;
use TypeError;
use ValueError;
use function array_key_exists;
use function preg_match;
use function str_replace;
use function ucwords;

/**
 * Channel helper
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Helpers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Channel
{

	/**
	 * @throws Exceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function getServiceType(Documents\Channels\Channel $channel): Types\ServiceType
	{
		preg_match(Entities\Channels\Channel::SERVICE_IDENTIFIER, $channel->getIdentifier(), $matches);

		if (!array_key_exists('type', $matches)) {
			throw new Exceptions\InvalidState('Device channel has invalid identifier');
		}

		$type = str_replace(' ', '', ucwords(str_replace('_', ' ', $matches['type'])));

		if (Types\ServiceType::tryFrom($type) === null) {
			throw new Exceptions\InvalidState('Device channel has invalid identifier');
		}

		return Types\ServiceType::from($type);
	}

}
