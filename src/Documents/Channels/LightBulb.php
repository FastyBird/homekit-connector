<?php declare(strict_types = 1);

/**
 * LightBulb.php
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
use FastyBird\Core\Application\Documents as ApplicationDocuments;

#[ApplicationDocuments\Mapping\Document(entity: Entities\Channels\LightBulb::class)]
#[ApplicationDocuments\Mapping\DiscriminatorEntry(name: Entities\Channels\LightBulb::TYPE)]
class LightBulb extends Channel
{

	public static function getType(): string
	{
		return Entities\Channels\LightBulb::TYPE;
	}

}
