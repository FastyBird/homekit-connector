<?php declare(strict_types = 1);

/**
 * Devices.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Commands
 * @since          1.0.0
 *
 * @date           21.01.23
 */

namespace FastyBird\Connector\HomeKit\Commands;

use Brick\Math;
use DateTimeInterface;
use Doctrine\DBAL;
use Doctrine\Persistence;
use FastyBird\Connector\HomeKit;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Connector\HomeKit\Queries;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Bootstrap\Helpers as BootstrapHelpers;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Library\Metadata\ValueObjects as MetadataValueObjects;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use Nette;
use Nette\Localization;
use Nette\Utils;
use Ramsey\Uuid;
use Symfony\Component\Console;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;
use Symfony\Component\Console\Style;
use Throwable;
use function array_combine;
use function array_diff;
use function array_filter;
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
 * Connector devices management command
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Commands
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Devices extends Console\Command\Command
{

	public const NAME = 'fb:homekit-connector:devices';

	public function __construct(
		private readonly Helpers\Loader $loader,
		private readonly HomeKit\Logger $logger,
		private readonly DevicesModels\Entities\Connectors\ConnectorsRepository $connectorsRepository,
		private readonly DevicesModels\Entities\Devices\DevicesRepository $devicesRepository,
		private readonly DevicesModels\Entities\Devices\DevicesManager $devicesManager,
		private readonly DevicesModels\Entities\Devices\Properties\PropertiesRepository $devicesPropertiesRepository,
		private readonly DevicesModels\Entities\Devices\Properties\PropertiesManager $devicesPropertiesManager,
		private readonly DevicesModels\Entities\Channels\ChannelsRepository $channelsRepository,
		private readonly DevicesModels\Entities\Channels\ChannelsManager $channelsManager,
		private readonly DevicesModels\Entities\Channels\Properties\PropertiesRepository $channelsPropertiesRepository,
		private readonly DevicesModels\Entities\Channels\Properties\PropertiesManager $channelsPropertiesManager,
		private readonly Persistence\ManagerRegistry $managerRegistry,
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
			->setDescription('HomeKit devices management');
	}

	/**
	 * @throws Console\Exception\InvalidArgumentException
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 */
	protected function execute(Input\InputInterface $input, Output\OutputInterface $output): int
	{
		$io = new Style\SymfonyStyle($input, $output);

		$io->title($this->translator->translate('//homekit-connector.cmd.devices.title'));

		$io->note($this->translator->translate('//homekit-connector.cmd.devices.subtitle'));

		if ($input->getOption('no-interaction') === false) {
			$question = new Console\Question\ConfirmationQuestion(
				$this->translator->translate('//homekit-connector.cmd.base.questions.continue'),
				false,
			);

			$continue = (bool) $io->askQuestion($question);

			if (!$continue) {
				return Console\Command\Command::SUCCESS;
			}
		}

		$connector = $this->askWhichConnector($io);

		if ($connector === null) {
			$io->warning($this->translator->translate('//homekit-connector.cmd.base.messages.noConnectors'));

			return Console\Command\Command::SUCCESS;
		}

		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//homekit-connector.cmd.base.questions.whatToDo'),
			[
				0 => $this->translator->translate('//homekit-connector.cmd.devices.actions.create'),
				1 => $this->translator->translate('//homekit-connector.cmd.devices.actions.update'),
				2 => $this->translator->translate('//homekit-connector.cmd.devices.actions.remove'),
			],
		);

		$question->setErrorMessage(
			$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);

		$whatToDo = $io->askQuestion($question);

		if ($whatToDo === $this->translator->translate('//homekit-connector.cmd.devices.actions.create')) {
			$this->createNewDevice($io, $connector);

		} elseif ($whatToDo === $this->translator->translate('//homekit-connector.cmd.devices.actions.update')) {
			$this->editExistingDevice($io, $connector);

		} elseif ($whatToDo === $this->translator->translate('//homekit-connector.cmd.devices.actions.remove')) {
			$this->deleteExistingDevice($io, $connector);
		}

		return Console\Command\Command::SUCCESS;
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 */
	private function createNewDevice(Style\SymfonyStyle $io, Entities\HomeKitConnector $connector): void
	{
		$question = new Console\Question\Question(
			$this->translator->translate('//homekit-connector.cmd.devices.questions.provide.identifier'),
		);

		$question->setValidator(function (string|null $answer) {
			if ($answer !== '' && $answer !== null) {
				$findDeviceQuery = new Queries\FindDevices();
				$findDeviceQuery->byIdentifier($answer);

				if (
					$this->devicesRepository->findOneBy($findDeviceQuery, Entities\HomeKitDevice::class) !== null
				) {
					throw new Exceptions\Runtime(
						$this->translator->translate('//homekit-connector.cmd.devices.messages.identifier.used'),
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

				$findDeviceQuery = new Queries\FindDevices();
				$findDeviceQuery->byIdentifier($identifier);

				if (
					$this->devicesRepository->findOneBy($findDeviceQuery, Entities\HomeKitDevice::class) === null
				) {
					break;
				}
			}
		}

		if ($identifier === '') {
			$io->error($this->translator->translate('//homekit-connector.cmd.devices.messages.identifier.missing'));

			return;
		}

		$name = $this->askDeviceName($io);

		$category = $this->askCategory($io);

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$device = $this->devicesManager->create(Utils\ArrayHash::from([
				'entity' => Entities\HomeKitDevice::class,
				'connector' => $connector,
				'identifier' => $identifier,
				'name' => $name,
			]));
			assert($device instanceof Entities\HomeKitDevice);

			$this->devicesPropertiesManager->create(Utils\ArrayHash::from([
				'entity' => DevicesEntities\Devices\Properties\Variable::class,
				'identifier' => Types\DevicePropertyIdentifier::CATEGORY,
				'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
				'value' => $category->getValue(),
				'device' => $device,
			]));

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(
				$this->translator->translate(
					'//homekit-connector.cmd.devices.messages.create.device.success',
					['name' => $device->getName() ?? $device->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'devices-cmd',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
				],
			);

			$io->error($this->translator->translate('//homekit-connector.cmd.devices.messages.create.device.error'));

			return;
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}

		$this->createService($io, $device);
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 */
	private function editExistingDevice(Style\SymfonyStyle $io, Entities\HomeKitConnector $connector): void
	{
		$device = $this->askWhichDevice($io, $connector);

		if ($device === null) {
			$io->warning($this->translator->translate('//homekit-connector.cmd.devices.messages.noDevices'));

			$question = new Console\Question\ConfirmationQuestion(
				$this->translator->translate('//homekit-connector.cmd.devices.questions.create'),
				false,
			);

			$continue = (bool) $io->askQuestion($question);

			if ($continue) {
				$this->createNewDevice($io, $connector);
			}

			return;
		}

		$name = $this->askDeviceName($io, $device);

		$findDevicePropertyQuery = new DevicesQueries\FindDeviceProperties();
		$findDevicePropertyQuery->forDevice($device);
		$findDevicePropertyQuery->byIdentifier(Types\DevicePropertyIdentifier::CATEGORY);

		$categoryProperty = $this->devicesPropertiesRepository->findOneBy($findDevicePropertyQuery);

		$category = $this->askCategory($io, $device);

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$device = $this->devicesManager->update($device, Utils\ArrayHash::from([
				'name' => $name,
			]));

			if ($categoryProperty === null) {
				$this->devicesPropertiesManager->create(Utils\ArrayHash::from([
					'entity' => DevicesEntities\Devices\Properties\Variable::class,
					'identifier' => Types\DevicePropertyIdentifier::CATEGORY,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
					'value' => $category->getValue(),
					'device' => $device,
				]));
			} elseif ($categoryProperty instanceof DevicesEntities\Devices\Properties\Variable) {
				$this->devicesPropertiesManager->update($categoryProperty, Utils\ArrayHash::from([
					'value' => $category->getValue(),
				]));
			}

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(
				$this->translator->translate(
					'//homekit-connector.cmd.devices.messages.update.device.success',
					['name' => $device->getName() ?? $device->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'devices-cmd',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
				],
			);

			$io->error($this->translator->translate('//homekit-connector.cmd.devices.messages.update.device.error'));
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}

		assert($device instanceof Entities\HomeKitDevice);

		$question = new Console\Question\ConfirmationQuestion(
			$this->translator->translate('//homekit-connector.cmd.devices.questions.editServices'),
			false,
		);

		$manage = (bool) $io->askQuestion($question);

		if (!$manage) {
			return;
		}

		$findChannelsQuery = new Queries\FindChannels();
		$findChannelsQuery->forDevice($device);

		$channels = $this->channelsRepository->findAllBy($findChannelsQuery, Entities\HomeKitChannel::class);

		if (count($channels) > 0) {
			$this->askServiceAction($io, $device, true);

			return;
		}

		$question = new Console\Question\ConfirmationQuestion(
			$this->translator->translate('//homekit-connector.cmd.devices.questions.createService'),
			false,
		);

		$create = (bool) $io->askQuestion($question);

		if ($create) {
			$this->createService($io, $device, true);
		}
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\Runtime
	 */
	private function deleteExistingDevice(Style\SymfonyStyle $io, Entities\HomeKitConnector $connector): void
	{
		$device = $this->askWhichDevice($io, $connector);

		if ($device === null) {
			$io->info($this->translator->translate('//homekit-connector.cmd.devices.messages.noDevices'));

			return;
		}

		$question = new Console\Question\ConfirmationQuestion(
			$this->translator->translate('//homekit-connector.cmd.base.questions.continue'),
			false,
		);

		$continue = (bool) $io->askQuestion($question);

		if (!$continue) {
			return;
		}

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$this->devicesManager->delete($device);

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(
				$this->translator->translate(
					'//homekit-connector.cmd.devices.messages.remove.device.success',
					['name' => $device->getName() ?? $device->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'devices-cmd',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
				],
			);

			$io->error($this->translator->translate('//homekit-connector.cmd.devices.messages.remove.device.error'));
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 */
	private function createService(Style\SymfonyStyle $io, Entities\HomeKitDevice $device, bool $editMode = false): void
	{
		$type = $this->askServiceType($io, $device);

		$identifier = strtolower(strval(preg_replace('/(?<!^)[A-Z]/', '_$0', $type)));

		$identifierPattern = $identifier . '_%d';

		for ($i = 1; $i <= 100; $i++) {
			$identifier = sprintf($identifierPattern, $i);

			$findChannelQuery = new Queries\FindChannels();
			$findChannelQuery->forDevice($device);
			$findChannelQuery->byIdentifier($identifier);

			$channel = $this->channelsRepository->findOneBy($findChannelQuery, Entities\HomeKitChannel::class);

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
			$this->getOrmConnection()->beginTransaction();

			$channel = $this->channelsManager->create(Utils\ArrayHash::from([
				'entity' => Entities\HomeKitChannel::class,
				'identifier' => $identifier,
				'device' => $device,
			]));
			assert($channel instanceof Entities\HomeKitChannel);

			$this->createCharacteristics($io, $device, $channel, $requiredCharacteristics, true);

			$this->createCharacteristics(
				$io,
				$device,
				$channel,
				array_merge($optionalCharacteristics, $virtualCharacteristics),
				false,
			);

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(
				$this->translator->translate(
					'//homekit-connector.cmd.devices.messages.create.service.success',
					['name' => $channel->getName() ?? $channel->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'devices-cmd',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
				],
			);

			$io->error($this->translator->translate('//homekit-connector.cmd.devices.messages.create.service.error'));

			return;
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}

		if ($editMode) {
			$this->askServiceAction($io, $device, $editMode);

			return;
		}

		$question = new Console\Question\ConfirmationQuestion(
			$this->translator->translate('//homekit-connector.cmd.devices.questions.createAnotherService'),
			false,
		);

		$create = (bool) $io->askQuestion($question);

		if ($create) {
			$this->createService($io, $device, $editMode);
		}
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 */
	private function editService(Style\SymfonyStyle $io, Entities\HomeKitDevice $device): void
	{
		$channels = $this->getServicesList($device);

		if (count($channels) === 0) {
			$io->warning($this->translator->translate('//homekit-connector.cmd.devices.messages.noServices'));

			$question = new Console\Question\ConfirmationQuestion(
				$this->translator->translate('//homekit-connector.cmd.devices.questions.createService'),
				false,
			);

			$continue = (bool) $io->askQuestion($question);

			if ($continue) {
				$this->createService($io, $device, true);
			}

			return;
		}

		$channel = $this->askWhichService($io, $device, $channels);

		if ($channel === null) {
			return;
		}

		$type = $channel->getServiceType();

		$metadata = $this->loader->loadServices();

		if (!$metadata->offsetExists(strval($type->getValue()))) {
			throw new Exceptions\InvalidArgument(sprintf(
				'Definition for service: %s was not found',
				strval($type->getValue()),
			));
		}

		$serviceMetadata = $metadata->offsetGet(strval($type->getValue()));

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
			$findPropertyQuery = new DevicesQueries\FindChannelProperties();
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
			$findPropertyQuery = new DevicesQueries\FindChannelProperties();
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
			$this->getOrmConnection()->beginTransaction();

			if (count($missingRequired) > 0) {
				$this->createCharacteristics($io, $device, $channel, $missingRequired, true);
			}

			if (count($missingOptional) > 0) {
				$question = new Console\Question\ConfirmationQuestion(
					$this->translator->translate('//homekit-connector.cmd.devices.questions.addCharacteristics'),
					false,
				);

				$add = (bool) $io->askQuestion($question);

				if ($add) {
					$this->createCharacteristics($io, $device, $channel, $missingOptional, false);
				}
			}

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(
				$this->translator->translate(
					'//homekit-connector.cmd.devices.messages.update.service.success',
					['name' => $channel->getName() ?? $channel->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'devices-cmd',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
				],
			);

			$io->success($this->translator->translate('//homekit-connector.cmd.devices.messages.update.service.error'));
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}

		$this->askCharacteristicAction($io, $channel);
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 */
	private function deleteService(Style\SymfonyStyle $io, Entities\HomeKitDevice $device): void
	{
		$channels = $this->getServicesList($device);

		if (count($channels) === 0) {
			$io->warning($this->translator->translate('//homekit-connector.cmd.devices.messages.noServices'));

			return;
		}

		$channel = $this->askWhichService($io, $device, $channels);

		if ($channel === null) {
			return;
		}

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$this->channelsManager->delete($channel);

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(
				$this->translator->translate(
					'//homekit-connector.cmd.devices.messages.remove.service.success',
					['name' => $channel->getName() ?? $channel->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'devices-cmd',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
				],
			);

			$io->success($this->translator->translate('//homekit-connector.cmd.devices.messages.remove.service.error'));
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}

		$findChannelsQuery = new Queries\FindChannels();
		$findChannelsQuery->forDevice($device);

		$channels = $this->channelsRepository->findAllBy($findChannelsQuery, Entities\HomeKitChannel::class);

		if (count($channels) > 0) {
			$this->askServiceAction($io, $device, true);
		}
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 */
	private function listServices(Style\SymfonyStyle $io, Entities\HomeKitDevice $device): void
	{
		$findChannelsQuery = new Queries\FindChannels();
		$findChannelsQuery->forDevice($device);

		$deviceChannels = $this->channelsRepository->findAllBy($findChannelsQuery, Entities\HomeKitChannel::class);
		usort(
			$deviceChannels,
			static function (DevicesEntities\Channels\Channel $a, DevicesEntities\Channels\Channel $b): int {
				if ($a->getIdentifier() === $b->getIdentifier()) {
					return $a->getName() <=> $b->getName();
				}

				return $a->getIdentifier() <=> $b->getIdentifier();
			},
		);

		$table = new Console\Helper\Table($io);
		$table->setHeaders([
			'#',
			'Name',
			'Type',
			'Characteristics',
		]);

		foreach ($deviceChannels as $index => $channel) {
			$findChannelPropertiesQuery = new DevicesQueries\FindChannelProperties();
			$findChannelPropertiesQuery->forChannel($channel);

			$table->addRow([
				$index + 1,
				$channel->getName() ?? $channel->getIdentifier(),
				$channel->getServiceType()->getValue(),
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

		$findChannelsQuery = new Queries\FindChannels();
		$findChannelsQuery->forDevice($device);

		$channels = $this->channelsRepository->findAllBy($findChannelsQuery, Entities\HomeKitChannel::class);

		if (count($channels) > 0) {
			$this->askServiceAction($io, $device, true);
		}
	}

	/**
	 * @param array<string> $characteristics
	 *
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws Nette\IOException
	 */
	private function createCharacteristics(
		Style\SymfonyStyle $io,
		Entities\HomeKitDevice $device,
		Entities\HomeKitChannel $channel,
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
				|| !is_string($characteristicMetadata->offsetGet('DataType'))
			) {
				throw new Exceptions\InvalidState('Characteristic definition is missing required attributes');
			}

			$dataType = MetadataTypes\DataType::get($characteristicMetadata->offsetGet('DataType'));

			$format = $this->askFormat($io, $characteristic);

			$question = new Console\Question\ConfirmationQuestion(
				$this->translator->translate('//homekit-connector.cmd.devices.questions.connectCharacteristic'),
				true,
			);

			$connect = (bool) $io->askQuestion($question);

			if ($connect) {
				$connectProperty = $this->askProperty($io);

				$format = $this->askFormat($io, $characteristic, $connectProperty);

				if ($connectProperty instanceof DevicesEntities\Devices\Properties\Dynamic) {
					$this->devicesPropertiesManager->create(Utils\ArrayHash::from([
						'entity' => DevicesEntities\Devices\Properties\Mapped::class,
						'parent' => $connectProperty,
						'identifier' => strtolower(strval(preg_replace('/(?<!^)[A-Z]/', '_$0', $characteristic))),
						'device' => $device,
						'dataType' => $dataType,
						'format' => $format,
					]));

				} elseif ($connectProperty instanceof DevicesEntities\Channels\Properties\Dynamic) {
					$this->channelsPropertiesManager->create(Utils\ArrayHash::from([
						'entity' => DevicesEntities\Channels\Properties\Mapped::class,
						'parent' => $connectProperty,
						'identifier' => strtolower(strval(preg_replace('/(?<!^)[A-Z]/', '_$0', $characteristic))),
						'channel' => $channel,
						'dataType' => $dataType,
						'format' => $format,
					]));
				}
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
					$this->translator->translate('//homekit-connector.cmd.base.questions.continue'),
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
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 */
	private function editCharacteristic(Style\SymfonyStyle $io, Entities\HomeKitChannel $channel): void
	{
		$properties = $this->getCharacteristicsList($channel);

		if (count($properties) === 0) {
			$io->warning($this->translator->translate('//homekit-connector.cmd.devices.messages.noCharacteristics'));

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
			|| !$characteristicMetadata->offsetExists('DataType')
		) {
			throw new Exceptions\InvalidState('Characteristic definition is missing required attributes');
		}

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$dataType = MetadataTypes\DataType::get($characteristicMetadata->offsetGet('DataType'));

			$format = $this->askFormat($io, $type);

			$question = new Console\Question\ConfirmationQuestion(
				$this->translator->translate('//homekit-connector.cmd.devices.questions.connectCharacteristic'),
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
				);

				$format = $this->askFormat($io, $type, $connectProperty);

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

					if ($connectProperty instanceof DevicesEntities\Devices\Properties\Dynamic) {
						$property = $this->devicesPropertiesManager->create(Utils\ArrayHash::from([
							'entity' => DevicesEntities\Devices\Properties\Mapped::class,
							'parent' => $connectProperty,
							'identifier' => $property->getIdentifier(),
							'device' => $channel->getDevice(),
							'dataType' => $dataType,
							'format' => $format,
						]));

					} elseif ($connectProperty instanceof DevicesEntities\Channels\Properties\Dynamic) {
						$property = $this->channelsPropertiesManager->create(Utils\ArrayHash::from([
							'entity' => DevicesEntities\Channels\Properties\Mapped::class,
							'parent' => $connectProperty,
							'identifier' => $property->getIdentifier(),
							'channel' => $channel,
							'dataType' => $dataType,
							'format' => $format,
						]));
					}
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

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(
				$this->translator->translate(
					'//homekit-connector.cmd.devices.messages.update.characteristic.success',
					['name' => $property->getName() ?? $property->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'devices-cmd',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
				],
			);

			$io->success(
				$this->translator->translate('//homekit-connector.cmd.devices.messages.update.characteristic.error'),
			);
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}

		$this->askCharacteristicAction($io, $channel);
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 */
	private function deleteCharacteristic(Style\SymfonyStyle $io, Entities\HomeKitChannel $channel): void
	{
		$properties = $this->getCharacteristicsList($channel);

		if (count($properties) === 0) {
			$io->warning($this->translator->translate('//homekit-connector.cmd.devices.messages.noCharacteristics'));

			return;
		}

		$property = $this->askWhichCharacteristic($io, $channel, $properties);

		if ($property === null) {
			return;
		}

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$this->channelsPropertiesManager->delete($property);

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(
				$this->translator->translate(
					'//homekit-connector.cmd.devices.messages.remove.characteristic.success',
					['name' => $property->getName() ?? $property->getIdentifier()],
				),
			);
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'An unhandled error occurred',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'devices-cmd',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
				],
			);

			$io->success(
				$this->translator->translate('//homekit-connector.cmd.devices.messages.remove.characteristic.error'),
			);
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}

		$findChannelPropertiesQuery = new DevicesQueries\FindChannelProperties();
		$findChannelPropertiesQuery->forChannel($channel);

		if (count($this->channelsPropertiesRepository->findAllBy($findChannelPropertiesQuery)) > 0) {
			$this->askCharacteristicAction($io, $channel);
		}
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 */
	private function listCharacteristics(Style\SymfonyStyle $io, Entities\HomeKitChannel $channel): void
	{
		$findPropertiesQuery = new DevicesQueries\FindChannelProperties();
		$findPropertiesQuery->forChannel($channel);

		$channelProperties = $this->channelsPropertiesRepository->findAllBy($findPropertiesQuery);
		usort(
			$channelProperties,
			static function (DevicesEntities\Channels\Properties\Property $a, DevicesEntities\Channels\Properties\Property $b): int {
				if ($a->getIdentifier() === $b->getIdentifier()) {
					return $a->getName() <=> $b->getName();
				}

				return $a->getIdentifier() <=> $b->getIdentifier();
			},
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
				$property->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_ENUM)
				&& $metadata->offsetExists($type)
				&& $metadata->offsetGet($type) instanceof Utils\ArrayHash
				&& $metadata->offsetGet($type)->offsetExists('ValidValues')
				&& $metadata->offsetGet($type)->offsetGet('ValidValues') instanceof Utils\ArrayHash
			) {
				$enumValue = array_search(
					intval(DevicesUtilities\ValueHelper::flattenValue($value)),
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

		$findChannelPropertiesQuery = new DevicesQueries\FindChannelProperties();
		$findChannelPropertiesQuery->forChannel($channel);

		if (count($this->channelsPropertiesRepository->findAllBy($findChannelPropertiesQuery)) > 0) {
			$this->askCharacteristicAction($io, $channel);
		}
	}

	private function askDeviceName(Style\SymfonyStyle $io, Entities\HomeKitDevice|null $device = null): string|null
	{
		$question = new Console\Question\Question(
			$this->translator->translate('//homekit-connector.cmd.devices.questions.provide.name'),
			$device?->getName(),
		);

		$name = $io->askQuestion($question);

		return strval($name) === '' ? null : strval($name);
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function askCategory(
		Style\SymfonyStyle $io,
		Entities\HomeKitDevice|null $device = null,
	): Types\AccessoryCategory
	{
		$categories = array_combine(
			array_values(Types\AccessoryCategory::getValues()),
			array_map(
				fn (Types\AccessoryCategory $category): string => $this->translator->translate(
					'//homekit-connector.cmd.base.category.' . $category->getValue(),
				),
				(array) Types\AccessoryCategory::getAvailableEnums(),
			),
		);
		$categories = array_filter(
			$categories,
			fn (string $category): bool => $category !== $this->translator->translate(
				'//homekit-connector.cmd.base.category.' . Types\AccessoryCategory::BRIDGE,
			)
		);
		asort($categories);

		$default = $device !== null ? array_search(
			$this->translator->translate(
				'//homekit-connector.cmd.base.category.' . $device->getAccessoryCategory()->getValue(),
			),
			array_values($categories),
			true,
		) : array_search(
			$this->translator->translate(
				'//homekit-connector.cmd.base.category.' . Types\AccessoryCategory::OTHER,
			),
			array_values($categories),
			true,
		);

		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//homekit-connector.cmd.devices.questions.select.category'),
			array_values($categories),
			$default,
		);
		$question->setErrorMessage(
			$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|int|null $answer) use ($categories): Types\AccessoryCategory {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (array_key_exists($answer, array_values($categories))) {
				$answer = array_values($categories)[$answer];
			}

			$category = array_search($answer, $categories, true);

			if ($category !== false && Types\AccessoryCategory::isValidValue($category)) {
				return Types\AccessoryCategory::get(intval($category));
			}

			throw new Exceptions\Runtime(
				sprintf($this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'), $answer),
			);
		});

		$answer = $io->askQuestion($question);
		assert($answer instanceof Types\AccessoryCategory);

		return $answer;
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 */
	private function askServiceAction(
		Style\SymfonyStyle $io,
		Entities\HomeKitDevice $device,
		bool $editMode = false,
	): void
	{
		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//homekit-connector.cmd.base.questions.whatToDo'),
			[
				0 => $this->translator->translate('//homekit-connector.cmd.devices.actions.createService'),
				1 => $this->translator->translate('//homekit-connector.cmd.devices.actions.updateService'),
				2 => $this->translator->translate('//homekit-connector.cmd.devices.actions.removeService'),
				3 => $this->translator->translate('//homekit-connector.cmd.devices.actions.listServices'),
				4 => $this->translator->translate('//homekit-connector.cmd.devices.actions.nothing'),
			],
			4,
		);

		$question->setErrorMessage(
			$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);

		$whatToDo = $io->askQuestion($question);

		if (
			$whatToDo === $this->translator->translate(
				'//homekit-connector.cmd.devices.actions.createService',
			)
			|| $whatToDo === '0'
		) {
			$this->createService($io, $device, $editMode);

		} elseif (
			$whatToDo === $this->translator->translate(
				'//homekit-connector.cmd.devices.actions.updateService',
			)
			|| $whatToDo === '1'
		) {
			$this->editService($io, $device);

		} elseif (
			$whatToDo === $this->translator->translate(
				'//homekit-connector.cmd.devices.actions.removeService',
			)
			|| $whatToDo === '2'
		) {
			$this->deleteService($io, $device);

		} elseif (
			$whatToDo === $this->translator->translate(
				'//homekit-connector.cmd.devices.actions.listServices',
			)
			|| $whatToDo === '3'
		) {
			$this->listServices($io, $device);
		}
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 */
	private function askCharacteristicAction(
		Style\SymfonyStyle $io,
		Entities\HomeKitChannel $channel,
	): void
	{
		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//homekit-connector.cmd.base.questions.whatToDo'),
			[
				0 => $this->translator->translate('//homekit-connector.cmd.devices.actions.updateCharacteristic'),
				1 => $this->translator->translate('//homekit-connector.cmd.devices.actions.removeCharacteristic'),
				2 => $this->translator->translate('//homekit-connector.cmd.devices.actions.listCharacteristics'),
				3 => $this->translator->translate('//homekit-connector.cmd.devices.actions.nothing'),
			],
			3,
		);

		$question->setErrorMessage(
			$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);

		$whatToDo = $io->askQuestion($question);

		if (
			$whatToDo === $this->translator->translate(
				'//homekit-connector.cmd.devices.actions.updateCharacteristic',
			)
			|| $whatToDo === '0'
		) {
			$this->editCharacteristic($io, $channel);

		} elseif (
			$whatToDo === $this->translator->translate(
				'//homekit-connector.cmd.devices.actions.removeCharacteristic',
			)
			|| $whatToDo === '1'
		) {
			$this->deleteCharacteristic($io, $channel);

		} elseif (
			$whatToDo === $this->translator->translate(
				'//homekit-connector.cmd.devices.actions.listCharacteristics',
			)
			|| $whatToDo === '2'
		) {
			$this->listCharacteristics($io, $channel);
		}
	}

	/**
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
	 */
	private function askServiceType(
		Style\SymfonyStyle $io,
		Entities\HomeKitDevice $device,
	): string
	{
		$findPropertyQuery = new DevicesQueries\FindDeviceProperties();
		$findPropertyQuery->forDevice($device);
		$findPropertyQuery->byIdentifier(Types\DevicePropertyIdentifier::CATEGORY);

		$category = $this->devicesPropertiesRepository->findOneBy(
			$findPropertyQuery,
			DevicesEntities\Devices\Properties\Variable::class,
		);

		if (!$category instanceof DevicesEntities\Devices\Properties\Variable) {
			throw new Exceptions\InvalidState('Device category is not configured');
		}

		if ($category->getValue() === Types\AccessoryCategory::OTHER) {
			$metadata = $this->loader->loadServices();

			$services = array_values(array_keys((array) $metadata));
		} else {
			$metadata = $this->loader->loadAccessories();

			if (!$metadata->offsetExists(strval(DevicesUtilities\ValueHelper::flattenValue($category->getValue())))) {
				throw new Exceptions\InvalidArgument(sprintf(
					'Definition for accessory category: %s was not found',
					strval(DevicesUtilities\ValueHelper::flattenValue($category->getValue())),
				));
			}

			$accessoryMetadata = $metadata->offsetGet(
				strval(DevicesUtilities\ValueHelper::flattenValue($category->getValue())),
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
			$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|null $answer) use ($services): string {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
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

		if (!$metadata->offsetExists(strval($service->getValue()))) {
			throw new Exceptions\InvalidArgument(sprintf(
				'Definition for service: %s was not found',
				strval($service->getValue()),
			));
		}

		$characteristics = array_values(array_diff($characteristics, $ignore));

		if (!$required) {
			$characteristics[] = $this->translator->translate('//homekit-connector.cmd.devices.answers.none');
		}

		$question = $required ? new Console\Question\ChoiceQuestion(
			$this->translator->translate('//homekit-connector.cmd.devices.questions.select.requiredCharacteristic'),
			$characteristics,
			0,
		) : new Console\Question\ChoiceQuestion(
			$this->translator->translate('//homekit-connector.cmd.devices.questions.select.optionalCharacteristic'),
			$characteristics,
			count($characteristics) - 1,
		);

		$question->setErrorMessage(
			$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|null $answer) use ($required, $characteristics): string|null {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
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
					$answer === $this->translator->translate('//homekit-connector.cmd.devices.answers.none')
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
	 * @throws DevicesExceptions\InvalidState
	 */
	private function askProperty(
		Style\SymfonyStyle $io,
		DevicesEntities\Devices\Properties\Dynamic|DevicesEntities\Channels\Properties\Dynamic|null $connectedProperty = null,
	): DevicesEntities\Devices\Properties\Dynamic|DevicesEntities\Channels\Properties\Dynamic|null
	{
		$devices = [];

		$connectedDevice = null;
		$connectedChannel = null;

		if ($connectedProperty instanceof DevicesEntities\Devices\Properties\Dynamic) {
			$connectedDevice = $connectedProperty->getDevice();

		} elseif ($connectedProperty instanceof DevicesEntities\Channels\Properties\Dynamic) {
			$connectedChannel = $connectedProperty->getChannel();
			$connectedDevice = $connectedProperty->getChannel()->getDevice();
		}

		$findDevicesQuery = new DevicesQueries\FindDevices();

		$systemDevices = $this->devicesRepository->findAllBy($findDevicesQuery);
		usort(
			$systemDevices,
			static fn (DevicesEntities\Devices\Device $a, DevicesEntities\Devices\Device $b): int => $a->getIdentifier() <=> $b->getIdentifier()
		);

		foreach ($systemDevices as $device) {
			if ($device instanceof Entities\HomeKitDevice) {
				continue;
			}

			$devices[$device->getId()->toString()] = $device->getIdentifier()
				// phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
				. ($device->getConnector()->getName() !== null ? ' [' . $device->getConnector()->getName() . ']' : '[' . $device->getConnector()->getIdentifier() . ']')
				. ($device->getName() !== null ? ' [' . $device->getName() . ']' : '');
		}

		if (count($devices) === 0) {
			$io->warning($this->translator->translate('//homekit-connector.cmd.devices.messages.noHardwareDevices'));

			return null;
		}

		$default = count($devices) === 1 ? 0 : null;

		if ($connectedDevice !== null) {
			foreach (array_values($devices) as $index => $value) {
				if (Utils\Strings::contains($value, $connectedDevice->getIdentifier())) {
					$default = $index;

					break;
				}
			}
		}

		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//homekit-connector.cmd.devices.questions.select.mappedDevice'),
			array_values($devices),
			$default,
		);
		$question->setErrorMessage(
			$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|null $answer) use ($devices): DevicesEntities\Devices\Device {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (array_key_exists($answer, array_values($devices))) {
				$answer = array_values($devices)[$answer];
			}

			$identifier = array_search($answer, $devices, true);

			if ($identifier !== false) {
				$findDeviceQuery = new DevicesQueries\FindDevices();
				$findDeviceQuery->byId(Uuid\Uuid::fromString($identifier));

				$device = $this->devicesRepository->findOneBy($findDeviceQuery);

				if ($device !== null) {
					return $device;
				}
			}

			throw new Exceptions\Runtime(
				sprintf(
					$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
					$answer,
				),
			);
		});

		$device = $io->askQuestion($question);
		assert($device instanceof DevicesEntities\Devices\Device);

		$default = 1;

		if ($connectedProperty !== null) {
			$default = $connectedProperty instanceof DevicesEntities\Devices\Properties\Dynamic ? 0 : 1;
		}

		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//homekit-connector.cmd.devices.questions.select.propertyType'),
			[
				$this->translator->translate('//homekit-connector.cmd.devices.answers.deviceProperty'),
				$this->translator->translate('//homekit-connector.cmd.devices.answers.channelProperty'),
			],
			$default,
		);
		$question->setErrorMessage(
			$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|int|null $answer): int {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (
				$answer === $this->translator->translate(
					'//homekit-connector.cmd.devices.answers.deviceProperty',
				)
				|| strval($answer) === '0'
			) {
				return 0;
			}

			if (
				$answer === $this->translator->translate(
					'//homekit-connector.cmd.devices.answers.channelProperty',
				)
				|| strval($answer) === '1'
			) {
				return 1;
			}

			throw new Exceptions\Runtime(
				sprintf(
					$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
					$answer,
				),
			);
		});

		$type = $io->askQuestion($question);
		assert(is_int($type));

		if ($type === 0) {
			$properties = [];

			$findDevicePropertiesQuery = new DevicesQueries\FindDeviceProperties();
			$findDevicePropertiesQuery->forDevice($device);

			$deviceProperties = $this->devicesPropertiesRepository->findAllBy(
				$findDevicePropertiesQuery,
				DevicesEntities\Devices\Properties\Dynamic::class,
			);
			usort(
				$deviceProperties,
				static function (DevicesEntities\Devices\Properties\Property $a, DevicesEntities\Devices\Properties\Property $b): int {
					if ($a->getIdentifier() === $b->getIdentifier()) {
						return $a->getName() <=> $b->getName();
					}

					return $a->getIdentifier() <=> $b->getIdentifier();
				},
			);

			foreach ($deviceProperties as $property) {
				if (!$property instanceof DevicesEntities\Devices\Properties\Dynamic) {
					continue;
				}

				$properties[$property->getIdentifier()] = sprintf(
					'%s%s',
					$property->getIdentifier(),
					($property->getName() !== null ? ' [' . $property->getName() . ']' : ''),
				);
			}

			$default = count($properties) === 1 ? 0 : null;

			if ($connectedProperty !== null) {
				foreach (array_values($properties) as $index => $value) {
					if (Utils\Strings::contains($value, $connectedProperty->getIdentifier())) {
						$default = $index;

						break;
					}
				}
			}

			$question = new Console\Question\ChoiceQuestion(
				$this->translator->translate('//homekit-connector.cmd.devices.questions.select.mappedDeviceProperty'),
				array_values($properties),
				$default,
			);
			$question->setErrorMessage(
				$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
			);
			$question->setValidator(
				function (string|null $answer) use ($device, $properties): DevicesEntities\Devices\Properties\Dynamic {
					if ($answer === null) {
						throw new Exceptions\Runtime(
							sprintf(
								$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
								$answer,
							),
						);
					}

					if (array_key_exists($answer, array_values($properties))) {
						$answer = array_values($properties)[$answer];
					}

					$identifier = array_search($answer, $properties, true);

					if ($identifier !== false) {
						$findPropertyQuery = new DevicesQueries\FindDeviceProperties();
						$findPropertyQuery->byIdentifier($identifier);
						$findPropertyQuery->forDevice($device);

						$property = $this->devicesPropertiesRepository->findOneBy(
							$findPropertyQuery,
							DevicesEntities\Devices\Properties\Dynamic::class,
						);

						if ($property !== null) {
							assert($property instanceof DevicesEntities\Devices\Properties\Dynamic);

							return $property;
						}
					}

					throw new Exceptions\Runtime(
						sprintf(
							$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
							$answer,
						),
					);
				},
			);

			$property = $io->askQuestion($question);
			assert($property instanceof DevicesEntities\Devices\Properties\Dynamic);

			return $property;
		} else {
			$channels = [];

			$findChannelsQuery = new DevicesQueries\FindChannels();
			$findChannelsQuery->forDevice($device);
			$findChannelsQuery->withProperties();

			$deviceChannels = $this->channelsRepository->findAllBy($findChannelsQuery);
			usort(
				$deviceChannels,
				static function (DevicesEntities\Channels\Channel $a, DevicesEntities\Channels\Channel $b): int {
					if ($a->getIdentifier() === $b->getIdentifier()) {
						return $a->getName() <=> $b->getName();
					}

					return $a->getIdentifier() <=> $b->getIdentifier();
				},
			);

			foreach ($deviceChannels as $channel) {
				$channels[$channel->getIdentifier()] = sprintf(
					'%s%s',
					$channel->getIdentifier(),
					($channel->getName() !== null ? ' [' . $channel->getName() . ']' : ''),
				);
			}

			$default = count($channels) === 1 ? 0 : null;

			if ($connectedChannel !== null) {
				foreach (array_values($channels) as $index => $value) {
					if (Utils\Strings::contains($value, $connectedChannel->getIdentifier())) {
						$default = $index;

						break;
					}
				}
			}

			$question = new Console\Question\ChoiceQuestion(
				$this->translator->translate('//homekit-connector.cmd.devices.questions.select.mappedDeviceChannel'),
				array_values($channels),
				$default,
			);
			$question->setErrorMessage(
				$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
			);
			$question->setValidator(
				function (string|null $answer) use ($device, $channels): DevicesEntities\Channels\Channel {
					if ($answer === null) {
						throw new Exceptions\Runtime(
							sprintf(
								$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
								$answer,
							),
						);
					}

					if (array_key_exists($answer, array_values($channels))) {
						$answer = array_values($channels)[$answer];
					}

					$identifier = array_search($answer, $channels, true);

					if ($identifier !== false) {
						$findChannelQuery = new DevicesQueries\FindChannels();
						$findChannelQuery->byIdentifier($identifier);
						$findChannelQuery->forDevice($device);

						$channel = $this->channelsRepository->findOneBy($findChannelQuery);

						if ($channel !== null) {
							return $channel;
						}
					}

					throw new Exceptions\Runtime(
						sprintf(
							$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
							$answer,
						),
					);
				},
			);

			$channel = $io->askQuestion($question);
			assert($channel instanceof DevicesEntities\Channels\Channel);

			$properties = [];

			$findDevicePropertiesQuery = new DevicesQueries\FindChannelProperties();
			$findDevicePropertiesQuery->forChannel($channel);

			$channelProperties = $this->channelsPropertiesRepository->findAllBy(
				$findDevicePropertiesQuery,
				DevicesEntities\Channels\Properties\Dynamic::class,
			);
			usort(
				$channelProperties,
				static function (DevicesEntities\Channels\Properties\Property $a, DevicesEntities\Channels\Properties\Property $b): int {
					if ($a->getIdentifier() === $b->getIdentifier()) {
						return $a->getName() <=> $b->getName();
					}

					return $a->getIdentifier() <=> $b->getIdentifier();
				},
			);

			foreach ($channelProperties as $property) {
				if (!$property instanceof DevicesEntities\Channels\Properties\Dynamic) {
					continue;
				}

				$properties[$property->getIdentifier()] = sprintf(
					'%s%s',
					$property->getIdentifier(),
					($property->getName() !== null ? ' [' . $property->getName() . ']' : ''),
				);
			}

			$default = count($properties) === 1 ? 0 : null;

			if ($connectedProperty !== null) {
				foreach (array_values($properties) as $index => $value) {
					if (Utils\Strings::contains($value, $connectedProperty->getIdentifier())) {
						$default = $index;

						break;
					}
				}
			}

			$question = new Console\Question\ChoiceQuestion(
				$this->translator->translate('//homekit-connector.cmd.devices.questions.select.mappedChannelProperty'),
				array_values($properties),
				$default,
			);
			$question->setErrorMessage(
				$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
			);
			$question->setValidator(
				function (string|null $answer) use ($channel, $properties): DevicesEntities\Channels\Properties\Dynamic {
					if ($answer === null) {
						throw new Exceptions\Runtime(
							sprintf(
								$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
								$answer,
							),
						);
					}

					if (array_key_exists($answer, array_values($properties))) {
						$answer = array_values($properties)[$answer];
					}

					$identifier = array_search($answer, $properties, true);

					if ($identifier !== false) {
						$findPropertyQuery = new DevicesQueries\FindChannelProperties();
						$findPropertyQuery->byIdentifier($identifier);
						$findPropertyQuery->forChannel($channel);

						$property = $this->channelsPropertiesRepository->findOneBy(
							$findPropertyQuery,
							DevicesEntities\Channels\Properties\Dynamic::class,
						);

						if ($property !== null) {
							assert($property instanceof DevicesEntities\Channels\Properties\Dynamic);

							return $property;
						}
					}

					throw new Exceptions\Runtime(
						sprintf(
							$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
							$answer,
						),
					);
				},
			);

			$property = $io->askQuestion($question);
			assert($property instanceof DevicesEntities\Channels\Properties\Dynamic);

			return $property;
		}
	}

	/**
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws Nette\IOException
	 */
	private function askFormat(
		Style\SymfonyStyle $io,
		string $characteristic,
		DevicesEntities\Devices\Properties\Dynamic|DevicesEntities\Channels\Properties\Dynamic|null $connectProperty = null,
	): MetadataValueObjects\NumberRangeFormat|MetadataValueObjects\StringEnumFormat|MetadataValueObjects\CombinedEnumFormat|null
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
			|| !is_string($characteristicMetadata->offsetGet('DataType'))
		) {
			throw new Exceptions\InvalidState('Characteristic definition is missing required attributes');
		}

		$dataType = MetadataTypes\DataType::get($characteristicMetadata->offsetGet('DataType'));

		$format = null;

		if (
			$characteristicMetadata->offsetExists('MinValue')
			|| $characteristicMetadata->offsetExists('MaxValue')
		) {
			$format = new MetadataValueObjects\NumberRangeFormat([
				$characteristicMetadata->offsetExists('MinValue') ? floatval(
					$characteristicMetadata->offsetGet('MinValue'),
				) : null,
				$characteristicMetadata->offsetExists('MaxValue') ? floatval(
					$characteristicMetadata->offsetGet('MaxValue'),
				) : null,
			]);
		}

		if (
			(
				$dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_ENUM)
				|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SWITCH)
				|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_BUTTON)
			)
			&& $characteristicMetadata->offsetExists('ValidValues')
			&& $characteristicMetadata->offsetGet('ValidValues') instanceof Utils\ArrayHash
		) {
			$format = new MetadataValueObjects\StringEnumFormat(
				array_values((array) $characteristicMetadata->offsetGet('ValidValues')),
			);

			if (
				$connectProperty !== null
				&& (
					$connectProperty->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_ENUM)
					|| $connectProperty->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_SWITCH)
					|| $connectProperty->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_BUTTON)
				) && (
					$connectProperty->getFormat() instanceof MetadataValueObjects\StringEnumFormat
					|| $connectProperty->getFormat() instanceof MetadataValueObjects\CombinedEnumFormat
				)
			) {
				$mappedFormat = [];

				foreach ($characteristicMetadata->offsetGet('ValidValues') as $name => $item) {
					$options = $connectProperty->getFormat() instanceof MetadataValueObjects\StringEnumFormat
						? $connectProperty->getFormat()->toArray()
						: array_map(
							static function (array $items): array|null {
								if ($items[0] === null) {
									return null;
								}

								return [
									$items[0]->getDataType(),
									strval($items[0]->getValue()),
								];
							},
							$connectProperty->getFormat()->getItems(),
						);

					$question = new Console\Question\ChoiceQuestion(
						$this->translator->translate(
							'//homekit-connector.cmd.devices.questions.select.valueMapping',
							['value' => $name],
						),
						array_map(
							static fn ($item): string|null => is_array($item) ? $item[1] : $item,
							$options,
						),
					);
					$question->setErrorMessage(
						$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
					);
					$question->setValidator(function (string|null $answer) use ($options): string|array {
						if ($answer === null) {
							throw new Exceptions\Runtime(
								sprintf(
									$this->translator->translate(
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
								static fn ($item): bool => is_array($item) ? $item[1] === $answer : $item === $answer
							));

							if (count($options) === 1 && $options[0] !== null) {
								return $options[0];
							}
						}

						throw new Exceptions\Runtime(
							sprintf(
								$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
								strval($answer),
							),
						);
					});

					$value = $io->askQuestion($question);
					assert(is_string($value) || is_int($value) || is_array($value));

					$valueDataType = is_array($value) ? strval($value[0]) : null;
					$value = is_array($value) ? $value[1] : $value;

					if (MetadataTypes\SwitchPayload::isValidValue($value)) {
						$valueDataType = MetadataTypes\DataTypeShort::DATA_TYPE_SWITCH;

					} elseif (MetadataTypes\ButtonPayload::isValidValue($value)) {
						$valueDataType = MetadataTypes\DataTypeShort::DATA_TYPE_BUTTON;

					} elseif (MetadataTypes\CoverPayload::isValidValue($value)) {
						$valueDataType = MetadataTypes\DataTypeShort::DATA_TYPE_COVER;
					}

					$mappedFormat[] = [
						[$valueDataType, strval($value)],
						[MetadataTypes\DataTypeShort::DATA_TYPE_UCHAR, strval($item)],
						[MetadataTypes\DataTypeShort::DATA_TYPE_UCHAR, strval($item)],
					];
				}

				$format = new MetadataValueObjects\CombinedEnumFormat($mappedFormat);
			}
		}

		return $format;
	}

	/**
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Nette\IOException
	 */
	private function provideCharacteristicValue(
		Style\SymfonyStyle $io,
		string $characteristic,
		bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayload|MetadataTypes\SwitchPayload|MetadataTypes\CoverPayload|null $value = null,
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
			|| !MetadataTypes\DataType::isValidValue($characteristicMetadata->offsetGet('DataType'))
		) {
			throw new Exceptions\InvalidState('Characteristic definition is missing required attributes');
		}

		$dataType = MetadataTypes\DataType::get($characteristicMetadata->offsetGet('DataType'));

		if (
			$characteristicMetadata->offsetExists('ValidValues')
			&& $characteristicMetadata->offsetGet('ValidValues') instanceof Utils\ArrayHash
		) {
			$options = array_combine(
				array_values((array) $characteristicMetadata->offsetGet('ValidValues')),
				array_keys((array) $characteristicMetadata->offsetGet('ValidValues')),
			);

			$question = new Console\Question\ChoiceQuestion(
				$this->translator->translate('//homekit-connector.cmd.devices.questions.select.value'),
				$options,
				$value !== null ? array_key_exists(
					strval(DevicesUtilities\ValueHelper::flattenValue($value)),
					$options,
				) : null,
			);
			$question->setErrorMessage(
				$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
			);
			$question->setValidator(function (string|int|null $answer) use ($options): string|int {
				if ($answer === null) {
					throw new Exceptions\Runtime(
						sprintf(
							$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
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
						$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			});

			$value = $io->askQuestion($question);
			assert(is_string($value) || is_numeric($value));

			return $value;
		}

		if ($dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_BOOLEAN)) {
			$question = new Console\Question\ChoiceQuestion(
				$this->translator->translate('//homekit-connector.cmd.devices.questions.select.value'),
				[
					$this->translator->translate('//homekit-connector.cmd.devices.answers.false'),
					$this->translator->translate('//homekit-connector.cmd.devices.answers.true'),
				],
				is_bool($value) ? ($value ? 0 : 1) : null,
			);
			$question->setErrorMessage(
				$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
			);
			$question->setValidator(function (string|int|null $answer): bool {
				if ($answer === null) {
					throw new Exceptions\Runtime(
						sprintf(
							$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
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
			$this->translator->translate('//homekit-connector.cmd.devices.questions.provide.value'),
			is_object($value) ? strval($value) : $value,
		);
		$question->setValidator(
			function (string|int|null $answer) use ($dataType, $minValue, $maxValue, $step): string|int|float {
				if ($answer === null) {
					throw new Exceptions\Runtime(
						sprintf(
							$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
							$answer,
						),
					);
				}

				if ($dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_STRING)) {
					return strval($answer);
				}

				if ($dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_FLOAT)) {
					if ($minValue !== null && floatval($answer) < $minValue) {
						throw new Exceptions\Runtime(
							sprintf(
								$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
								$answer,
							),
						);
					}

					if ($maxValue !== null && floatval($answer) > $maxValue) {
						throw new Exceptions\Runtime(
							sprintf(
								$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
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
								$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
								$answer,
							),
						);
					}

					return floatval($answer);
				}

				if (
					$dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_CHAR)
					|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_UCHAR)
					|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_SHORT)
					|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_USHORT)
					|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_INT)
					|| $dataType->equalsValue(MetadataTypes\DataType::DATA_TYPE_UINT)
				) {
					if ($minValue !== null && intval($answer) < $minValue) {
						throw new Exceptions\Runtime(
							sprintf(
								$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
								$answer,
							),
						);
					}

					if ($maxValue !== null && intval($answer) > $maxValue) {
						throw new Exceptions\Runtime(
							sprintf(
								$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
								$answer,
							),
						);
					}

					if ($step !== null && intval($answer) % $step !== 0) {
						throw new Exceptions\Runtime(
							sprintf(
								$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
								$answer,
							),
						);
					}

					return intval($answer);
				}

				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
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
	private function askWhichConnector(Style\SymfonyStyle $io): Entities\HomeKitConnector|null
	{
		$connectors = [];

		$findConnectorsQuery = new Queries\FindConnectors();

		$systemConnectors = $this->connectorsRepository->findAllBy(
			$findConnectorsQuery,
			Entities\HomeKitConnector::class,
		);
		usort(
			$systemConnectors,
			// phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
			static fn (DevicesEntities\Connectors\Connector $a, DevicesEntities\Connectors\Connector $b): int => $a->getIdentifier() <=> $b->getIdentifier()
		);

		foreach ($systemConnectors as $connector) {
			$connectors[$connector->getIdentifier()] = $connector->getIdentifier()
				. ($connector->getName() !== null ? ' [' . $connector->getName() . ']' : '');
		}

		if (count($connectors) === 0) {
			return null;
		}

		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//homekit-connector.cmd.devices.questions.select.connector'),
			array_values($connectors),
			count($connectors) === 1 ? 0 : null,
		);
		$question->setErrorMessage(
			$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|int|null $answer) use ($connectors): Entities\HomeKitConnector {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (array_key_exists($answer, array_values($connectors))) {
				$answer = array_values($connectors)[$answer];
			}

			$identifier = array_search($answer, $connectors, true);

			if ($identifier !== false) {
				$findConnectorQuery = new Queries\FindConnectors();
				$findConnectorQuery->byIdentifier($identifier);

				$connector = $this->connectorsRepository->findOneBy(
					$findConnectorQuery,
					Entities\HomeKitConnector::class,
				);

				if ($connector !== null) {
					return $connector;
				}
			}

			throw new Exceptions\Runtime(
				sprintf(
					$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
					$answer,
				),
			);
		});

		$connector = $io->askQuestion($question);
		assert($connector instanceof Entities\HomeKitConnector);

		return $connector;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 */
	private function askWhichDevice(
		Style\SymfonyStyle $io,
		Entities\HomeKitConnector $connector,
	): Entities\HomeKitDevice|null
	{
		$devices = [];

		$findDevicesQuery = new Queries\FindDevices();
		$findDevicesQuery->forConnector($connector);

		$connectorDevices = $this->devicesRepository->findAllBy($findDevicesQuery, Entities\HomeKitDevice::class);
		usort(
			$connectorDevices,
			static fn (DevicesEntities\Devices\Device $a, DevicesEntities\Devices\Device $b): int => $a->getIdentifier() <=> $b->getIdentifier()
		);

		foreach ($connectorDevices as $device) {
			$devices[$device->getIdentifier()] = $device->getIdentifier()
				. ($device->getName() !== null ? ' [' . $device->getName() . ']' : '');
		}

		if (count($devices) === 0) {
			return null;
		}

		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//homekit-connector.cmd.devices.questions.select.device'),
			array_values($devices),
			count($devices) === 1 ? 0 : null,
		);
		$question->setErrorMessage(
			$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);
		$question->setValidator(function (string|int|null $answer) use ($connector, $devices): Entities\HomeKitDevice {
			if ($answer === null) {
				throw new Exceptions\Runtime(
					sprintf(
						$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
						$answer,
					),
				);
			}

			if (array_key_exists($answer, array_values($devices))) {
				$answer = array_values($devices)[$answer];
			}

			$identifier = array_search($answer, $devices, true);

			if ($identifier !== false) {
				$findDeviceQuery = new Queries\FindDevices();
				$findDeviceQuery->byIdentifier($identifier);
				$findDeviceQuery->forConnector($connector);

				$device = $this->devicesRepository->findOneBy($findDeviceQuery, Entities\HomeKitDevice::class);

				if ($device !== null) {
					return $device;
				}
			}

			throw new Exceptions\Runtime(
				sprintf(
					$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
					$answer,
				),
			);
		});

		$device = $io->askQuestion($question);
		assert($device instanceof Entities\HomeKitDevice);

		return $device;
	}

	/**
	 * @param array<string, string> $channels
	 *
	 * @throws DevicesExceptions\InvalidState
	 */
	private function askWhichService(
		Style\SymfonyStyle $io,
		Entities\HomeKitDevice $device,
		array $channels,
	): Entities\HomeKitChannel|null
	{
		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//homekit-connector.cmd.devices.questions.select.service'),
			array_values($channels),
			count($channels) === 1 ? 0 : null,
		);
		$question->setErrorMessage(
			$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);

		$serviceIdentifier = array_search($io->askQuestion($question), $channels, true);

		if ($serviceIdentifier === false) {
			$io->error($this->translator->translate('//homekit-connector.cmd.devices.messages.serviceNotFound'));

			$this->logger->alert(
				'Could not read service identifier from console answer',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'devices-cmd',
				],
			);

			return null;
		}

		$findChannelQuery = new Queries\FindChannels();
		$findChannelQuery->forDevice($device);
		$findChannelQuery->byIdentifier($serviceIdentifier);

		$channel = $this->channelsRepository->findOneBy($findChannelQuery, Entities\HomeKitChannel::class);

		if ($channel === null) {
			$io->error($this->translator->translate('//homekit-connector.cmd.devices.messages.serviceNotFound'));

			$this->logger->alert(
				'Channel was not found',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'devices-cmd',
				],
			);

			return null;
		}

		return $channel;
	}

	/**
	 * @param array<string, string> $properties
	 *
	 * @throws DevicesExceptions\InvalidState
	 */
	private function askWhichCharacteristic(
		Style\SymfonyStyle $io,
		Entities\HomeKitChannel $channel,
		array $properties,
	): DevicesEntities\Channels\Properties\Variable|DevicesEntities\Channels\Properties\Mapped|null
	{
		$question = new Console\Question\ChoiceQuestion(
			$this->translator->translate('//homekit-connector.cmd.devices.questions.select.characteristic'),
			array_values($properties),
		);
		$question->setErrorMessage(
			$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
		);

		$characteristicIdentifier = array_search($io->askQuestion($question), $properties, true);

		if ($characteristicIdentifier === false) {
			$io->error($this->translator->translate('//homekit-connector.cmd.devices.messages.characteristicNotFound'));

			$this->logger->alert(
				'Could not read characteristic identifier from console answer',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'devices-cmd',
				],
			);

			return null;
		}

		$findPropertyQuery = new DevicesQueries\FindChannelProperties();
		$findPropertyQuery->forChannel($channel);
		$findPropertyQuery->byIdentifier($characteristicIdentifier);

		$property = $this->channelsPropertiesRepository->findOneBy($findPropertyQuery);

		if ($property === null) {
			$io->error($this->translator->translate('//homekit-connector.cmd.devices.messages.characteristicNotFound'));

			$this->logger->alert(
				'Property was not found',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'devices-cmd',
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
	private function getServicesList(Entities\HomeKitDevice $device): array
	{
		$channels = [];

		$findChannelsQuery = new Queries\FindChannels();
		$findChannelsQuery->forDevice($device);

		$deviceChannels = $this->channelsRepository->findAllBy($findChannelsQuery, Entities\HomeKitChannel::class);
		usort(
			$deviceChannels,
			static function (DevicesEntities\Channels\Channel $a, DevicesEntities\Channels\Channel $b): int {
				if ($a->getIdentifier() === $b->getIdentifier()) {
					return $a->getName() <=> $b->getName();
				}

				return $a->getIdentifier() <=> $b->getIdentifier();
			},
		);

		foreach ($deviceChannels as $channel) {
			$channels[$channel->getIdentifier()] = sprintf(
				'%s%s',
				$channel->getIdentifier(),
				($channel->getName() !== null ? ' [' . $channel->getName() . ']' : ''),
			);
		}

		return $channels;
	}

	/**
	 * @return array<string, string>
	 *
	 * @throws DevicesExceptions\InvalidState
	 */
	private function getCharacteristicsList(Entities\HomeKitChannel $channel): array
	{
		$properties = [];

		$findPropertiesQuery = new DevicesQueries\FindChannelProperties();
		$findPropertiesQuery->forChannel($channel);

		$channelProperties = $this->channelsPropertiesRepository->findAllBy($findPropertiesQuery);
		usort(
			$channelProperties,
			static function (DevicesEntities\Channels\Properties\Property $a, DevicesEntities\Channels\Properties\Property $b): int {
				if ($a->getIdentifier() === $b->getIdentifier()) {
					return $a->getName() <=> $b->getName();
				}

				return $a->getIdentifier() <=> $b->getIdentifier();
			},
		);

		foreach ($channelProperties as $property) {
			$properties[$property->getIdentifier()] = sprintf(
				'%s%s',
				$property->getIdentifier(),
				($property->getName() !== null ? ' [' . $property->getName() . ']' : ''),
			);
		}

		return $properties;
	}

	/**
	 * @throws Exceptions\Runtime
	 */
	private function getOrmConnection(): DBAL\Connection
	{
		$connection = $this->managerRegistry->getConnection();

		if ($connection instanceof DBAL\Connection) {
			return $connection;
		}

		throw new Exceptions\Runtime('Database connection could not be established');
	}

}
