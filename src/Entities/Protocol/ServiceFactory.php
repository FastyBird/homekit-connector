<?php declare(strict_types = 1);

/**
 * ServiceFactory.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 * @since          0.19.0
 *
 * @date           13.09.22
 */

namespace FastyBird\HomeKitConnector\Entities\Protocol;

use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Helpers;
use FastyBird\Metadata\Entities as MetadataEntities;
use Nette\Utils;

/**
 * HAP service factory
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ServiceFactory
{

	/** @var Helpers\Loader */
	private Helpers\Loader $loader;

	/**
	 * @param Helpers\Loader $loader
	 */
	public function __construct(
		Helpers\Loader $loader
	) {
		$this->loader = $loader;
	}

	/**
	 * @param string $name
	 * @param Accessory $accessory
	 * @param MetadataEntities\Modules\DevicesModule\ChannelEntity|null $channel
	 *
	 * @return Service
	 */
	public function create(
		string $name,
		Accessory $accessory,
		?MetadataEntities\Modules\DevicesModule\ChannelEntity $channel = null
	): Service {
		$metadata = $this->loader->loadServices();

		if (!$metadata->offsetExists($name)) {
			throw new Exceptions\InvalidArgument(sprintf(
				'Definition for service: %s was not found',
				$name
			));
		}

		$serviceMetadata = $metadata->offsetGet($name);

		if (
			!$serviceMetadata instanceof Utils\ArrayHash
			|| !$serviceMetadata->offsetExists('UUID')
			|| !\is_string($serviceMetadata->offsetGet('UUID'))
			|| !$serviceMetadata->offsetExists('RequiredCharacteristics')
			|| !$serviceMetadata->offsetGet('RequiredCharacteristics') instanceof Utils\ArrayHash
		) {
			throw new Exceptions\InvalidState('Service definition is missing required attributes');
		}

		return new Service(
			Helpers\Protocol::hapTypeToUuid(\strval($serviceMetadata->offsetGet('UUID'))),
			$name,
			$accessory,
			$channel,
			(array) $serviceMetadata->offsetGet('RequiredCharacteristics'),
			$serviceMetadata->offsetExists('OptionalCharacteristics') && $serviceMetadata->offsetGet('OptionalCharacteristics') instanceof Utils\ArrayHash ? (array) $serviceMetadata->offsetGet('OptionalCharacteristics') : []
		);
	}

}
