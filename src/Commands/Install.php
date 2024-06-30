<?php declare(strict_types = 1);

/**
 * Install.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Commands
 * @since          1.0.0
 *
 * @date           15.12.23
 */

namespace FastyBird\Connector\HomeKit\Commands;

use Brick\Math;
use DateTimeInterface;
use Doctrine\DBAL;
use Exception;
use FastyBird\Connector\HomeKit;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Connector\HomeKit\Queries;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Application\Exceptions as ApplicationExceptions;
use FastyBird\Library\Application\Helpers as ApplicationHelpers;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Formats as MetadataFormats;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Library\Metadata\Utilities as MetadataUtilities;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use Nette;
use Nette\Localization;
use Nette\Utils;
use Ramsey\Uuid;
use Symfony\Component\Console;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;
use Symfony\Component\Console\Style;
use Throwable;
use TypeError;
use ValueError;
use function array_combine;
use function array_diff;
use function array_filter;
use function array_flip;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_search;
use function array_values;
use function asort;
use function assert;
use function boolval;
use function count;
use function floatval;
use function implode;
use function in_array;
use function intval;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_object;
use function is_string;
use function preg_replace;
use function sprintf;
use function str_replace;
use function strtolower;
use function strval;
use function ucwords;
use function usort;

/**
 * Connector install command
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Commands
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Install extends Console\Command\Command
{

	public const NAME = 'fb:homekit-connector:install';

	public function __construct(
		private readonly Helpers\Loader $loader,
		private readonly HomeKit\Logger $logger,
		private readonly DevicesModels\Entities\Connectors\ConnectorsRepository $connectorsRepository,
		private readonly DevicesModels\Entities\Connectors\ConnectorsManager $connectorsManager,
		private readonly DevicesModels\Entities\Connectors\Properties\PropertiesRepository $connectorsPropertiesRepository,
		private readonly DevicesModels\Entities\Connectors\Properties\PropertiesManager $connectorsPropertiesManager,
		private readonly DevicesModels\Entities\Devices\DevicesRepository $devicesRepository,
		private readonly DevicesModels\Entities\Devices\DevicesManager $devicesManager,
		private readonly DevicesModels\Entities\Devices\Properties\PropertiesRepository $devicesPropertiesRepository,
		private readonly DevicesModels\Entities\Devices\Properties\PropertiesManager $devicesPropertiesManager,
		private readonly DevicesModels\Entities\Channels\ChannelsRepository $channelsRepository,
		private readonly DevicesModels\Entities\Channels\ChannelsManager $channelsManager,
		private readonly DevicesModels\Entities\Channels\Properties\PropertiesRepository $channelsPropertiesRepository,
		private readonly DevicesModels\Entities\Channels\Properties\PropertiesManager $channelsPropertiesManager,
		private readonly ApplicationHelpers\Database $databaseHelper,
		private readonly Localization\Translator $translator,
		string|null $name = null,
	)
	{
		parent::__construct($name);
	}

	/**
	 * @throws Console\Exception\InvalidArgumentException
	 */
	protected function configure(): void
	{
		$this
			->setName(self::NAME)
			->setDescription('HomeKit connector installer');
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 * @throws TypeError
	 * @throws ValueError
	 */
	protected function execute(Input\InputInterface $input, Output\OutputInterface $output): int
	{
		$io = new Style\SymfonyStyle($input, $output);

		$io->title((string) $this->translator->translate('//homekit-connector.cmd.install.title'));

		$io->note((string) $this->translator->translate('//homekit-connector.cmd.install.subtitle'));

		$this->askInstallAction($io);

		return Console\Command\Command::SUCCESS;
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function createConnector(Style\SymfonyStyle $io): void
	{
		$question = new Console\Question\Question(
			(string) $this->translator->translate(
				'//homekit-connector.cmd.install.questions.provide.connector.identifier',
			),
		);

		$question->setValidator(function ($answer) {
			if ($answer !== null) {
				$findConnectorQuery = new Queries\Entities\FindConnectors();
				$findConnectorQuery->byIdentifier($answer);

				if ($this->connectorsRepository->findOneBy(
					$findConnectorQuery,
					Entities\Connectors\Connector::class,
				) !== null) {
					throw new Exceptions\Runtime(
						(string) $this->translator->translate(
							'//homekit-connector.cmd.install.messages.identifier.connector.used',
						),
					);
				}
			}

			return $answer;
		});

		$identifier = $io->askQuestion($question);

		if ($identifier === '' || $identifier === null) {
			$identifierPattern = 'homekit-%d';

			for ($i = 1; $i <= 100; $i++) {
				$identifier = sprintf($identifierPattern, $i);

				$findConnectorQuery = new Queries\Entities\FindConnectors();
				$findConnectorQuery->byIdentifier($identifier);

				if ($this->connectorsRepository->findOneBy(
					$findConnectorQuery,
					Entities\Connectors\Connector::class,
				) === null) {
					break;
				}
			}
		}

		if ($identifier === '') {
			$io->error(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.messages.identifier.connector.missing',
				),
			);

			return;
		}

		$name = $this->askConnectorName($io);

		$port = $this->askConnectorPort($io);

		try {
			// Start transaction connection to the database
			$this->databaseHelper->beginTransaction();

			$connector = $this->connectorsManager->create(Utils\ArrayHash::from([
				'entity' => Entities\Connectors\Connector::class,
				'identifier' => $identifier,
				'name' => $name === '' ? null : $name,
			]));
			assert($connector instanceof Entities\Connectors\Connector);

			$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
				'entity' => DevicesEntities\Connectors\Properties\Variable::class,
				'identifier' => Types\ConnectorPropertyIdentifier::PORT,
				'dataType' => MetadataTypes\DataType::UCHAR,
				'value' => $port,
				'connector' => $connector,
			]));

			$this->databaseHelper->commitTransaction();

			$io->success(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.messages.create.connector.success',
					['name' => $connector->getName() ?? $connector->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
					'type' => 'install-cmd',
					'exception' => ApplicationHelpers\Logger::buildException($ex),
				],
			);

			$io->error(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.messages.create.connector.error',
				),
			);

			return;
		} finally {
			$this->databaseHelper->clear();
		}

		$question = new Console\Question\ConfirmationQuestion(
			(string) $this->translator->translate('//homekit-connector.cmd.install.questions.create.devices'),
			true,
		);

		$createDevices = (bool) $io->askQuestion($question);

		if ($createDevices) {
			$connector = $this->connectorsRepository->find($connector->getId(), Entities\Connectors\Connector::class);
			assert($connector instanceof Entities\Connectors\Connector);

			$this->createDevice($io, $connector);
		}
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function editConnector(Style\SymfonyStyle $io): void
	{
		$connector = $this->askWhichConnector($io);

		if ($connector === null) {
			$io->warning((string) $this->translator->translate('//homekit-connector.cmd.base.messages.noConnectors'));

			$question = new Console\Question\ConfirmationQuestion(
				(string) $this->translator->translate('//homekit-connector.cmd.install.questions.create.connector'),
				false,
			);

			$continue = (bool) $io->askQuestion($question);

			if ($continue) {
				$this->createConnector($io);
			}

			return;
		}

		$name = $this->askConnectorName($io, $connector);

		$enabled = $connector->isEnabled();

		if ($connector->isEnabled()) {
			$question = new Console\Question\ConfirmationQuestion(
				(string) $this->translator->translate('//homekit-connector.cmd.install.questions.disable.connector'),
				false,
			);

			if ($io->askQuestion($question) === true) {
				$enabled = false;
			}
		} else {
			$question = new Console\Question\ConfirmationQuestion(
				(string) $this->translator->translate('//homekit-connector.cmd.install.questions.enable.connector'),
				false,
			);

			if ($io->askQuestion($question) === true) {
				$enabled = true;
			}
		}

		$port = $this->askConnectorPort($io, $connector);

		$findConnectorPropertyQuery = new Queries\Entities\FindConnectorProperties();
		$findConnectorPropertyQuery->forConnector($connector);
		$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::PORT);

		$portProperty = $this->connectorsPropertiesRepository->findOneBy($findConnectorPropertyQuery);

		try {
			// Start transaction connection to the database
			$this->databaseHelper->beginTransaction();

			$connector = $this->connectorsManager->update($connector, Utils\ArrayHash::from([
				'name' => $name === '' ? null : $name,
				'enabled' => $enabled,
			]));
			assert($connector instanceof Entities\Connectors\Connector);

			if ($portProperty === null) {
				$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::PORT,
					'dataType' => MetadataTypes\DataType::UCHAR,
					'value' => $port,
					'connector' => $connector,
				]));
			} else {
				$this->connectorsPropertiesManager->update($portProperty, Utils\ArrayHash::from([
					'value' => $port,
				]));
			}

			$this->databaseHelper->commitTransaction();

			$io->success(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.messages.update.connector.success',
					['name' => $connector->getName() ?? $connector->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
					'type' => 'install-cmd',
					'exception' => ApplicationHelpers\Logger::buildException($ex),
				],
			);

			$io->error(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.messages.update.connector.error',
				),
			);

			return;
		} finally {
			$this->databaseHelper->clear();
		}

		$question = new Console\Question\ConfirmationQuestion(
			(string) $this->translator->translate('//homekit-connector.cmd.install.questions.manage.devices'),
			false,
		);

		$manage = (bool) $io->askQuestion($question);

		if (!$manage) {
			return;
		}

		$connector = $this->connectorsRepository->find($connector->getId(), Entities\Connectors\Connector::class);
		assert($connector instanceof Entities\Connectors\Connector);

		$this->askManageConnectorAction($io, $connector);
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DevicesExceptions\InvalidState
	 */
	private function deleteConnector(Style\SymfonyStyle $io): void
	{
		$connector = $this->askWhichConnector($io);

		if ($connector === null) {
			$io->info((string) $this->translator->translate('//homekit-connector.cmd.base.messages.noConnectors'));

			return;
		}

		$io->warning(
			(string) $this->translator->translate(
				'//homekit-connector.cmd.install.messages.remove.connector.confirm',
				['name' => $connector->getName() ?? $connector->getIdentifier()],
			),
		);

		$question = new Console\Question\ConfirmationQuestion(
			(string) $this->translator->translate('//homekit-connector.cmd.base.questions.continue'),
			false,
		);

		$continue = (bool) $io->askQuestion($question);

		if (!$continue) {
			return;
		}

		try {
			// Start transaction connection to the database
			$this->databaseHelper->beginTransaction();

			$this->connectorsManager->delete($connector);

			$this->databaseHelper->commitTransaction();

			$io->success(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.messages.remove.connector.success',
					['name' => $connector->getName() ?? $connector->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
					'type' => 'install-cmd',
					'exception' => ApplicationHelpers\Logger::buildException($ex),
				],
			);

			$io->error(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.messages.remove.connector.error',
				),
			);
		} finally {
			$this->databaseHelper->clear();
		}
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function manageConnector(Style\SymfonyStyle $io): void
	{
		$connector = $this->askWhichConnector($io);

		if ($connector === null) {
			$io->info((string) $this->translator->translate('//homekit-connector.cmd.base.messages.noConnectors'));

			return;
		}

		$this->askManageConnectorAction($io, $connector);
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 */
	private function listConnectors(Style\SymfonyStyle $io): void
	{
		$findConnectorsQuery = new Queries\Entities\FindConnectors();

		$connectors = $this->connectorsRepository->findAllBy(
			$findConnectorsQuery,
			Entities\Connectors\Connector::class,
		);
		usort(
			$connectors,
			static fn (Entities\Connectors\Connector $a, Entities\Connectors\Connector $b): int => (
				($a->getName() ?? $a->getIdentifier()) <=> ($b->getName() ?? $b->getIdentifier())
			),
		);

		$table = new Console\Helper\Table($io);
		$table->setHeaders([
			'#',
			(string) $this->translator->translate('//homekit-connector.cmd.install.data.name'),
			(string) $this->translator->translate('//homekit-connector.cmd.install.data.devicesCnt'),
		]);

		foreach ($connectors as $index => $connector) {
			$findDevicesQuery = new Queries\Entities\FindDevices();
			$findDevicesQuery->forConnector($connector);

			$devices = $this->devicesRepository->findAllBy($findDevicesQuery, Entities\Devices\Device::class);

			$table->addRow([
				$index + 1,
				$connector->getName() ?? $connector->getIdentifier(),
				count($devices),
			]);
		}

		$table->render();

		$io->newLine();
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function createDevice(Style\SymfonyStyle $io, Entities\Connectors\Connector $connector): void
	{
		$question = new Console\Question\Question(
			(string) $this->translator->translate(
				'//homekit-connector.cmd.install.questions.provide.device.identifier',
			),
		);

		$question->setValidator(function (string|null $answer) {
			if ($answer !== '' && $answer !== null) {
				$findDeviceQuery = new Queries\Entities\FindDevices();
				$findDeviceQuery->byIdentifier($answer);

				if (
					$this->devicesRepository->findOneBy($findDeviceQuery, Entities\Devices\Device::class) !== null
				) {
					throw new Exceptions\Runtime(
						(string) $this->translator->translate(
							'//homekit-connector.cmd.install.messages.identifier.device.used',
						),
					);
				}
			}

			return $answer;
		});

		$identifier = $io->askQuestion($question);

		if ($identifier === '' || $identifier === null) {
			$identifierPattern = 'homekit-%d';

			for ($i = 1; $i <= 100; $i++) {
				$identifier = sprintf($identifierPattern, $i);

				$findDeviceQuery = new Queries\Entities\FindDevices();
				$findDeviceQuery->byIdentifier($identifier);

				if (
					$this->devicesRepository->findOneBy($findDeviceQuery, Entities\Devices\Device::class) === null
				) {
					break;
				}
			}
		}

		if ($identifier === '') {
			$io->error(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.messages.identifier.device.missing',
				),
			);

			return;
		}

		$name = $this->askDeviceName($io);

		$category = $this->askDeviceCategory($io);

		try {
			// Start transaction connection to the database
			$this->databaseHelper->beginTransaction();

			$device = $this->devicesManager->create(Utils\ArrayHash::from([
				'entity' => Entities\Devices\Device::class,
				'connector' => $connector,
				'identifier' => $identifier,
				'name' => $name,
			]));
			assert($device instanceof Entities\Devices\Device);

			$this->devicesPropertiesManager->create(Utils\ArrayHash::from([
				'entity' => DevicesEntities\Devices\Properties\Variable::class,
				'identifier' => Types\DevicePropertyIdentifier::CATEGORY->value,
				'dataType' => MetadataTypes\DataType::UCHAR,
				'value' => $category->value,
				'device' => $device,
			]));

			$this->databaseHelper->commitTransaction();

			$io->success(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.messages.create.device.success',
					['name' => $device->getName() ?? $device->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
					'type' => 'install-cmd',
					'exception' => ApplicationHelpers\Logger::buildException($ex),
				],
			);

			$io->error(
				(string) $this->translator->translate('//homekit-connector.cmd.install.messages.create.device.error'),
			);

			return;
		} finally {
			$this->databaseHelper->clear();
		}

		$device = $this->devicesRepository->find($device->getId(), Entities\Devices\Device::class);
		assert($device instanceof Entities\Devices\Device);

		$this->createService($io, $device);
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function editDevice(Style\SymfonyStyle $io, Entities\Connectors\Connector $connector): void
	{
		$device = $this->askWhichDevice($io, $connector);

		if ($device === null) {
			$io->warning((string) $this->translator->translate('//homekit-connector.cmd.install.messages.noDevices'));

			$question = new Console\Question\ConfirmationQuestion(
				(string) $this->translator->translate('//homekit-connector.cmd.install.questions.create.device'),
				false,
			);

			$continue = (bool) $io->askQuestion($question);

			if ($continue) {
				$this->createDevice($io, $connector);
			}

			return;
		}

		$name = $this->askDeviceName($io, $device);

		$findDevicePropertyQuery = new Queries\Entities\FindDeviceProperties();
		$findDevicePropertyQuery->forDevice($device);
		$findDevicePropertyQuery->byIdentifier(Types\DevicePropertyIdentifier::CATEGORY);

		$categoryProperty = $this->devicesPropertiesRepository->findOneBy($findDevicePropertyQuery);

		$category = $this->askDeviceCategory($io, $device);

		try {
			// Start transaction connection to the database
			$this->databaseHelper->beginTransaction();

			$device = $this->devicesManager->update($device, Utils\ArrayHash::from([
				'name' => $name,
			]));
			assert($device instanceof Entities\Devices\Device);

			if ($categoryProperty === null) {
				$this->devicesPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Devices\Properties\Variable::class,
					'identifier' => Types\DevicePropertyIdentifier::CATEGORY->value,
					'dataType' => MetadataTypes\DataType::UCHAR,
					'value' => $category->value,
					'device' => $device,
				]));
			} elseif ($categoryProperty instanceof DevicesEntities\Devices\Properties\Variable) {
				$this->devicesPropertiesManager->update($categoryProperty, Utils\ArrayHash::from([
					'value' => $category->value,
				]));
			}

			$this->databaseHelper->commitTransaction();

			$io->success(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.messages.update.device.success',
					['name' => $device->getName() ?? $device->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
					'type' => 'install-cmd',
					'exception' => ApplicationHelpers\Logger::buildException($ex),
				],
			);

			$io->error(
				(string) $this->translator->translate('//homekit-connector.cmd.install.messages.update.device.error'),
			);

			return;
		} finally {
			$this->databaseHelper->clear();
		}

		$question = new Console\Question\ConfirmationQuestion(
			(string) $this->translator->translate('//homekit-connector.cmd.install.questions.manage.services'),
			false,
		);

		$manage = (bool) $io->askQuestion($question);

		if (!$manage) {
			return;
		}

		$device = $this->devicesRepository->find($device->getId(), Entities\Devices\Device::class);
		assert($device instanceof Entities\Devices\Device);

		$this->askManageDeviceAction($io, $device);
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DevicesExceptions\InvalidState
	 */
	private function deleteDevice(Style\SymfonyStyle $io, Entities\Connectors\Connector $connector): void
	{
		$device = $this->askWhichDevice($io, $connector);

		if ($device === null) {
			$io->info((string) $this->translator->translate('//homekit-connector.cmd.install.messages.noDevices'));

			return;
		}

		$io->warning(
			(string) $this->translator->translate(
				'//homekit-connector.cmd.install.messages.remove.device.confirm',
				['name' => $device->getName() ?? $device->getIdentifier()],
			),
		);

		$question = new Console\Question\ConfirmationQuestion(
			(string) $this->translator->translate('//homekit-connector.cmd.base.questions.continue'),
			false,
		);

		$continue = (bool) $io->askQuestion($question);

		if (!$continue) {
			return;
		}

		try {
			// Start transaction connection to the database
			$this->databaseHelper->beginTransaction();

			$this->devicesManager->delete($device);

			$this->databaseHelper->commitTransaction();

			$io->success(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.messages.remove.device.success',
					['name' => $device->getName() ?? $device->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
					'type' => 'install-cmd',
					'exception' => ApplicationHelpers\Logger::buildException($ex),
				],
			);

			$io->error(
				(string) $this->translator->translate('//homekit-connector.cmd.install.messages.remove.device.error'),
			);
		} finally {
			$this->databaseHelper->clear();
		}
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function manageDevice(Style\SymfonyStyle $io, Entities\Connectors\Connector $connector): void
	{
		$device = $this->askWhichDevice($io, $connector);

		if ($device === null) {
			$io->info((string) $this->translator->translate('//homekit-connector.cmd.install.messages.noDevices'));

			return;
		}

		$this->askManageDeviceAction($io, $device);
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function listDevices(Style\SymfonyStyle $io, Entities\Connectors\Connector $connector): void
	{
		$findDevicesQuery = new Queries\Entities\FindDevices();
		$findDevicesQuery->forConnector($connector);

		$devices = $this->devicesRepository->findAllBy($findDevicesQuery, Entities\Devices\Device::class);
		usort(
			$devices,
			static fn (Entities\Devices\Device $a, Entities\Devices\Device $b): int => (
				($a->getName() ?? $a->getIdentifier()) <=> ($b->getName() ?? $b->getIdentifier())
			),
		);

		$table = new Console\Helper\Table($io);
		$table->setHeaders([
			'#',
			(string) $this->translator->translate('//homekit-connector.cmd.install.data.name'),
			(string) $this->translator->translate('//homekit-connector.cmd.install.data.category'),
		]);

		foreach ($devices as $index => $device) {
			$table->addRow([
				$index + 1,
				$device->getName() ?? $device->getIdentifier(),
				(string) $this->translator->translate(
					'//homekit-connector.cmd.base.category.' . $device->getAccessoryCategory()->value,
				),
			]);
		}

		$table->render();

		$io->newLine();
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function createService(Style\SymfonyStyle $io, Entities\Devices\Device $device): void
	{
		$type = $this->askServiceType($io, $device);

		$identifier = strtolower(strval(preg_replace('/(?<!^)[A-Z]/', '_$0', $type)));

		$identifierPattern = $identifier . '_%d';

		for ($i = 1; $i <= 100; $i++) {
			$identifier = sprintf($identifierPattern, $i);

			$findChannelQuery = new Queries\Entities\FindChannels();
			$findChannelQuery->forDevice($device);
			$findChannelQuery->byIdentifier($identifier);

			$channel = $this->channelsRepository->findOneBy($findChannelQuery, Entities\Channels\Channel::class);

			if ($channel === null) {
				break;
			}
		}

		$metadata = $this->loader->loadServices();

		if (!$metadata->offsetExists($type)) {
			throw new Exceptions\InvalidArgument(sprintf(
				'Definition for service: %s was not found',
				$type,
			));
		}

		$serviceMetadata = $metadata->offsetGet($type);

		if (
			!$serviceMetadata instanceof Utils\ArrayHash
			|| !$serviceMetadata->offsetExists('UUID')
			|| !is_string($serviceMetadata->offsetGet('UUID'))
			|| !$serviceMetadata->offsetExists('RequiredCharacteristics')
			|| !$serviceMetadata->offsetGet('RequiredCharacteristics') instanceof Utils\ArrayHash
		) {
			throw new Exceptions\InvalidState('Service definition is missing required attributes');
		}

		$requiredCharacteristics = (array) $serviceMetadata->offsetGet('RequiredCharacteristics');
		$optionalCharacteristics = $virtualCharacteristics = [];

		if (
			$serviceMetadata->offsetExists('OptionalCharacteristics')
			&& $serviceMetadata->offsetGet('OptionalCharacteristics') instanceof Utils\ArrayHash
		) {
			$optionalCharacteristics = (array) $serviceMetadata->offsetGet('OptionalCharacteristics');
		}

		if (
			$serviceMetadata->offsetExists('VirtualCharacteristics')
			&& $serviceMetadata->offsetGet('VirtualCharacteristics') instanceof Utils\ArrayHash
		) {
			$virtualCharacteristics = (array) $serviceMetadata->offsetGet('VirtualCharacteristics');
		}

		try {
			// Start transaction connection to the database
			$this->databaseHelper->beginTransaction();

			$channel = $this->channelsManager->create(Utils\ArrayHash::from([
				'entity' => Entities\Channels\Channel::class,
				'identifier' => $identifier,
				'device' => $device,
			]));
			assert($channel instanceof Entities\Channels\Channel);

			$this->createCharacteristics($io, $channel, $requiredCharacteristics, true);

			$this->createCharacteristics(
				$io,
				$channel,
				array_merge($optionalCharacteristics, $virtualCharacteristics),
				false,
			);

			$this->databaseHelper->commitTransaction();

			$io->success(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.messages.create.service.success',
					['name' => $channel->getName() ?? $channel->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
					'type' => 'install-cmd',
					'exception' => ApplicationHelpers\Logger::buildException($ex),
				],
			);

			$io->error(
				(string) $this->translator->translate('//homekit-connector.cmd.install.messages.create.service.error'),
			);

			return;
		} finally {
			$this->databaseHelper->clear();
		}

		$question = new Console\Question\ConfirmationQuestion(
			(string) $this->translator->translate('//homekit-connector.cmd.install.questions.manage.characteristics'),
			false,
		);

		$manage = (bool) $io->askQuestion($question);

		if (!$manage) {
			return;
		}

		$channel = $this->channelsRepository->find($channel->getId(), Entities\Channels\Channel::class);
		assert($channel instanceof Entities\Channels\Channel);

		$this->askManageServiceAction($io, $channel);
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function editService(Style\SymfonyStyle $io, Entities\Devices\Device $device): void
	{
		$channels = $this->getServicesList($device);

		if (count($channels) === 0) {
			$io->warning((string) $this->translator->translate('//homekit-connector.cmd.install.messages.noServices'));

			$question = new Console\Question\ConfirmationQuestion(
				(string) $this->translator->translate('//homekit-connector.cmd.install.questions.create.service'),
				false,
			);

			$continue = (bool) $io->askQuestion($question);

			if ($continue) {
				$this->createService($io, $device);
			}

			return;
		}

		$channel = $this->askWhichService($io, $device, $channels);

		if ($channel === null) {
			return;
		}

		$type = $channel->getServiceType();

		$metadata = $this->loader->loadServices();

		if (!$metadata->offsetExists($type->value)) {
			throw new Exceptions\InvalidArgument(sprintf(
				'Definition for service: %s was not found',
				$type->value,
			));
		}

		$serviceMetadata = $metadata->offsetGet($type->value);

		if (
			!$serviceMetadata instanceof Utils\ArrayHash
			|| !$serviceMetadata->offsetExists('UUID')
			|| !is_string($serviceMetadata->offsetGet('UUID'))
			|| !$serviceMetadata->offsetExists('RequiredCharacteristics')
			|| !$serviceMetadata->offsetGet('RequiredCharacteristics') instanceof Utils\ArrayHash
		) {
			throw new Exceptions\InvalidState('Service definition is missing required attributes');
		}

		$requiredCharacteristics = (array) $serviceMetadata->offsetGet('RequiredCharacteristics');
		$optionalCharacteristics = $virtualCharacteristics = [];

		if (
			$serviceMetadata->offsetExists('OptionalCharacteristics')
			&& $serviceMetadata->offsetGet('OptionalCharacteristics') instanceof Utils\ArrayHash
		) {
			$optionalCharacteristics = (array) $serviceMetadata->offsetGet('OptionalCharacteristics');
		}

		if (
			$serviceMetadata->offsetExists('VirtualCharacteristics')
			&& $serviceMetadata->offsetGet('VirtualCharacteristics') instanceof Utils\ArrayHash
		) {
			$virtualCharacteristics = (array) $serviceMetadata->offsetGet('VirtualCharacteristics');
		}

		$missingRequired = [];

		foreach ($requiredCharacteristics as $requiredCharacteristic) {
			$findPropertyQuery = new DevicesQueries\Entities\FindChannelProperties();
			$findPropertyQuery->forChannel($channel);
			$findPropertyQuery->byIdentifier(
				strtolower(strval(preg_replace('/(?<!^)[A-Z]/', '_$0', $requiredCharacteristic))),
			);

			$property = $this->channelsPropertiesRepository->findOneBy($findPropertyQuery);

			if ($property === null) {
				$missingRequired[] = $requiredCharacteristic;
			}
		}

		$missingOptional = [];

		foreach (array_merge($optionalCharacteristics, $virtualCharacteristics) as $optionalCharacteristic) {
			$findPropertyQuery = new DevicesQueries\Entities\FindChannelProperties();
			$findPropertyQuery->forChannel($channel);
			$findPropertyQuery->byIdentifier(
				strtolower(strval(preg_replace('/(?<!^)[A-Z]/', '_$0', $optionalCharacteristic))),
			);

			$property = $this->channelsPropertiesRepository->findOneBy($findPropertyQuery);

			if ($property === null) {
				$missingOptional[] = $optionalCharacteristic;
			}
		}

		try {
			// Start transaction connection to the database
			$this->databaseHelper->beginTransaction();

			if (count($missingRequired) > 0) {
				$this->createCharacteristics($io, $channel, $missingRequired, true);
			}

			if (count($missingOptional) > 0) {
				$question = new Console\Question\ConfirmationQuestion(
					(string) $this->translator->translate(
						'//homekit-connector.cmd.install.questions.addCharacteristics',
					),
					false,
				);

				$add = (bool) $io->askQuestion($question);

				if ($add) {
					$this->createCharacteristics($io, $channel, $missingOptional, false);
				}
			}

			$this->databaseHelper->commitTransaction();

			$io->success(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.messages.update.service.success',
					['name' => $channel->getName() ?? $channel->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
					'type' => 'install-cmd',
					'exception' => ApplicationHelpers\Logger::buildException($ex),
				],
			);

			$io->success(
				(string) $this->translator->translate('//homekit-connector.cmd.install.messages.update.service.error'),
			);

			return;
		} finally {
			$this->databaseHelper->clear();
		}

		$question = new Console\Question\ConfirmationQuestion(
			(string) $this->translator->translate('//homekit-connector.cmd.install.questions.manage.characteristics'),
			false,
		);

		$manage = (bool) $io->askQuestion($question);

		if (!$manage) {
			return;
		}

		$channel = $this->channelsRepository->find($channel->getId(), Entities\Channels\Channel::class);
		assert($channel instanceof Entities\Channels\Channel);

		$this->askManageServiceAction($io, $channel);
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DevicesExceptions\InvalidState
	 */
	private function deleteService(Style\SymfonyStyle $io, Entities\Devices\Device $device): void
	{
		$channels = $this->getServicesList($device);

		if (count($channels) === 0) {
			$io->warning((string) $this->translator->translate('//homekit-connector.cmd.install.messages.noServices'));

			return;
		}

		$channel = $this->askWhichService($io, $device, $channels);

		if ($channel === null) {
			return;
		}

		$io->warning(
			(string) $this->translator->translate(
				'//homekit-connector.cmd.install.messages.remove.service.confirm',
				['name' => $channel->getName() ?? $channel->getIdentifier()],
			),
		);

		$question = new Console\Question\ConfirmationQuestion(
			(string) $this->translator->translate('//homekit-connector.cmd.base.questions.continue'),
			false,
		);

		$continue = (bool) $io->askQuestion($question);

		if (!$continue) {
			return;
		}

		try {
			// Start transaction connection to the database
			$this->databaseHelper->beginTransaction();

			$this->channelsManager->delete($channel);

			$this->databaseHelper->commitTransaction();

			$io->success(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.messages.remove.service.success',
					['name' => $channel->getName() ?? $channel->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
					'type' => 'install-cmd',
					'exception' => ApplicationHelpers\Logger::buildException($ex),
				],
			);

			$io->success(
				(string) $this->translator->translate('//homekit-connector.cmd.install.messages.remove.service.error'),
			);
		} finally {
			$this->databaseHelper->clear();
		}
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function manageService(Style\SymfonyStyle $io, Entities\Devices\Device $device): void
	{
		$channels = $this->getServicesList($device);

		if (count($channels) === 0) {
			$io->warning((string) $this->translator->translate('//homekit-connector.cmd.install.messages.noServices'));

			return;
		}

		$channel = $this->askWhichService($io, $device, $channels);

		if ($channel === null) {
			$io->info((string) $this->translator->translate('//homekit-connector.cmd.install.messages.noServices'));

			return;
		}

		$this->askManageServiceAction($io, $channel);
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function listServices(Style\SymfonyStyle $io, Entities\Devices\Device $device): void
	{
		$findChannelsQuery = new Queries\Entities\FindChannels();
		$findChannelsQuery->forDevice($device);

		$deviceChannels = $this->channelsRepository->findAllBy($findChannelsQuery, Entities\Channels\Channel::class);
		usort(
			$deviceChannels,
			static fn (DevicesEntities\Channels\Channel $a, DevicesEntities\Channels\Channel $b): int => (
				($a->getName() ?? $a->getIdentifier()) <=> ($b->getName() ?? $b->getIdentifier())
			),
		);

		$table = new Console\Helper\Table($io);
		$table->setHeaders([
			'#',
			'Name',
			'Type',
			'Characteristics',
		]);

		foreach ($deviceChannels as $index => $channel) {
			$findChannelPropertiesQuery = new DevicesQueries\Entities\FindChannelProperties();
			$findChannelPropertiesQuery->forChannel($channel);

			$table->addRow([
				$index + 1,
				$channel->getName() ?? $channel->getIdentifier(),
				$channel->getServiceType()->value,
				implode(
					', ',
					array_map(
						static fn (DevicesEntities\Channels\Properties\Property $property): string => str_replace(
							' ',
							'',
							ucwords(str_replace('_', ' ', $property->getIdentifier())),
						),
						$this->channelsPropertiesRepository->findAllBy($findChannelPropertiesQuery),
					),
				),
			]);
		}

		$table->render();

		$io->newLine();
	}

	/**
	 * @param array<string> $characteristics
	 *
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exception
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function createCharacteristics(
		Style\SymfonyStyle $io,
		Entities\Channels\Channel $channel,
		array $characteristics,
		bool $required,
	): void
	{
		$metadata = $this->loader->loadCharacteristics();

		$createdCharacteristics = [];

		while (count(array_diff($characteristics, $createdCharacteristics)) > 0) {
			$characteristic = $this->askCharacteristic(
				$io,
				$channel->getServiceType(),
				$required,
				$characteristics,
				$createdCharacteristics,
			);

			if ($characteristic === null) {
				break;
			}

			$characteristicMetadata = $metadata->offsetGet($characteristic);

			if (
				!$characteristicMetadata instanceof Utils\ArrayHash
				|| !$characteristicMetadata->offsetExists('Format')
				|| !is_string($characteristicMetadata->offsetGet('Format'))
				|| !$characteristicMetadata->offsetExists('DataType')
				|| (
					!is_string($characteristicMetadata->offsetGet('DataType'))
					&& !$characteristicMetadata->offsetGet('DataType') instanceof Utils\ArrayHash
				)
				|| !$characteristicMetadata->offsetExists('Permissions')
				|| !$characteristicMetadata->offsetGet('Permissions') instanceof Utils\ArrayHash
			) {
				throw new Exceptions\InvalidState('Characteristic definition is missing required attributes');
			}

			$permissions = (array) $characteristicMetadata->offsetGet('Permissions');

			if ($characteristicMetadata->offsetGet('DataType') instanceof Utils\ArrayHash) {
				$dataTypes = array_map(
					static fn (string $type): MetadataTypes\DataType => MetadataTypes\DataType::from($type),
					(array) $characteristicMetadata->offsetGet('DataType'),
				);

				if ($dataTypes === []) {
					throw new Exceptions\InvalidState('Characteristic definition is missing required attributes');
				}

				$dataType = $dataTypes[0];
			} else {
				$dataType = MetadataTypes\DataType::from($characteristicMetadata->offsetGet('DataType'));
			}

			$format = $this->askFormat($io, $characteristic);

			$question = new Console\Question\ConfirmationQuestion(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.questions.connectCharacteristic',
				),
				true,
			);

			$connect = (bool) $io->askQuestion($question);

			if ($connect) {
				$connectProperty = $this->askProperty(
					$io,
					null,
					in_array(Types\CharacteristicPermission::WRITE->value, $permissions, true),
				);

				$format = $this->askFormat($io, $characteristic, $connectProperty);

				if (
					$connectProperty !== null
					&& in_array($connectProperty->getDataType(), $dataTypes ?? [], true)
				) {
					$dataType = $connectProperty->getDataType();
					$format = $connectProperty->getFormat();
				}

				if (
					(
						$dataType === MetadataTypes\DataType::BOOLEAN
						|| in_array(
							MetadataTypes\DataType::BOOLEAN,
							$dataTypes ?? [],
							true,
						)
					)
					&& $connectProperty !== null
					&& $connectProperty->getDataType() === MetadataTypes\DataType::SWITCH
				) {
					$dataType = MetadataTypes\DataType::SWITCH;

					$format = [
						[
							MetadataTypes\Payloads\Switcher::ON->value,
							[
								MetadataTypes\DataTypeShort::BOOLEAN->value,
								'true',
							],
							[
								MetadataTypes\DataTypeShort::BOOLEAN->value,
								'true',
							],
						],
						[
							MetadataTypes\Payloads\Switcher::OFF->value,
							[
								MetadataTypes\DataTypeShort::BOOLEAN->value,
								'false',
							],
							[
								MetadataTypes\DataTypeShort::BOOLEAN->value,
								'false',
							],
						],
					];
				}

				$this->channelsPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Channels\Properties\Mapped::class,
					'parent' => $connectProperty,
					'identifier' => strtolower(strval(preg_replace('/(?<!^)[A-Z]/', '_$0', $characteristic))),
					'channel' => $channel,
					'dataType' => $dataType,
					'format' => $format,
					'settable' => $connectProperty?->isSettable(),
					'queryable' => $connectProperty?->isQueryable(),
				]));
			} else {
				$value = $this->provideCharacteristicValue($io, $characteristic);

				$this->channelsPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Channels\Properties\Variable::class,
					'identifier' => strtolower(strval(preg_replace('/(?<!^)[A-Z]/', '_$0', $characteristic))),
					'channel' => $channel,
					'dataType' => $dataType,
					'format' => $format,
					'value' => $value,
				]));
			}

			$createdCharacteristics[] = $characteristic;

			if (!$required && count(array_diff($characteristics, $createdCharacteristics)) > 0) {
				$question = new Console\Question\ConfirmationQuestion(
					(string) $this->translator->translate('//homekit-connector.cmd.base.questions.continue'),
					false,
				);

				$continue = (bool) $io->askQuestion($question);

				if (!$continue) {
					break;
				}
			}
		}
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Nette\IOException
	 */
	private function editCharacteristic(Style\SymfonyStyle $io, Entities\Channels\Channel $channel): void
	{
		$properties = $this->getCharacteristicsList($channel);

		if (count($properties) === 0) {
			$io->warning(
				(string) $this->translator->translate('//homekit-connector.cmd.install.messages.noCharacteristics'),
			);

			return;
		}

		$property = $this->askWhichCharacteristic($io, $channel, $properties);

		if ($property === null) {
			return;
		}

		$type = str_replace(' ', '', ucwords(str_replace('_', ' ', $property->getIdentifier())));

		$metadata = $this->loader->loadCharacteristics();

		if (!$metadata->offsetExists($type)) {
			throw new Exceptions\InvalidArgument(sprintf(
				'Definition for characteristic: %s was not found',
				$type,
			));
		}

		$characteristicMetadata = $metadata->offsetGet($type);

		if (
			!$characteristicMetadata instanceof Utils\ArrayHash
			|| !$characteristicMetadata->offsetExists('UUID')
			|| !is_string($characteristicMetadata->offsetGet('UUID'))
			|| !$characteristicMetadata->offsetExists('Format')
			|| (
				!is_string($characteristicMetadata->offsetGet('DataType'))
				&& !$characteristicMetadata->offsetGet('DataType') instanceof Utils\ArrayHash
			)
			|| !$characteristicMetadata->offsetExists('Permissions')
			|| !$characteristicMetadata->offsetGet('Permissions') instanceof Utils\ArrayHash
		) {
			throw new Exceptions\InvalidState('Characteristic definition is missing required attributes');
		}

		try {
			// Start transaction connection to the database
			$this->databaseHelper->beginTransaction();

			$permissions = (array) $characteristicMetadata->offsetGet('Permissions');

			if ($characteristicMetadata->offsetGet('DataType') instanceof Utils\ArrayHash) {
				$dataTypes = array_map(
					static fn (string $type): MetadataTypes\DataType => MetadataTypes\DataType::from($type),
					(array) $characteristicMetadata->offsetGet('DataType'),
				);

				if ($dataTypes === []) {
					throw new Exceptions\InvalidState('Characteristic definition is missing required attributes');
				}

				$dataType = $dataTypes[0];
			} else {
				$dataType = MetadataTypes\DataType::from($characteristicMetadata->offsetGet('DataType'));
			}

			$format = $this->askFormat($io, $type);

			$question = new Console\Question\ConfirmationQuestion(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.questions.connectCharacteristic',
				),
				$property instanceof DevicesEntities\Channels\Properties\Mapped,
			);

			$connect = (bool) $io->askQuestion($question);

			if ($connect) {
				$connectProperty = $this->askProperty(
					$io,
					(
						$property instanceof DevicesEntities\Channels\Properties\Mapped
						&& $property->getParent() instanceof DevicesEntities\Channels\Properties\Dynamic
							? $property->getParent()
							: null
					),
					in_array(Types\CharacteristicPermission::WRITE->value, $permissions, true),
				);

				$format = $this->askFormat($io, $type, $connectProperty);

				if (
					$connectProperty !== null
					&& in_array($connectProperty->getDataType(), $dataTypes ?? [], true)
				) {
					$dataType = $connectProperty->getDataType();
					$format = $connectProperty->getFormat();
				}

				if (
					(
						$dataType === MetadataTypes\DataType::BOOLEAN
						|| in_array(
							MetadataTypes\DataType::BOOLEAN,
							$dataTypes ?? [],
							true,
						)
					)
					&& $connectProperty !== null
					&& $connectProperty->getDataType() === MetadataTypes\DataType::SWITCH
				) {
					$dataType = MetadataTypes\DataType::SWITCH;

					$format = [
						[
							MetadataTypes\Payloads\Switcher::ON->value,
							[
								MetadataTypes\DataTypeShort::BOOLEAN->value,
								'true',
							],
							[
								MetadataTypes\DataTypeShort::BOOLEAN->value,
								'true',
							],
						],
						[
							MetadataTypes\Payloads\Switcher::OFF->value,
							[
								MetadataTypes\DataTypeShort::BOOLEAN->value,
								'false',
							],
							[
								MetadataTypes\DataTypeShort::BOOLEAN->value,
								'false',
							],
						],
					];
				}

				if (
					$property instanceof DevicesEntities\Channels\Properties\Mapped
					&& $connectProperty instanceof DevicesEntities\Channels\Properties\Dynamic
				) {
					$this->channelsPropertiesManager->update($property, Utils\ArrayHash::from([
						'parent' => $connectProperty,
						'format' => $format,
					]));
				} else {
					$this->channelsPropertiesManager->delete($property);

					$property = $this->channelsPropertiesManager->create(Utils\ArrayHash::from([
						'entity' => DevicesEntities\Channels\Properties\Mapped::class,
						'parent' => $connectProperty,
						'identifier' => $property->getIdentifier(),
						'channel' => $channel,
						'dataType' => $dataType,
						'format' => $format,
						'settable' => $connectProperty?->isSettable(),
						'queryable' => $connectProperty?->isQueryable(),
					]));
				}
			} else {
				$value = $this->provideCharacteristicValue(
					$io,
					$type,
					$property instanceof DevicesEntities\Channels\Properties\Variable ? $property->getValue() : null,
				);

				if ($property instanceof DevicesEntities\Channels\Properties\Variable) {
					$this->channelsPropertiesManager->update($property, Utils\ArrayHash::from([
						'value' => $value,
						'format' => $format,
					]));
				} else {
					$this->channelsPropertiesManager->delete($property);

					$property = $this->channelsPropertiesManager->create(Utils\ArrayHash::from([
						'entity' => DevicesEntities\Channels\Properties\Variable::class,
						'identifier' => $property->getIdentifier(),
						'channel' => $channel,
						'dataType' => $dataType,
						'format' => $format,
						'value' => $value,
					]));
				}
			}

			$this->databaseHelper->commitTransaction();

			$io->success(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.messages.update.characteristic.success',
					['name' => $property->getName() ?? $property->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
					'type' => 'install-cmd',
					'exception' => ApplicationHelpers\Logger::buildException($ex),
				],
			);

			$io->success(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.messages.update.characteristic.error',
				),
			);
		} finally {
			$this->databaseHelper->clear();
		}
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DevicesExceptions\InvalidState
	 */
	private function deleteCharacteristic(Style\SymfonyStyle $io, Entities\Channels\Channel $channel): void
	{
		$properties = $this->getCharacteristicsList($channel);

		if (count($properties) === 0) {
			$io->warning(
				(string) $this->translator->translate('//homekit-connector.cmd.install.messages.noCharacteristics'),
			);

			return;
		}

		$property = $this->askWhichCharacteristic($io, $channel, $properties);

		if ($property === null) {
			return;
		}

		$io->warning(
			(string) $this->translator->translate(
				'//homekit-connector.cmd.install.messages.remove.characteristic.confirm',
				['name' => $property->getName() ?? $property->getIdentifier()],
			),
		);

		$question = new Console\Question\ConfirmationQuestion(
			(string) $this->translator->translate('//homekit-connector.cmd.base.questions.continue'),
			false,
		);

		$continue = (bool) $io->askQuestion($question);

		if (!$continue) {
			return;
		}

		try {
			// Start transaction connection to the database
			$this->databaseHelper->beginTransaction();

			$this->channelsPropertiesManager->delete($property);

			$this->databaseHelper->commitTransaction();

			$io->success(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.messages.remove.characteristic.success',
					['name' => $property->getName() ?? $property->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
					'type' => 'install-cmd',
					'exception' => ApplicationHelpers\Logger::buildException($ex),
				],
			);

			$io->success(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.messages.remove.characteristic.error',
				),
			);
		} finally {
			$this->databaseHelper->clear();
		}
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function listCharacteristics(Style\SymfonyStyle $io, Entities\Channels\Channel $channel): void
	{
		$findPropertiesQuery = new DevicesQueries\Entities\FindChannelProperties();
		$findPropertiesQuery->forChannel($channel);

		$channelProperties = $this->channelsPropertiesRepository->findAllBy($findPropertiesQuery);
		usort(
			$channelProperties,
			static fn (DevicesEntities\Channels\Properties\Property $a, DevicesEntities\Channels\Properties\Property $b): int => (
				($a->getName() ?? $a->getIdentifier()) <=> ($b->getName() ?? $b->getIdentifier())
			),
		);

		$table = new Console\Helper\Table($io);
		$table->setHeaders([
			'#',
			'Name',
			'Type',
			'Value',
		]);

		$metadata = $this->loader->loadCharacteristics();

		foreach ($channelProperties as $index => $property) {
			$type = str_replace(' ', '', ucwords(str_replace('_', ' ', $property->getIdentifier())));

			$value = $property instanceof DevicesEntities\Channels\Properties\Variable ? $property->getValue() : 'N/A';

			if (
				$property->getDataType() === MetadataTypes\DataType::ENUM
				&& $metadata->offsetExists($type)
				&& $metadata->offsetGet($type) instanceof Utils\ArrayHash
				&& $metadata->offsetGet($type)->offsetExists('ValidValues')
				&& $metadata->offsetGet($type)->offsetGet('ValidValues') instanceof Utils\ArrayHash
			) {
				$enumValue = array_search(
					intval(MetadataUtilities\Value::flattenValue($value)),
					(array) $metadata->offsetGet($type)->offsetGet('ValidValues'),
					true,
				);

				if ($enumValue !== false) {
					$value = $enumValue;
				}
			}

			$table->addRow([
				$index + 1,
				$property->getName() ?? $property->getIdentifier(),
				str_replace(' ', '', ucwords(str_replace('_', ' ', $property->getIdentifier()))),
				$value,
			]);
		}

		$table->render();

		$io->newLine();
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function askInstallAction(Style\SymfonyStyle $io): void
	{
		$question = new Console\Question\ChoiceQuestion(
			(string) $this->translator->translate('//homekit-connector.cmd.base.questions.whatToDo'),
			[
				0 => (string) $this->translator->translate('//homekit-connector.cmd.install.actions.create.connector'),
				1 => (string) $this->translator->translate('//homekit-connector.cmd.install.actions.update.connector'),
				2 => (string) $this->translator->translate('//homekit-connector.cmd.install.actions.remove.connector'),
				3 => (string) $this->translator->translate('//homekit-connector.cmd.install.actions.manage.connector'),
				4 => (string) $this->translator->translate('//homekit-connector.cmd.install.actions.list.connectors'),
				5 => (string) $this->translator->translate('//homekit-connector.cmd.install.actions.nothing'),
			],
			5,
		);

		$question->setErrorMessage(
			(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);

		$whatToDo = $io->askQuestion($question);

		if (
			$whatToDo === (string) $this->translator->translate(
				'//homekit-connector.cmd.install.actions.create.connector',
			)
			|| $whatToDo === '0'
		) {
			$this->createConnector($io);

			$this->askInstallAction($io);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//homekit-connector.cmd.install.actions.update.connector',
			)
			|| $whatToDo === '1'
		) {
			$this->editConnector($io);

			$this->askInstallAction($io);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//homekit-connector.cmd.install.actions.remove.connector',
			)
			|| $whatToDo === '2'
		) {
			$this->deleteConnector($io);

			$this->askInstallAction($io);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//homekit-connector.cmd.install.actions.manage.connector',
			)
			|| $whatToDo === '3'
		) {
			$this->manageConnector($io);

			$this->askInstallAction($io);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//homekit-connector.cmd.install.actions.list.connectors',
			)
			|| $whatToDo === '4'
		) {
			$this->listConnectors($io);

			$this->askInstallAction($io);
		}
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function askManageConnectorAction(
		Style\SymfonyStyle $io,
		Entities\Connectors\Connector $connector,
	): void
	{
		$connector = $this->connectorsRepository->find($connector->getId(), Entities\Connectors\Connector::class);
		assert($connector instanceof Entities\Connectors\Connector);

		$question = new Console\Question\ChoiceQuestion(
			(string) $this->translator->translate('//homekit-connector.cmd.base.questions.whatToDo'),
			[
				0 => (string) $this->translator->translate('//homekit-connector.cmd.install.actions.create.device'),
				1 => (string) $this->translator->translate('//homekit-connector.cmd.install.actions.update.device'),
				2 => (string) $this->translator->translate('//homekit-connector.cmd.install.actions.remove.device'),
				3 => (string) $this->translator->translate('//homekit-connector.cmd.install.actions.manage.device'),
				4 => (string) $this->translator->translate('//homekit-connector.cmd.install.actions.list.devices'),
				5 => (string) $this->translator->translate('//homekit-connector.cmd.install.actions.nothing'),
			],
			5,
		);

		$question->setErrorMessage(
			(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);

		$whatToDo = $io->askQuestion($question);

		if (
			$whatToDo === (string) $this->translator->translate(
				'//homekit-connector.cmd.install.actions.create.device',
			)
			|| $whatToDo === '0'
		) {
			$this->createDevice($io, $connector);

			$this->askManageConnectorAction($io, $connector);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//homekit-connector.cmd.install.actions.update.device',
			)
			|| $whatToDo === '1'
		) {
			$this->editDevice($io, $connector);

			$this->askManageConnectorAction($io, $connector);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//homekit-connector.cmd.install.actions.remove.device',
			)
			|| $whatToDo === '2'
		) {
			$this->deleteDevice($io, $connector);

			$this->askManageConnectorAction($io, $connector);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//homekit-connector.cmd.install.actions.manage.device',
			)
			|| $whatToDo === '3'
		) {
			$this->manageDevice($io, $connector);

			$this->askManageConnectorAction($io, $connector);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//homekit-connector.cmd.install.actions.list.devices',
			)
			|| $whatToDo === '4'
		) {
			$this->listDevices($io, $connector);

			$this->askManageConnectorAction($io, $connector);
		}
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function askManageDeviceAction(
		Style\SymfonyStyle $io,
		Entities\Devices\Device $device,
	): void
	{
		$device = $this->devicesRepository->find($device->getId(), Entities\Devices\Device::class);
		assert($device instanceof Entities\Devices\Device);

		$question = new Console\Question\ChoiceQuestion(
			(string) $this->translator->translate('//homekit-connector.cmd.base.questions.whatToDo'),
			[
				0 => (string) $this->translator->translate('//homekit-connector.cmd.install.actions.create.service'),
				1 => (string) $this->translator->translate('//homekit-connector.cmd.install.actions.update.service'),
				2 => (string) $this->translator->translate('//homekit-connector.cmd.install.actions.remove.service'),
				3 => (string) $this->translator->translate('//homekit-connector.cmd.install.actions.manage.service'),
				4 => (string) $this->translator->translate('//homekit-connector.cmd.install.actions.list.services'),
				5 => (string) $this->translator->translate('//homekit-connector.cmd.install.actions.nothing'),
			],
			5,
		);

		$question->setErrorMessage(
			(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);

		$whatToDo = $io->askQuestion($question);

		if (
			$whatToDo === (string) $this->translator->translate(
				'//homekit-connector.cmd.install.actions.create.service',
			)
			|| $whatToDo === '0'
		) {
			$this->createService($io, $device);

			$this->askManageDeviceAction($io, $device);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//homekit-connector.cmd.install.actions.update.service',
			)
			|| $whatToDo === '1'
		) {
			$this->editService($io, $device);

			$this->askManageDeviceAction($io, $device);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//homekit-connector.cmd.install.actions.remove.service',
			)
			|| $whatToDo === '2'
		) {
			$this->deleteService($io, $device);

			$this->askManageDeviceAction($io, $device);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//homekit-connector.cmd.install.actions.manage.service',
			)
			|| $whatToDo === '3'
		) {
			$this->manageService($io, $device);

			$this->askManageDeviceAction($io, $device);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//homekit-connector.cmd.install.actions.list.services',
			)
			|| $whatToDo === '4'
		) {
			$this->listServices($io, $device);

			$this->askManageDeviceAction($io, $device);
		}
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function askManageServiceAction(
		Style\SymfonyStyle $io,
		Entities\Channels\Channel $channel,
	): void
	{
		$channel = $this->channelsRepository->find($channel->getId(), Entities\Channels\Channel::class);
		assert($channel instanceof Entities\Channels\Channel);

		$question = new Console\Question\ChoiceQuestion(
			(string) $this->translator->translate('//homekit-connector.cmd.base.questions.whatToDo'),
			[
				0 => (string) $this->translator->translate(
					'//homekit-connector.cmd.install.actions.update.characteristic',
				),
				1 => (string) $this->translator->translate(
					'//homekit-connector.cmd.install.actions.remove.characteristic',
				),
				2 => (string) $this->translator->translate(
					'//homekit-connector.cmd.install.actions.list.characteristics',
				),
				3 => (string) $this->translator->translate('//homekit-connector.cmd.install.actions.nothing'),
			],
			3,
		);

		$question->setErrorMessage(
			(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);

		$whatToDo = $io->askQuestion($question);

		if (
			$whatToDo === (string) $this->translator->translate(
				'//homekit-connector.cmd.install.actions.update.characteristic',
			)
			|| $whatToDo === '0'
		) {
			$this->editCharacteristic($io, $channel);

			$this->askManageServiceAction($io, $channel);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//homekit-connector.cmd.install.actions.remove.characteristic',
			)
			|| $whatToDo === '1'
		) {
			$this->deleteCharacteristic($io, $channel);

			$this->askManageServiceAction($io, $channel);

		} elseif (
			$whatToDo === (string) $this->translator->translate(
				'//homekit-connector.cmd.install.actions.list.characteristics',
			)
			|| $whatToDo === '2'
		) {
			$this->listCharacteristics($io, $channel);

			$this->askManageServiceAction($io, $channel);
		}
	}

	private function askConnectorName(
		Style\SymfonyStyle $io,
		Entities\Connectors\Connector|null $connector = null,
	): string|null
	{
		$question = new Console\Question\Question(
			(string) $this->translator->translate('//homekit-connector.cmd.install.questions.provide.connector.name'),
			$connector?->getName(),
		);

		$name = $io->askQuestion($question);

		return strval($name) === '' ? null : strval($name);
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function askConnectorPort(Style\SymfonyStyle $io, Entities\Connectors\Connector|null $connector = null): int
	{
		$question = new Console\Question\Question(
			(string) $this->translator->translate('//homekit-connector.cmd.install.questions.provide.connector.port'),
			$connector?->getPort() ?? HomeKit\Constants::DEFAULT_PORT,
		);
		$question->setValidator(function (string|null $answer) use ($connector): string {
			if ($answer === '' || $answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			$findConnectorPropertiesQuery = new Queries\Entities\FindConnectorProperties();
			$findConnectorPropertiesQuery->byIdentifier(Types\ConnectorPropertyIdentifier::PORT);

			$properties = $this->connectorsPropertiesRepository->findAllBy(
				$findConnectorPropertiesQuery,
				DevicesEntities\Connectors\Properties\Variable::class,
			);

			foreach ($properties as $property) {
				if (
					$property->getConnector() instanceof Entities\Connectors\Connector
					&& $property->getValue() === intval($answer)
					&& (
						$connector === null || !$property->getConnector()->getId()->equals($connector->getId())
					)
				) {
					throw new Exceptions\Runtime(
						(string) $this->translator->translate(
							'//homekit-connector.cmd.install.messages.portUsed',
							['connector' => $property->getConnector()->getIdentifier()],
						),
					);
				}
			}

			return $answer;
		});

		return intval($io->askQuestion($question));
	}

	private function askDeviceName(Style\SymfonyStyle $io, Entities\Devices\Device|null $device = null): string|null
	{
		$question = new Console\Question\Question(
			(string) $this->translator->translate('//homekit-connector.cmd.install.questions.provide.device.name'),
			$device?->getName(),
		);

		$name = $io->askQuestion($question);

		return strval($name) === '' ? null : strval($name);
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function askDeviceCategory(
		Style\SymfonyStyle $io,
		Entities\Devices\Device|null $device = null,
	): Types\AccessoryCategory
	{
		$categories = array_combine(
			array_map(
				static fn (Types\AccessoryCategory $category): int => $category->value,
				Types\AccessoryCategory::cases(),
			),
			array_map(
				fn (Types\AccessoryCategory $category): string => (string) $this->translator->translate(
					'//homekit-connector.cmd.base.category.' . $category->value,
				),
				Types\AccessoryCategory::cases(),
			),
		);
		$categories = array_filter(
			$categories,
			fn (string $category): bool => $category !== (string) $this->translator->translate(
				'//homekit-connector.cmd.base.category.' . Types\AccessoryCategory::BRIDGE->value,
			),
		);
		asort($categories);

		$default = $device !== null ? array_search(
			(string) $this->translator->translate(
				'//homekit-connector.cmd.base.category.' . $device->getAccessoryCategory()->value,
			),
			array_values($categories),
			true,
		) : array_search(
			(string) $this->translator->translate(
				'//homekit-connector.cmd.base.category.' . Types\AccessoryCategory::OTHER->value,
			),
			array_values($categories),
			true,
		);

		$question = new Console\Question\ChoiceQuestion(
			(string) $this->translator->translate('//homekit-connector.cmd.install.questions.select.device.category'),
			array_values($categories),
			$default,
		);
		$question->setErrorMessage(
			(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|int|null $answer) use ($categories): Types\AccessoryCategory {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (array_key_exists($answer, array_values($categories))) {
				$answer = array_values($categories)[$answer];
			}

			$category = array_search($answer, $categories, true);

			if ($category !== false) {
				return Types\AccessoryCategory::from(intval($category));
			}

			throw new Exceptions\Runtime(
				sprintf(
					(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
					$answer,
				),
			);
		});

		$answer = $io->askQuestion($question);
		assert($answer instanceof Types\AccessoryCategory);

		return $answer;
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function askServiceType(
		Style\SymfonyStyle $io,
		Entities\Devices\Device $device,
	): string
	{
		$findPropertyQuery = new Queries\Entities\FindDeviceProperties();
		$findPropertyQuery->forDevice($device);
		$findPropertyQuery->byIdentifier(Types\DevicePropertyIdentifier::CATEGORY);

		$category = $this->devicesPropertiesRepository->findOneBy(
			$findPropertyQuery,
			DevicesEntities\Devices\Properties\Variable::class,
		);

		if (!$category instanceof DevicesEntities\Devices\Properties\Variable) {
			throw new Exceptions\InvalidState('Device category is not configured');
		}

		if ($category->getValue() === Types\AccessoryCategory::OTHER->value) {
			$metadata = $this->loader->loadServices();

			$services = array_values(array_keys((array) $metadata));
		} else {
			$metadata = $this->loader->loadAccessories();

			if (!$metadata->offsetExists(MetadataUtilities\Value::toString($category->getValue(), true))) {
				throw new Exceptions\InvalidArgument(sprintf(
					'Definition for accessory category: %s was not found',
					MetadataUtilities\Value::toString($category->getValue()),
				));
			}

			$accessoryMetadata = $metadata->offsetGet(
				MetadataUtilities\Value::toString($category->getValue(), true),
			);

			if (
				!$accessoryMetadata instanceof Utils\ArrayHash
				|| !$accessoryMetadata->offsetExists('name')
				|| !is_string($accessoryMetadata->offsetGet('name'))
				|| !$accessoryMetadata->offsetExists('services')
				|| !$accessoryMetadata->offsetGet('services') instanceof Utils\ArrayHash
			) {
				throw new Exceptions\InvalidState('Accessory definition is missing required attributes');
			}

			$services = array_values((array) $accessoryMetadata->offsetGet('services'));
		}

		$question = new Console\Question\ChoiceQuestion(
			'What type of device service you would like to add?',
			$services,
			0,
		);

		$question->setErrorMessage(
			(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|null $answer) use ($services): string {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (array_key_exists($answer, array_values($services))) {
				$answer = array_values($services)[$answer];
			}

			return strval($answer);
		});

		return strval($io->askQuestion($question));
	}

	/**
	 * @param array<string> $characteristics
	 * @param array<string> $ignore
	 *
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Nette\IOException
	 */
	private function askCharacteristic(
		Style\SymfonyStyle $io,
		Types\ServiceType $service,
		bool $required = true,
		array $characteristics = [],
		array $ignore = [],
	): string|null
	{
		$metadata = $this->loader->loadServices();

		if (!$metadata->offsetExists($service->value)) {
			throw new Exceptions\InvalidArgument(sprintf(
				'Definition for service: %s was not found',
				$service->value,
			));
		}

		$characteristics = array_values(array_diff($characteristics, $ignore));

		if (!$required) {
			$characteristics[] = (string) $this->translator->translate('//homekit-connector.cmd.install.answers.none');
		}

		$question = $required ? new Console\Question\ChoiceQuestion(
			(string) $this->translator->translate(
				'//homekit-connector.cmd.install.questions.select.device.requiredCharacteristic',
			),
			$characteristics,
			0,
		) : new Console\Question\ChoiceQuestion(
			(string) $this->translator->translate(
				'//homekit-connector.cmd.install.questions.select.device.optionalCharacteristic',
			),
			$characteristics,
			count($characteristics) - 1,
		);

		$question->setErrorMessage(
			(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|null $answer) use ($required, $characteristics): string|null {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (array_key_exists($answer, array_values($characteristics))) {
				$answer = array_values($characteristics)[$answer];
			}

			if (
				!$required
				&& (
					$answer === (string) $this->translator->translate('//homekit-connector.cmd.install.answers.none')
					|| $answer === strval(count($characteristics) - 1)
				)
			) {
				return null;
			}

			return strval($answer);
		});

		$characteristic = $io->askQuestion($question);

		return $characteristic === null ? null : strval($characteristic);
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exception
	 */
	private function askProperty(
		Style\SymfonyStyle $io,
		DevicesEntities\Channels\Properties\Dynamic|null $connectedProperty = null,
		bool|null $settable = null,
	): DevicesEntities\Channels\Properties\Dynamic|null
	{
		$devices = [];

		$connectedChannel = $connectedProperty?->getChannel();
		$connectedDevice = $connectedProperty?->getChannel()->getDevice();

		$findDevicesQuery = new DevicesQueries\Entities\FindDevices();

		$systemDevices = $this->devicesRepository->findAllBy($findDevicesQuery);
		$systemDevices = array_filter($systemDevices, function (DevicesEntities\Devices\Device $device): bool {
			$findChannelsQuery = new DevicesQueries\Entities\FindChannels();
			$findChannelsQuery->forDevice($device);
			$findChannelsQuery->withProperties();

			return $this->channelsRepository->getResultSet($findChannelsQuery)->count() > 0;
		});
		usort(
			$systemDevices,
			static fn (DevicesEntities\Devices\Device $a, DevicesEntities\Devices\Device $b): int => (
				(
					($a->getConnector()->getName() ?? $a->getConnector()->getIdentifier())
					<=> ($b->getConnector()->getName() ?? $b->getConnector()->getIdentifier())
				) * 100 +
				(($a->getName() ?? $a->getIdentifier()) <=> ($b->getName() ?? $b->getIdentifier()))
			),
		);

		foreach ($systemDevices as $device) {
			if ($device instanceof Entities\Devices\Device) {
				continue;
			}

			$findChannelsQuery = new DevicesQueries\Entities\FindChannels();
			$findChannelsQuery->forDevice($device);

			$channels = $this->channelsRepository->findAllBy($findChannelsQuery);

			$hasProperty = false;

			foreach ($channels as $channel) {
				$findChannelPropertiesQuery = new DevicesQueries\Entities\FindChannelDynamicProperties();
				$findChannelPropertiesQuery->forChannel($channel);

				if ($settable === true) {
					$findChannelPropertiesQuery->settable(true);
				}

				if (
					$this->channelsPropertiesRepository->getResultSet(
						$findChannelPropertiesQuery,
						DevicesEntities\Channels\Properties\Dynamic::class,
					)->count() > 0
				) {
					$hasProperty = true;

					break;
				}
			}

			if (!$hasProperty) {
				continue;
			}

			$devices[$device->getId()->toString()] = '[' . ($device->getConnector()->getName() ?? $device->getConnector()->getIdentifier()) . '] '
				. ($device->getName() ?? $device->getIdentifier());
		}

		if (count($devices) === 0) {
			$io->warning(
				(string) $this->translator->translate('//homekit-connector.cmd.install.messages.noHardwareDevices'),
			);

			return null;
		}

		$default = count($devices) === 1 ? 0 : null;

		if ($connectedDevice !== null) {
			foreach (array_values(array_flip($devices)) as $index => $value) {
				if ($value === $connectedDevice->getId()->toString()) {
					$default = $index;

					break;
				}
			}
		}

		$question = new Console\Question\ChoiceQuestion(
			(string) $this->translator->translate(
				'//homekit-connector.cmd.install.questions.select.device.mappedDevice',
			),
			array_values($devices),
			$default,
		);
		$question->setErrorMessage(
			(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|null $answer) use ($devices): DevicesEntities\Devices\Device {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (array_key_exists($answer, array_values($devices))) {
				$answer = array_values($devices)[$answer];
			}

			$identifier = array_search($answer, $devices, true);

			if ($identifier !== false) {
				$device = $this->devicesRepository->find(Uuid\Uuid::fromString($identifier));

				if ($device !== null) {
					return $device;
				}
			}

			throw new Exceptions\Runtime(
				sprintf(
					(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
					$answer,
				),
			);
		});

		$device = $io->askQuestion($question);
		assert($device instanceof DevicesEntities\Devices\Device);

		$channels = [];

		$findChannelsQuery = new DevicesQueries\Entities\FindChannels();
		$findChannelsQuery->forDevice($device);
		$findChannelsQuery->withProperties();

		$deviceChannels = $this->channelsRepository->findAllBy($findChannelsQuery);
		usort(
			$deviceChannels,
			static fn (DevicesEntities\Channels\Channel $a, DevicesEntities\Channels\Channel $b): int => (
				($a->getName() ?? $a->getIdentifier()) <=> ($b->getName() ?? $b->getIdentifier())
			),
		);

		foreach ($deviceChannels as $channel) {
			$hasProperty = false;

			$findChannelPropertiesQuery = new DevicesQueries\Entities\FindChannelDynamicProperties();
			$findChannelPropertiesQuery->forChannel($channel);

			if ($settable === true) {
				$findChannelPropertiesQuery->settable(true);
			}

			if (
				$this->channelsPropertiesRepository->getResultSet(
					$findChannelPropertiesQuery,
					DevicesEntities\Channels\Properties\Dynamic::class,
				)->count() > 0
			) {
				$hasProperty = true;
			}

			if (!$hasProperty) {
				continue;
			}

			$channels[$channel->getId()->toString()] = $channel->getName() ?? $channel->getIdentifier();
		}

		$default = count($channels) === 1 ? 0 : null;

		if ($connectedChannel !== null) {
			foreach (array_values(array_flip($channels)) as $index => $value) {
				if ($value === $connectedChannel->getId()->toString()) {
					$default = $index;

					break;
				}
			}
		}

		$question = new Console\Question\ChoiceQuestion(
			(string) $this->translator->translate(
				'//homekit-connector.cmd.install.questions.select.device.mappedDeviceChannel',
			),
			array_values($channels),
			$default,
		);
		$question->setErrorMessage(
			(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(
			function (string|null $answer) use ($channels): DevicesEntities\Channels\Channel {
				if ($answer === null) {
					throw new Exceptions\Runtime(
						sprintf(
							(string) $this->translator->translate(
								'//homekit-connector.cmd.base.messages.answerNotValid',
							),
							$answer,
						),
					);
				}

				if (array_key_exists($answer, array_values($channels))) {
					$answer = array_values($channels)[$answer];
				}

				$identifier = array_search($answer, $channels, true);

				if ($identifier !== false) {
					$channel = $this->channelsRepository->find(Uuid\Uuid::fromString($identifier));

					if ($channel !== null) {
						return $channel;
					}
				}

				throw new Exceptions\Runtime(
					sprintf(
						(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			},
		);

		$channel = $io->askQuestion($question);
		assert($channel instanceof DevicesEntities\Channels\Channel);

		$properties = [];

		$findChannelPropertiesQuery = new DevicesQueries\Entities\FindChannelProperties();
		$findChannelPropertiesQuery->forChannel($channel);

		if ($settable === true) {
			$findChannelPropertiesQuery->settable(true);
		}

		$channelProperties = $this->channelsPropertiesRepository->findAllBy(
			$findChannelPropertiesQuery,
			DevicesEntities\Channels\Properties\Dynamic::class,
		);
		usort(
			$channelProperties,
			static fn (DevicesEntities\Channels\Properties\Property $a, DevicesEntities\Channels\Properties\Property $b): int => (
				($a->getName() ?? $a->getIdentifier()) <=> ($b->getName() ?? $b->getIdentifier())
			),
		);

		foreach ($channelProperties as $property) {
			if (!$property instanceof DevicesEntities\Channels\Properties\Dynamic) {
				continue;
			}

			$properties[$property->getId()->toString()] = $property->getName() ?? $property->getIdentifier();
		}

		$default = count($properties) === 1 ? 0 : null;

		if ($connectedProperty !== null) {
			foreach (array_values(array_flip($properties)) as $index => $value) {
				if ($value === $connectedProperty->getId()->toString()) {
					$default = $index;

					break;
				}
			}
		}

		$question = new Console\Question\ChoiceQuestion(
			(string) $this->translator->translate(
				'//homekit-connector.cmd.install.questions.select.device.mappedChannelProperty',
			),
			array_values($properties),
			$default,
		);
		$question->setErrorMessage(
			(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(
			function (string|null $answer) use ($properties): DevicesEntities\Channels\Properties\Dynamic {
				if ($answer === null) {
					throw new Exceptions\Runtime(
						sprintf(
							(string) $this->translator->translate(
								'//homekit-connector.cmd.base.messages.answerNotValid',
							),
							$answer,
						),
					);
				}

				if (array_key_exists($answer, array_values($properties))) {
					$answer = array_values($properties)[$answer];
				}

				$identifier = array_search($answer, $properties, true);

				if ($identifier !== false) {
					$property = $this->channelsPropertiesRepository->find(
						Uuid\Uuid::fromString($identifier),
						DevicesEntities\Channels\Properties\Dynamic::class,
					);

					if ($property !== null) {
						return $property;
					}
				}

				throw new Exceptions\Runtime(
					sprintf(
						(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			},
		);

		$property = $io->askQuestion($question);
		assert($property instanceof DevicesEntities\Channels\Properties\Dynamic);

		return $property;
	}

	/**
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function askFormat(
		Style\SymfonyStyle $io,
		string $characteristic,
		DevicesEntities\Channels\Properties\Dynamic|null $connectProperty = null,
	): MetadataFormats\NumberRange|MetadataFormats\StringEnum|MetadataFormats\CombinedEnum|null
	{
		$metadata = $this->loader->loadCharacteristics();

		if (!$metadata->offsetExists($characteristic)) {
			throw new Exceptions\InvalidArgument(sprintf(
				'Definition for characteristic: %s was not found',
				$characteristic,
			));
		}

		$characteristicMetadata = $metadata->offsetGet($characteristic);

		if (
			!$characteristicMetadata instanceof Utils\ArrayHash
			|| !$characteristicMetadata->offsetExists('Format')
			|| !is_string($characteristicMetadata->offsetGet('Format'))
			|| !$characteristicMetadata->offsetExists('DataType')
			|| (
				!is_string($characteristicMetadata->offsetGet('DataType'))
				&& !$characteristicMetadata->offsetGet('DataType') instanceof Utils\ArrayHash
			)
		) {
			throw new Exceptions\InvalidState('Characteristic definition is missing required attributes');
		}

		if ($characteristicMetadata->offsetGet('DataType') instanceof Utils\ArrayHash) {
			$dataTypes = array_map(
				static fn (string $type): MetadataTypes\DataType => MetadataTypes\DataType::from($type),
				(array) $characteristicMetadata->offsetGet('DataType'),
			);

			if ($dataTypes === []) {
				throw new Exceptions\InvalidState('Characteristic definition is missing required attributes');
			}
		} else {
			$dataTypes = [MetadataTypes\DataType::from($characteristicMetadata->offsetGet('DataType'))];
		}

		$format = null;

		if (
			$characteristicMetadata->offsetExists('MinValue')
			|| $characteristicMetadata->offsetExists('MaxValue')
		) {
			$format = new MetadataFormats\NumberRange([
				$characteristicMetadata->offsetExists('MinValue')
					? floatval($characteristicMetadata->offsetGet('MinValue'))
					: null,
				$characteristicMetadata->offsetExists('MaxValue')
					? floatval($characteristicMetadata->offsetGet('MaxValue'))
					: null,
			]);
		}

		if (
			(
				in_array(MetadataTypes\DataType::ENUM, $dataTypes, true)
				|| in_array(MetadataTypes\DataType::SWITCH, $dataTypes, true)
				|| in_array(MetadataTypes\DataType::BUTTON, $dataTypes, true)
			)
			&& $characteristicMetadata->offsetExists('ValidValues')
			&& $characteristicMetadata->offsetGet('ValidValues') instanceof Utils\ArrayHash
		) {
			$format = new MetadataFormats\StringEnum(
				array_values((array) $characteristicMetadata->offsetGet('ValidValues')),
			);

			if (
				$connectProperty !== null
				&& (
					$connectProperty->getDataType() === MetadataTypes\DataType::ENUM
					|| $connectProperty->getDataType() === MetadataTypes\DataType::SWITCH
					|| $connectProperty->getDataType() === MetadataTypes\DataType::BUTTON
				) && (
					$connectProperty->getFormat() instanceof MetadataFormats\StringEnum
					|| $connectProperty->getFormat() instanceof MetadataFormats\CombinedEnum
				)
			) {
				$mappedFormat = [];

				foreach ($characteristicMetadata->offsetGet('ValidValues') as $name => $item) {
					$options = $connectProperty->getFormat() instanceof MetadataFormats\StringEnum
						? $connectProperty->getFormat()->toArray()
						: array_map(
							static function (array $items): array|null {
								if ($items[0] === null) {
									return null;
								}

								return [
									$items[0]->getDataType(),
									MetadataUtilities\Value::toString($items[0]->getValue()),
								];
							},
							$connectProperty->getFormat()->getItems(),
						);

					$question = new Console\Question\ChoiceQuestion(
						(string) $this->translator->translate(
							'//homekit-connector.cmd.install.questions.select.device.valueMapping',
							['value' => $name],
						),
						array_map(
							static fn ($item): string|null => is_array($item) ? $item[1] : $item,
							$options,
						),
					);
					$question->setErrorMessage(
						(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
					);
					$question->setValidator(function (string|null $answer) use ($options): string|array {
						if ($answer === null) {
							throw new Exceptions\Runtime(
								sprintf(
									(string) $this->translator->translate(
										'//homekit-connector.cmd.base.messages.answerNotValid',
									),
									$answer,
								),
							);
						}

						$remappedOptions = array_map(
							static fn ($item): string|null => is_array($item) ? $item[1] : $item,
							$options,
						);

						if (array_key_exists($answer, array_values($remappedOptions))) {
							$answer = array_values($remappedOptions)[$answer];
						}

						if (in_array($answer, $remappedOptions, true) && $answer !== null) {
							$options = array_values(array_filter(
								$options,
								static fn ($item): bool => is_array($item) ? $item[1] === $answer : $item === $answer,
							));

							if (count($options) === 1 && $options[0] !== null) {
								return $options[0];
							}
						}

						throw new Exceptions\Runtime(
							sprintf(
								(string) $this->translator->translate(
									'//homekit-connector.cmd.base.messages.answerNotValid',
								),
								strval($answer),
							),
						);
					});

					$value = $io->askQuestion($question);
					assert(is_string($value) || is_int($value) || is_array($value));

					$valueDataType = is_array($value) ? strval($value[0]) : null;
					$value = is_array($value) ? $value[1] : $value;

					if (MetadataTypes\Payloads\Switcher::tryFrom($value) !== null) {
						$valueDataType = MetadataTypes\DataTypeShort::SWITCH->value;

					} elseif (MetadataTypes\Payloads\Button::tryFrom($value) !== null) {
						$valueDataType = MetadataTypes\DataTypeShort::BUTTON->value;

					} elseif (MetadataTypes\Payloads\Cover::tryFrom($value) !== null) {
						$valueDataType = MetadataTypes\DataTypeShort::COVER->value;
					}

					$mappedFormat[] = [
						[$valueDataType, strval($value)],
						[MetadataTypes\DataTypeShort::UCHAR->value, strval($item)],
						[MetadataTypes\DataTypeShort::UCHAR->value, strval($item)],
					];
				}

				$format = new MetadataFormats\CombinedEnum($mappedFormat);
			}
		}

		return $format;
	}

	/**
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws Nette\IOException
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function provideCharacteristicValue(
		Style\SymfonyStyle $io,
		string $characteristic,
		bool|float|int|string|DateTimeInterface|MetadataTypes\Payloads\Payload|null $value = null,
	): string|int|bool|float
	{
		$metadata = $this->loader->loadCharacteristics();

		if (!$metadata->offsetExists($characteristic)) {
			throw new Exceptions\InvalidArgument(sprintf(
				'Definition for characteristic: %s was not found',
				$characteristic,
			));
		}

		$characteristicMetadata = $metadata->offsetGet($characteristic);

		if (
			!$characteristicMetadata instanceof Utils\ArrayHash
			|| !$characteristicMetadata->offsetExists('DataType')
			|| (
				!is_string($characteristicMetadata->offsetGet('DataType'))
				&& !$characteristicMetadata->offsetGet('DataType') instanceof Utils\ArrayHash
			)
		) {
			throw new Exceptions\InvalidState('Characteristic definition is missing required attributes');
		}

		if ($characteristicMetadata->offsetGet('DataType') instanceof Utils\ArrayHash) {
			$dataTypes = array_map(
				static fn (string $type): MetadataTypes\DataType => MetadataTypes\DataType::from($type),
				(array) $characteristicMetadata->offsetGet('DataType'),
			);

			if ($dataTypes === []) {
				throw new Exceptions\InvalidState('Characteristic definition is missing required attributes');
			}
		} else {
			$dataTypes = [MetadataTypes\DataType::from($characteristicMetadata->offsetGet('DataType'))];
		}

		if (
			$characteristicMetadata->offsetExists('ValidValues')
			&& $characteristicMetadata->offsetGet('ValidValues') instanceof Utils\ArrayHash
		) {
			$options = array_combine(
				array_values((array) $characteristicMetadata->offsetGet('ValidValues')),
				array_keys((array) $characteristicMetadata->offsetGet('ValidValues')),
			);

			$question = new Console\Question\ChoiceQuestion(
				(string) $this->translator->translate('//homekit-connector.cmd.install.questions.select.device.value'),
				$options,
				$value !== null ? array_key_exists(
					MetadataUtilities\Value::toString($value, true),
					$options,
				) : null,
			);
			$question->setErrorMessage(
				(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
			);
			$question->setValidator(function (string|int|null $answer) use ($options): string|int {
				if ($answer === null) {
					throw new Exceptions\Runtime(
						sprintf(
							(string) $this->translator->translate(
								'//homekit-connector.cmd.base.messages.answerNotValid',
							),
							$answer,
						),
					);
				}

				if (array_key_exists($answer, array_values($options))) {
					$answer = array_values($options)[$answer];
				}

				$value = array_search($answer, $options, true);

				if ($value !== false) {
					return $value;
				}

				throw new Exceptions\Runtime(
					sprintf(
						(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			});

			$value = $io->askQuestion($question);
			assert(is_string($value) || is_numeric($value));

			return $value;
		}

		if (
			count($dataTypes) === 1
			&& in_array(MetadataTypes\DataType::BOOLEAN, $dataTypes, true)
		) {
			$question = new Console\Question\ChoiceQuestion(
				(string) $this->translator->translate('//homekit-connector.cmd.install.questions.select.device.value'),
				[
					(string) $this->translator->translate('//homekit-connector.cmd.install.answers.false'),
					(string) $this->translator->translate('//homekit-connector.cmd.install.answers.true'),
				],
				is_bool($value) ? ($value ? 0 : 1) : null,
			);
			$question->setErrorMessage(
				(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
			);
			$question->setValidator(function (string|int|null $answer): bool {
				if ($answer === null) {
					throw new Exceptions\Runtime(
						sprintf(
							(string) $this->translator->translate(
								'//homekit-connector.cmd.base.messages.answerNotValid',
							),
							$answer,
						),
					);
				}

				return boolval($answer);
			});

			$value = $io->askQuestion($question);
			assert(is_bool($value));

			return $value;
		}

		$minValue = $characteristicMetadata->offsetExists('MinValue')
			? floatval(
				$characteristicMetadata->offsetGet('MinValue'),
			)
			: null;
		$maxValue = $characteristicMetadata->offsetExists('MaxValue')
			? floatval(
				$characteristicMetadata->offsetGet('MaxValue'),
			)
			: null;
		$step = $characteristicMetadata->offsetExists('MinStep')
			? floatval(
				$characteristicMetadata->offsetGet('MinStep'),
			)
			: null;

		$question = new Console\Question\Question(
			(string) $this->translator->translate('//homekit-connector.cmd.install.questions.provide.value'),
			is_object($value) ? MetadataUtilities\Value::toString($value) : $value,
		);
		$question->setValidator(
			function (string|int|null $answer) use ($dataTypes, $minValue, $maxValue, $step): string|int|float {
				if ($answer === null) {
					throw new Exceptions\Runtime(
						sprintf(
							(string) $this->translator->translate(
								'//homekit-connector.cmd.base.messages.answerNotValid',
							),
							$answer,
						),
					);
				}

				if (
					count($dataTypes) === 1
					&& in_array(MetadataTypes\DataType::STRING, $dataTypes, true)
				) {
					return strval($answer);
				}

				if (
					count($dataTypes) === 1
					&& in_array(MetadataTypes\DataType::FLOAT, $dataTypes, true)
				) {
					if ($minValue !== null && floatval($answer) < $minValue) {
						throw new Exceptions\Runtime(
							sprintf(
								(string) $this->translator->translate(
									'//homekit-connector.cmd.base.messages.answerNotValid',
								),
								$answer,
							),
						);
					}

					if ($maxValue !== null && floatval($answer) > $maxValue) {
						throw new Exceptions\Runtime(
							sprintf(
								(string) $this->translator->translate(
									'//homekit-connector.cmd.base.messages.answerNotValid',
								),
								$answer,
							),
						);
					}

					if (
						$step !== null
						&& Math\BigDecimal::of($answer)->remainder(
							Math\BigDecimal::of(strval($step)),
						)->toFloat() !== 0.0
					) {
						throw new Exceptions\Runtime(
							sprintf(
								(string) $this->translator->translate(
									'//homekit-connector.cmd.base.messages.answerNotValid',
								),
								$answer,
							),
						);
					}

					return floatval($answer);
				}

				if (
					count($dataTypes) === 1
					&& (
						in_array(MetadataTypes\DataType::CHAR, $dataTypes, true)
						|| in_array(MetadataTypes\DataType::UCHAR, $dataTypes, true)
						|| in_array(MetadataTypes\DataType::SHORT, $dataTypes, true)
						|| in_array(MetadataTypes\DataType::USHORT, $dataTypes, true)
						|| in_array(MetadataTypes\DataType::INT, $dataTypes, true)
						|| in_array(MetadataTypes\DataType::UINT, $dataTypes, true)
					)
				) {
					if ($minValue !== null && intval($answer) < $minValue) {
						throw new Exceptions\Runtime(
							sprintf(
								(string) $this->translator->translate(
									'//homekit-connector.cmd.base.messages.answerNotValid',
								),
								$answer,
							),
						);
					}

					if ($maxValue !== null && intval($answer) > $maxValue) {
						throw new Exceptions\Runtime(
							sprintf(
								(string) $this->translator->translate(
									'//homekit-connector.cmd.base.messages.answerNotValid',
								),
								$answer,
							),
						);
					}

					if ($step !== null && intval($answer) % $step !== 0) {
						throw new Exceptions\Runtime(
							sprintf(
								(string) $this->translator->translate(
									'//homekit-connector.cmd.base.messages.answerNotValid',
								),
								$answer,
							),
						);
					}

					return intval($answer);
				}

				throw new Exceptions\Runtime(
					sprintf(
						(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			},
		);

		$value = $io->askQuestion($question);
		assert(is_string($value) || is_int($value) || is_float($value));

		return $value;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 */
	private function askWhichConnector(Style\SymfonyStyle $io): Entities\Connectors\Connector|null
	{
		$connectors = [];

		$findConnectorsQuery = new Queries\Entities\FindConnectors();

		$systemConnectors = $this->connectorsRepository->findAllBy(
			$findConnectorsQuery,
			Entities\Connectors\Connector::class,
		);
		usort(
			$systemConnectors,
			static fn (Entities\Connectors\Connector $a, Entities\Connectors\Connector $b): int => (
				($a->getName() ?? $a->getIdentifier()) <=> ($b->getName() ?? $b->getIdentifier())
			),
		);

		foreach ($systemConnectors as $connector) {
			$connectors[$connector->getIdentifier()] = $connector->getName() ?? $connector->getIdentifier();
		}

		if (count($connectors) === 0) {
			return null;
		}

		$question = new Console\Question\ChoiceQuestion(
			(string) $this->translator->translate('//homekit-connector.cmd.install.questions.select.item.connector'),
			array_values($connectors),
			count($connectors) === 1 ? 0 : null,
		);

		$question->setErrorMessage(
			(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|int|null $answer) use ($connectors): Entities\Connectors\Connector {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (array_key_exists($answer, array_values($connectors))) {
				$answer = array_values($connectors)[$answer];
			}

			$identifier = array_search($answer, $connectors, true);

			if ($identifier !== false) {
				$findConnectorQuery = new Queries\Entities\FindConnectors();
				$findConnectorQuery->byIdentifier($identifier);

				$connector = $this->connectorsRepository->findOneBy(
					$findConnectorQuery,
					Entities\Connectors\Connector::class,
				);

				if ($connector !== null) {
					return $connector;
				}
			}

			throw new Exceptions\Runtime(
				sprintf(
					(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
					$answer,
				),
			);
		});

		$connector = $io->askQuestion($question);
		assert($connector instanceof Entities\Connectors\Connector);

		return $connector;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 */
	private function askWhichDevice(
		Style\SymfonyStyle $io,
		Entities\Connectors\Connector $connector,
	): Entities\Devices\Device|null
	{
		$devices = [];

		$findDevicesQuery = new Queries\Entities\FindDevices();
		$findDevicesQuery->forConnector($connector);

		$connectorDevices = $this->devicesRepository->findAllBy(
			$findDevicesQuery,
			Entities\Devices\Device::class,
		);
		usort(
			$connectorDevices,
			static fn (Entities\Devices\Device $a, Entities\Devices\Device $b): int => (
				($a->getName() ?? $a->getIdentifier()) <=> ($b->getName() ?? $b->getIdentifier())
			),
		);

		foreach ($connectorDevices as $device) {
			$devices[$device->getIdentifier()] = $device->getName() ?? $device->getIdentifier();
		}

		if (count($devices) === 0) {
			return null;
		}

		$question = new Console\Question\ChoiceQuestion(
			(string) $this->translator->translate('//homekit-connector.cmd.install.questions.select.item.device'),
			array_values($devices),
			count($devices) === 1 ? 0 : null,
		);

		$question->setErrorMessage(
			(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(
			function (string|int|null $answer) use ($connector, $devices): Entities\Devices\Device {
				if ($answer === null) {
					throw new Exceptions\Runtime(
						sprintf(
							(string) $this->translator->translate(
								'//homekit-connector.cmd.base.messages.answerNotValid',
							),
							$answer,
						),
					);
				}

				if (array_key_exists($answer, array_values($devices))) {
					$answer = array_values($devices)[$answer];
				}

				$identifier = array_search($answer, $devices, true);

				if ($identifier !== false) {
					$findDeviceQuery = new Queries\Entities\FindDevices();
					$findDeviceQuery->byIdentifier($identifier);
					$findDeviceQuery->forConnector($connector);

					$device = $this->devicesRepository->findOneBy(
						$findDeviceQuery,
						Entities\Devices\Device::class,
					);

					if ($device !== null) {
						return $device;
					}
				}

				throw new Exceptions\Runtime(
					sprintf(
						(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			},
		);

		$device = $io->askQuestion($question);
		assert($device instanceof Entities\Devices\Device);

		return $device;
	}

	/**
	 * @param array<string, string> $channels
	 *
	 * @throws ApplicationExceptions\InvalidState
	 */
	private function askWhichService(
		Style\SymfonyStyle $io,
		Entities\Devices\Device $device,
		array $channels,
	): Entities\Channels\Channel|null
	{
		$question = new Console\Question\ChoiceQuestion(
			(string) $this->translator->translate('//homekit-connector.cmd.install.questions.select.item.service'),
			array_values($channels),
			count($channels) === 1 ? 0 : null,
		);
		$question->setErrorMessage(
			(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);

		$serviceIdentifier = array_search($io->askQuestion($question), $channels, true);

		if ($serviceIdentifier === false) {
			$io->error(
				(string) $this->translator->translate('//homekit-connector.cmd.install.messages.serviceNotFound'),
			);

			$this->logger->alert(
				'Could not read service identifier from console answer',
				[
					'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
					'type' => 'install-cmd',
				],
			);

			return null;
		}

		$findChannelQuery = new Queries\Entities\FindChannels();
		$findChannelQuery->forDevice($device);
		$findChannelQuery->byIdentifier($serviceIdentifier);

		$channel = $this->channelsRepository->findOneBy($findChannelQuery, Entities\Channels\Channel::class);

		if ($channel === null) {
			$io->error(
				(string) $this->translator->translate('//homekit-connector.cmd.install.messages.serviceNotFound'),
			);

			$this->logger->alert(
				'Channel was not found',
				[
					'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
					'type' => 'install-cmd',
				],
			);

			return null;
		}

		return $channel;
	}

	/**
	 * @param array<string, string> $properties
	 *
	 * @throws ApplicationExceptions\InvalidState
	 */
	private function askWhichCharacteristic(
		Style\SymfonyStyle $io,
		Entities\Channels\Channel $channel,
		array $properties,
	): DevicesEntities\Channels\Properties\Variable|DevicesEntities\Channels\Properties\Mapped|null
	{
		$question = new Console\Question\ChoiceQuestion(
			(string) $this->translator->translate(
				'//homekit-connector.cmd.install.questions.select.item.characteristic',
			),
			array_values($properties),
		);
		$question->setErrorMessage(
			(string) $this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);

		$characteristicIdentifier = array_search($io->askQuestion($question), $properties, true);

		if ($characteristicIdentifier === false) {
			$io->error(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.messages.characteristicNotFound',
				),
			);

			$this->logger->alert(
				'Could not read characteristic identifier from console answer',
				[
					'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
					'type' => 'install-cmd',
				],
			);

			return null;
		}

		$findPropertyQuery = new DevicesQueries\Entities\FindChannelProperties();
		$findPropertyQuery->forChannel($channel);
		$findPropertyQuery->byIdentifier($characteristicIdentifier);

		$property = $this->channelsPropertiesRepository->findOneBy($findPropertyQuery);

		if ($property === null) {
			$io->error(
				(string) $this->translator->translate(
					'//homekit-connector.cmd.install.messages.characteristicNotFound',
				),
			);

			$this->logger->alert(
				'Property was not found',
				[
					'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
					'type' => 'install-cmd',
				],
			);

			return null;
		}

		assert(
			$property instanceof DevicesEntities\Channels\Properties\Variable || $property instanceof DevicesEntities\Channels\Properties\Mapped,
		);

		return $property;
	}

	/**
	 * @return array<string, string>
	 *
	 * @throws DevicesExceptions\InvalidState
	 */
	private function getServicesList(Entities\Devices\Device $device): array
	{
		$channels = [];

		$findChannelsQuery = new Queries\Entities\FindChannels();
		$findChannelsQuery->forDevice($device);

		$deviceChannels = $this->channelsRepository->findAllBy($findChannelsQuery, Entities\Channels\Channel::class);
		usort(
			$deviceChannels,
			static fn (DevicesEntities\Channels\Channel $a, DevicesEntities\Channels\Channel $b): int => (
				($a->getName() ?? $a->getIdentifier()) <=> ($b->getName() ?? $b->getIdentifier())
			),
		);

		foreach ($deviceChannels as $channel) {
			$channels[$channel->getIdentifier()] = $channel->getName() ?? $channel->getIdentifier();
		}

		return $channels;
	}

	/**
	 * @return array<string, string>
	 *
	 * @throws DevicesExceptions\InvalidState
	 */
	private function getCharacteristicsList(Entities\Channels\Channel $channel): array
	{
		$properties = [];

		$findPropertiesQuery = new DevicesQueries\Entities\FindChannelProperties();
		$findPropertiesQuery->forChannel($channel);

		$channelProperties = $this->channelsPropertiesRepository->findAllBy($findPropertiesQuery);
		usort(
			$channelProperties,
			static fn (DevicesEntities\Channels\Properties\Property $a, DevicesEntities\Channels\Properties\Property $b): int => (
				($a->getName() ?? $a->getIdentifier()) <=> ($b->getName() ?? $b->getIdentifier())
			),
		);

		foreach ($channelProperties as $property) {
			$properties[$property->getIdentifier()] = $property->getName() ?? $property->getIdentifier();
		}

		return $properties;
	}

}
