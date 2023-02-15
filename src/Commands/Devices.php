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

use Doctrine\DBAL;
use Doctrine\Persistence;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use Nette\Localization;
use Nette\Utils;
use Psr\Log;
use Symfony\Component\Console;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;
use Symfony\Component\Console\Style;
use Throwable;
use function array_combine;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_search;
use function array_values;
use function asort;
use function assert;
use function count;
use function intval;
use function sprintf;
use function strval;
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

	private Log\LoggerInterface $logger;

	public function __construct(
		private readonly DevicesModels\Connectors\ConnectorsRepository $connectorsRepository,
		private readonly DevicesModels\Devices\DevicesRepository $devicesRepository,
		private readonly DevicesModels\Devices\DevicesManager $devicesManager,
		private readonly DevicesModels\Devices\Properties\PropertiesManager $devicesPropertiesManager,
		private readonly Persistence\ManagerRegistry $managerRegistry,
		private readonly Localization\Translator $translator,
		Log\LoggerInterface|null $logger = null,
		string|null $name = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();

		parent::__construct($name);
	}

	/**
	 * @throws Console\Exception\InvalidArgumentException
	 */
	protected function configure(): void
	{
		$this
			->setName(self::NAME)
			->setDescription('HomeKit devices management')
			->setDefinition(
				new Input\InputDefinition([
					new Input\InputOption(
						'no-confirm',
						null,
						Input\InputOption::VALUE_NONE,
						'Do not ask for any confirmation',
					),
				]),
			);
	}

	/**
	 * @throws Console\Exception\InvalidArgumentException
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	protected function execute(Input\InputInterface $input, Output\OutputInterface $output): int
	{
		$io = new Style\SymfonyStyle($input, $output);

		$io->title($this->translator->translate('//homekit-connector.cmd.devices.title'));

		$io->note($this->translator->translate('//homekit-connector.cmd.devices.subtitle'));

		if ($input->getOption('no-confirm') === false) {
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
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function createNewDevice(Style\SymfonyStyle $io, Entities\HomeKitConnector $connector): void
	{
		$question = new Console\Question\Question(
			$this->translator->translate('//homekit-connector.cmd.devices.questions.provide.identifier'),
		);

		$question->setValidator(function (string|null $answer) {
			if ($answer !== '' && $answer !== null) {
				$findDeviceQuery = new DevicesQueries\FindDevices();
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

				$findDeviceQuery = new DevicesQueries\FindDevices();
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
				'identifier' => Types\DevicePropertyIdentifier::IDENTIFIER_CATEGORY,
				'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
				'value' => $category->getValue(),
				'device' => $device,
			]));

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(
				$this->translator->translate(
					'//homekit-connector.cmd.devices.messages.create.success',
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
					'group' => 'cmd',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
				],
			);

			$io->error($this->translator->translate('//homekit-connector.cmd.devices.messages.create.error'));

			return;
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
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
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

		$categoryProperty = $device->findProperty(Types\DevicePropertyIdentifier::IDENTIFIER_CATEGORY);

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
					'identifier' => Types\DevicePropertyIdentifier::IDENTIFIER_CATEGORY,
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
					'//homekit-connector.cmd.devices.messages.update.success',
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
					'group' => 'cmd',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
				],
			);

			$io->error($this->translator->translate('//homekit-connector.cmd.devices.messages.update.error'));
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
					'//homekit-connector.cmd.devices.messages.remove.success',
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
					'group' => 'cmd',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
				],
			);

			$io->error($this->translator->translate('//homekit-connector.cmd.devices.messages.remove.error'));
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
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
	 * @throws DevicesExceptions\InvalidState
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
				'//homekit-connector.cmd.base.category.' . Types\AccessoryCategory::CATEGORY_BRIDGE,
			)
		);
		asort($categories);

		$default = $device !== null ? array_search(
			$this->translator->translate(
				'//homekit-connector.cmd.base.category.' . $device->getCategory()->getValue(),
			),
			array_values($categories),
			true,
		) : array_search(
			$this->translator->translate(
				'//homekit-connector.cmd.base.category.' . Types\AccessoryCategory::CATEGORY_OTHER,
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

			if (array_key_exists(intval($answer), array_values($categories))) {
				$answer = array_values($categories)[intval($answer)];
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
	 * @throws DevicesExceptions\InvalidState
	 */
	private function askWhichConnector(Style\SymfonyStyle $io): Entities\HomeKitConnector|null
	{
		$connectors = [];

		$findConnectorsQuery = new DevicesQueries\FindConnectors();

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
			assert($connector instanceof Entities\HomeKitConnector);

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

			if (array_key_exists(intval($answer), array_values($connectors))) {
				$answer = array_values($connectors)[intval($answer)];
			}

			$identifier = array_search($answer, $connectors, true);

			if ($identifier !== false) {
				$findConnectorQuery = new DevicesQueries\FindConnectors();
				$findConnectorQuery->byIdentifier($identifier);

				$connector = $this->connectorsRepository->findOneBy(
					$findConnectorQuery,
					Entities\HomeKitConnector::class,
				);
				assert($connector instanceof Entities\HomeKitConnector || $connector === null);

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

		$findDevicesQuery = new DevicesQueries\FindDevices();
		$findDevicesQuery->forConnector($connector);

		$connectorDevices = $this->devicesRepository->findAllBy($findDevicesQuery, Entities\HomeKitDevice::class);
		usort(
			$connectorDevices,
			static fn (DevicesEntities\Devices\Device $a, DevicesEntities\Devices\Device $b): int => $a->getIdentifier() <=> $b->getIdentifier()
		);

		foreach ($connectorDevices as $device) {
			assert($device instanceof Entities\HomeKitDevice);

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

			if (array_key_exists(intval($answer), array_values($devices))) {
				$answer = array_values($devices)[intval($answer)];
			}

			$identifier = array_search($answer, $devices, true);

			if ($identifier !== false) {
				$findDeviceQuery = new DevicesQueries\FindDevices();
				$findDeviceQuery->byIdentifier($identifier);
				$findDeviceQuery->forConnector($connector);

				$device = $this->devicesRepository->findOneBy($findDeviceQuery, Entities\HomeKitDevice::class);
				assert($device instanceof Entities\HomeKitDevice || $device === null);

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
	 * @throws Exceptions\Runtime
	 */
	private function getOrmConnection(): DBAL\Connection
	{
		$connection = $this->managerRegistry->getConnection();

		if ($connection instanceof DBAL\Connection) {
			return $connection;
		}

		throw new Exceptions\Runtime('Entity manager could not be loaded');
	}

}
