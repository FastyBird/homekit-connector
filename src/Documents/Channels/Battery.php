<?php declare(strict_types = 1);

/**
 * Battery.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Documents
 * @since          1.0.0
 *
 * @date           10.02.24
 */

namespace FastyBird\Connector\HomeKit\Documents\Channels;

use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Library\Metadata\Documents\Mapping as DOC;

#[DOC\Document(entity: Entities\Channels\Battery::class)]
#[DOC\DiscriminatorEntry(name: Entities\Channels\Battery::TYPE)]
class Battery extends Channel
{

	public static function getType(): string
	{
		return Entities\Channels\Battery::TYPE;
	}

}
