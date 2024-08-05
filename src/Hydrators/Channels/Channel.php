<?php declare(strict_types = 1);

/**
 * Channel.php
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

use Doctrine\Persistence;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Schemas;
use FastyBird\JsonApi\Exceptions as JsonApiExceptions;
use FastyBird\JsonApi\Helpers;
use FastyBird\Library\Application\Exceptions as ApplicationExceptions;
use FastyBird\Module\Devices\Hydrators as DevicesHydrators;
use FastyBird\Module\Devices\Models as DevicesModels;
use Fig\Http\Message\StatusCodeInterface;
use IPub\JsonAPIDocument;
use Nette\Localization;
use Ramsey\Uuid;
use function is_string;
use function strval;

/**
 * HomeKit channel entity hydrator
 *
 * @template  T of Entities\Channels\Channel
 * @extends   DevicesHydrators\Channels\Channel<T>
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Hydrators
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
abstract class Channel extends DevicesHydrators\Channels\Channel
{

	public function __construct(
		private readonly DevicesModels\Entities\Devices\DevicesRepository $devicesRepository,
		Persistence\ManagerRegistry $managerRegistry,
		Localization\Translator $translator,
		Helpers\CrudReader|null $crudReader = null,
	)
	{
		parent::__construct($managerRegistry, $translator, $crudReader);
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws JsonApiExceptions\JsonApiError
	 */
	protected function hydrateDeviceRelationship(
		JsonAPIDocument\Objects\IRelationshipObject $relationship,
		JsonAPIDocument\Objects\IResourceObjectCollection|null $included,
		Entities\Channels\Channel|null $entity,
	): Entities\Devices\Device
	{
		if (
			$relationship->getData() instanceof JsonAPIDocument\Objects\IResourceIdentifierObject
			&& is_string($relationship->getData()->getId())
			&& Uuid\Uuid::isValid($relationship->getData()->getId())
		) {
			$device = $this->devicesRepository->find(
				Uuid\Uuid::fromString($relationship->getData()->getId()),
				Entities\Devices\Device::class,
			);

			if ($device !== null) {
				return $device;
			}
		}

		throw new JsonApiExceptions\JsonApiError(
			StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
			strval($this->translator->translate('//homekit-connector.base.messages.invalidRelation.heading')),
			strval($this->translator->translate('//homekit-connector.base.messages.invalidRelation.message')),
			[
				'pointer' => '/data/relationships/' . Schemas\Channels\Channel::RELATIONSHIPS_DEVICE . '/data/id',
			],
		);
	}

}
