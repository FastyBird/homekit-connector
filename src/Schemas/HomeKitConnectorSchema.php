<?php declare(strict_types = 1);

/**
 * HomeKitConnectorSchema.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Schemas
 * @since          0.1.0
 *
 * @date           29.03.22
 */

namespace FastyBird\HomeKitConnector\Schemas;

use FastyBird\DevicesModule\Schemas as DevicesModuleSchemas;
use FastyBird\HomeKitConnector\Entities;
use FastyBird\Metadata\Types as MetadataTypes;
use Neomerx\JsonApi;

/**
 * HomeKit connector entity schema
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Schemas
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 *
 * @phpstan-extends DevicesModuleSchemas\Connectors\ConnectorSchema<Entities\IHomeKitConnector>
 */
final class HomeKitConnectorSchema extends DevicesModuleSchemas\Connectors\ConnectorSchema
{

	/**
	 * Define entity schema type string
	 */
	public const SCHEMA_TYPE = MetadataTypes\ConnectorSourceType::SOURCE_CONNECTOR_HOMEKIT . '/connector/' . Entities\HomeKitConnector::CONNECTOR_TYPE;

	/**
	 * {@inheritDoc}
	 */
	public function getEntityClass(): string
	{
		return Entities\HomeKitConnector::class;
	}

	/**
	 * @return string
	 */
	public function getType(): string
	{
		return self::SCHEMA_TYPE;
	}

	/**
	 * @param Entities\IHomeKitConnector $connector
	 * @param JsonApi\Contracts\Schema\ContextInterface $context
	 *
	 * @return iterable<string, mixed>
	 *
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 */
	public function getAttributes($connector, JsonApi\Contracts\Schema\ContextInterface $context): iterable
	{
		return array_merge((array) parent::getAttributes($connector, $context), [
			'port' => $connector->getPort(),
		]);
	}

}
