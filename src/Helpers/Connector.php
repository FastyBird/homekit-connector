<?php declare(strict_types = 1);

/**
 * Device.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Helpers
 * @since          1.0.0
 *
 * @date           18.11.23
 */

namespace FastyBird\Connector\HomeKit\Helpers;

use FastyBird\Connector\HomeKit;
use FastyBird\Connector\HomeKit\Documents;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Queries;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Module\Devices\Documents as DevicesDocuments;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use TypeError;
use ValueError;
use function assert;
use function is_bool;
use function is_int;
use function is_string;

/**
 * Connector helper
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Helpers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final readonly class Connector
{

	public function __construct(
		private DevicesModels\Configuration\Connectors\Properties\Repository $connectorsPropertiesConfigurationRepository,
	)
	{
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function getPort(Documents\Connectors\Connector $connector): int
	{
		$findPropertyQuery = new Queries\Configuration\FindConnectorVariableProperties();
		$findPropertyQuery->forConnector($connector);
		$findPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::PORT);

		$property = $this->connectorsPropertiesConfigurationRepository->findOneBy(
			$findPropertyQuery,
			DevicesDocuments\Connectors\Properties\Variable::class,
		);

		$value = $property?->getValue();

		if (is_int($value)) {
			return $value;
		}

		return HomeKit\Constants::DEFAULT_PORT;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function getMacAddress(Documents\Connectors\Connector $connector): string
	{
		$findPropertyQuery = new Queries\Configuration\FindConnectorVariableProperties();
		$findPropertyQuery->forConnector($connector);
		$findPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::MAC_ADDRESS);

		$property = $this->connectorsPropertiesConfigurationRepository->findOneBy(
			$findPropertyQuery,
			DevicesDocuments\Connectors\Properties\Variable::class,
		);

		$value = $property?->getValue();

		if (is_string($value)) {
			return $value;
		}

		throw new Exceptions\InvalidState('Connector MAC address is not configured');
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function getVersion(Documents\Connectors\Connector $connector): int
	{
		$findPropertyQuery = new Queries\Configuration\FindConnectorVariableProperties();
		$findPropertyQuery->forConnector($connector);
		$findPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::CONFIG_VERSION);

		$property = $this->connectorsPropertiesConfigurationRepository->findOneBy(
			$findPropertyQuery,
			DevicesDocuments\Connectors\Properties\Variable::class,
		);

		$value = $property?->getValue();

		if (is_int($value)) {
			return $value;
		}

		return 1;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function getSetupId(Documents\Connectors\Connector $connector): string
	{
		$findPropertyQuery = new Queries\Configuration\FindConnectorVariableProperties();
		$findPropertyQuery->forConnector($connector);
		$findPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::SETUP_ID);

		$property = $this->connectorsPropertiesConfigurationRepository->findOneBy(
			$findPropertyQuery,
			DevicesDocuments\Connectors\Properties\Variable::class,
		);

		$value = $property?->getValue();

		if (is_string($value)) {
			return $value;
		}

		throw new Exceptions\InvalidState('Connector setup ID is not configured');
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function getPinCode(Documents\Connectors\Connector $connector): string
	{
		$findPropertyQuery = new Queries\Configuration\FindConnectorVariableProperties();
		$findPropertyQuery->forConnector($connector);
		$findPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::PIN_CODE);

		$property = $this->connectorsPropertiesConfigurationRepository->findOneBy(
			$findPropertyQuery,
			DevicesDocuments\Connectors\Properties\Variable::class,
		);

		$value = $property?->getValue();

		if (is_string($value)) {
			return $value;
		}

		throw new Exceptions\InvalidState('Connector PIN code is not configured');
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function getXhmUri(Documents\Connectors\Connector $connector): string
	{
		$findPropertyQuery = new Queries\Configuration\FindConnectorVariableProperties();
		$findPropertyQuery->forConnector($connector);
		$findPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::XHM_URI);

		$property = $this->connectorsPropertiesConfigurationRepository->findOneBy(
			$findPropertyQuery,
			DevicesDocuments\Connectors\Properties\Variable::class,
		);

		$value = $property?->getValue();

		if (is_string($value)) {
			return $value;
		}

		throw new Exceptions\InvalidState('Connector XHM uri is not created');
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function getServerSecret(Documents\Connectors\Connector $connector): string|null
	{
		$findPropertyQuery = new Queries\Configuration\FindConnectorVariableProperties();
		$findPropertyQuery->forConnector($connector);
		$findPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::SERVER_SECRET);

		$property = $this->connectorsPropertiesConfigurationRepository->findOneBy(
			$findPropertyQuery,
			DevicesDocuments\Connectors\Properties\Variable::class,
		);

		if ($property === null) {
			return null;
		}

		$value = $property->getValue();
		assert(is_string($value) || $value === null);

		return $value;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function getHashingKey(Documents\Connectors\Connector $connector): string|null
	{
		$findPropertyQuery = new Queries\Configuration\FindConnectorVariableProperties();
		$findPropertyQuery->forConnector($connector);
		$findPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::HASHING_KEY);

		$property = $this->connectorsPropertiesConfigurationRepository->findOneBy(
			$findPropertyQuery,
			DevicesDocuments\Connectors\Properties\Variable::class,
		);

		if ($property === null) {
			return null;
		}

		$value = $property->getValue();
		assert(is_string($value) || $value === null);

		return $value;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function getClientPublicKey(Documents\Connectors\Connector $connector): string|null
	{
		$findPropertyQuery = new Queries\Configuration\FindConnectorVariableProperties();
		$findPropertyQuery->forConnector($connector);
		$findPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::CLIENT_PUBLIC_KEY);

		$property = $this->connectorsPropertiesConfigurationRepository->findOneBy(
			$findPropertyQuery,
			DevicesDocuments\Connectors\Properties\Variable::class,
		);

		if ($property === null) {
			return null;
		}

		$value = $property->getValue();
		assert(is_string($value) || $value === null);

		return $value;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function getSharedKey(Documents\Connectors\Connector $connector): string|null
	{
		$findPropertyQuery = new Queries\Configuration\FindConnectorVariableProperties();
		$findPropertyQuery->forConnector($connector);
		$findPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::SHARED_KEY);

		$property = $this->connectorsPropertiesConfigurationRepository->findOneBy(
			$findPropertyQuery,
			DevicesDocuments\Connectors\Properties\Variable::class,
		);

		if ($property === null) {
			return null;
		}

		$value = $property->getValue();
		assert(is_string($value) || $value === null);

		return $value;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function isPaired(Documents\Connectors\Connector $connector): bool
	{
		$findPropertyQuery = new Queries\Configuration\FindConnectorVariableProperties();
		$findPropertyQuery->forConnector($connector);
		$findPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::PAIRED);

		$property = $this->connectorsPropertiesConfigurationRepository->findOneBy(
			$findPropertyQuery,
			DevicesDocuments\Connectors\Properties\Variable::class,
		);

		$value = $property?->getValue();

		if (is_bool($value)) {
			return $value;
		}

		return false;
	}

}
