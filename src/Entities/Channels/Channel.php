<?php declare(strict_types = 1);

/**
 * Channel.php
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

namespace FastyBird\Connector\HomeKit\Entities\Channels;

use Doctrine\ORM\Mapping as ORM;
use FastyBird\Connector\HomeKit;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use Ramsey\Uuid;
use TypeError;
use ValueError;
use function array_key_exists;
use function assert;
use function preg_match;
use function str_replace;
use function ucwords;

#[ORM\Entity]
#[ORM\MappedSuperclass]
abstract class Channel extends DevicesEntities\Channels\Channel
{

	public const SERVICE_IDENTIFIER = '/^(?P<type>[a-z_]+)(?:_(?P<cnt>[0-9]+){1})$/';

	public function __construct(
		Entities\Devices\Device $device,
		string $identifier,
		string|null $name = null,
		Uuid\UuidInterface|null $id = null,
	)
	{
		parent::__construct($device, $identifier, $name, $id);
	}

	public function getSource(): MetadataTypes\Sources\Connector
	{
		return MetadataTypes\Sources\Connector::HOMEKIT;
	}

	public function getDevice(): Entities\Devices\Device
	{
		assert($this->device instanceof Entities\Devices\Device);

		return $this->device;
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function getServiceType(): Types\ServiceType
	{
		preg_match(HomeKit\Constants::SERVICE_IDENTIFIER, $this->getIdentifier(), $matches);

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
