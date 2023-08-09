<?php declare(strict_types = 1);

/**
 * HomeKitChannel.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomekitConnector!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           04.03.22
 */

namespace FastyBird\Connector\HomeKit\Entities;

use Doctrine\ORM\Mapping as ORM;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use function array_key_exists;
use function preg_match;
use function str_replace;
use function ucwords;

/**
 * @ORM\Entity
 */
class HomeKitChannel extends DevicesEntities\Channels\Channel
{

	public const TYPE = 'homekit';

	public const SERVICE_IDENTIFIER = '/^(?P<type>[a-z_]+)(?:_(?P<cnt>[0-9]+){1})$/';

	public function getType(): string
	{
		return self::TYPE;
	}

	public function getDiscriminatorName(): string
	{
		return self::TYPE;
	}

	public function getSource(): MetadataTypes\ConnectorSource
	{
		return MetadataTypes\ConnectorSource::get(MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT);
	}

	/**
	 * @throws Exceptions\InvalidState
	 */
	public function getServiceType(): Types\ServiceType
	{
		preg_match(self::SERVICE_IDENTIFIER, $this->getIdentifier(), $matches);

		if (!array_key_exists('type', $matches)) {
			throw new Exceptions\InvalidState('Device channel has invalid identifier');
		}

		$type = str_replace(' ', '', ucwords(str_replace('_', ' ', $matches['type'])));

		if (!Types\ServiceType::isValidValue($type)) {
			throw new Exceptions\InvalidState('Device channel has invalid identifier');
		}

		return Types\ServiceType::get($type);
	}

}
