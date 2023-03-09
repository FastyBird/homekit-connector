<?php declare(strict_types = 1);

/**
 * HomeKitChannel.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Schemas
 * @since          1.0.0
 *
 * @date           04.03.23
 */

namespace FastyBird\Connector\HomeKit\Schemas;

use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Schemas as DevicesSchemas;

/**
 * HomeKit channel entity schema
 *
 * @extends DevicesSchemas\Channels\Channel<Entities\HomeKitChannel>
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Schemas
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class HomeKitChannel extends DevicesSchemas\Channels\Channel
{

	/**
	 * Define entity schema type string
	 */
	public const SCHEMA_TYPE = MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT . '/channel/' . Entities\HomeKitChannel::CHANNEL_TYPE;

	public function getEntityClass(): string
	{
		return Entities\HomeKitChannel::class;
	}

	public function getType(): string
	{
		return self::SCHEMA_TYPE;
	}

}
