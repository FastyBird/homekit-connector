<?php declare(strict_types = 1);

/**
 * HomeKitConnector.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 * @since          0.1.0
 *
 * @date           29.03.22
 */

namespace FastyBird\HomeKitConnector\Entities;

use Doctrine\ORM\Mapping as ORM;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\Metadata\Types as MetadataTypes;

/**
 * @ORM\Entity
 */
class HomeKitConnector extends DevicesModuleEntities\Connectors\Connector implements IHomeKitConnector
{

	public const CONNECTOR_TYPE = 'homekit';

	/**
	 * {@inheritDoc}
	 */
	public function getType(): string
	{
		return self::CONNECTOR_TYPE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPort(): int
	{
		$property = $this->findProperty(MetadataTypes\ConnectorPropertyNameType::NAME_PORT);

		if (
			!$property instanceof DevicesModuleEntities\Connectors\Properties\IStaticProperty
			|| !is_numeric($property->getValue())
		) {
			return 51826;
		}

		return intval($property->getValue());
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return array_merge(parent::toArray(), [
			'port' => $this->getPort(),
		]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDiscriminatorName(): string
	{
		return self::CONNECTOR_TYPE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSource(): MetadataTypes\ModuleSourceType|MetadataTypes\PluginSourceType|MetadataTypes\ConnectorSourceType
	{
		return MetadataTypes\ConnectorSourceType::get(MetadataTypes\ConnectorSourceType::SOURCE_CONNECTOR_HOMEKIT);
	}

}
