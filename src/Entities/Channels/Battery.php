<?php declare(strict_types = 1);

/**
 * Battery.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           30.01.24
 */

namespace FastyBird\Connector\HomeKit\Entities\Channels;

use Doctrine\ORM\Mapping as ORM;
use FastyBird\Core\Application\Entities\Mapping as ApplicationMapping;

#[ORM\Entity]
#[ApplicationMapping\DiscriminatorEntry(name: self::TYPE)]
class Battery extends Channel
{

	public const TYPE = 'homekit-connector-battery';

	public static function getType(): string
	{
		return self::TYPE;
	}

}
