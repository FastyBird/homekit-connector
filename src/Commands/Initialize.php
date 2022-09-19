<?php declare(strict_types = 1);

/**
 * Initialize.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Commands
 * @since          0.19.0
 *
 * @date           17.09.22
 */

namespace FastyBird\HomeKitConnector\Commands;

use Doctrine\DBAL;
use Doctrine\Persistence;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\HomeKitConnector\Entities;
use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata;
use Nette\Utils;
use Psr\Log;
use Symfony\Component\Console;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;
use Symfony\Component\Console\Style;
use Throwable;

/**
 * Connector initialize command
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Commands
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Initialize extends Console\Command\Command
{

	private const CHOICE_QUESTION_CREATE_CONNECTOR = 'Create new connector configuration';
	private const CHOICE_QUESTION_EDIT_CONNECTOR = 'Edit existing connector configuration';
	private const CHOICE_QUESTION_DELETE_CONNECTOR = 'Delete existing connector configuration';

	/** @var DevicesModuleModels\Connectors\IConnectorsRepository */
	private DevicesModuleModels\Connectors\IConnectorsRepository $connectorsRepository;

	/** @var DevicesModuleModels\Connectors\IConnectorsManager */
	private DevicesModuleModels\Connectors\IConnectorsManager $connectorsManager;

	/** @var DevicesModuleModels\Connectors\Controls\IControlsManager */
	private DevicesModuleModels\Connectors\Controls\IControlsManager $controlsManager;

	/** @var DevicesModuleModels\DataStorage\IConnectorsRepository */
	private DevicesModuleModels\DataStorage\IConnectorsRepository $connectorsDataStorageRepository;

	/** @var Persistence\ManagerRegistry */
	private Persistence\ManagerRegistry $managerRegistry;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param DevicesModuleModels\Connectors\IConnectorsRepository $connectorsRepository
	 * @param DevicesModuleModels\Connectors\IConnectorsManager $connectorsManager
	 * @param DevicesModuleModels\Connectors\Controls\IControlsManager $controlsManager
	 * @param DevicesModuleModels\DataStorage\IConnectorsRepository $connectorsDataStorageRepository
	 * @param Persistence\ManagerRegistry $managerRegistry
	 * @param Log\LoggerInterface|null $logger
	 * @param string|null $name
	 */
	public function __construct(
		DevicesModuleModels\Connectors\IConnectorsRepository $connectorsRepository,
		DevicesModuleModels\Connectors\IConnectorsManager $connectorsManager,
		DevicesModuleModels\Connectors\Controls\IControlsManager $controlsManager,
		DevicesModuleModels\DataStorage\IConnectorsRepository $connectorsDataStorageRepository,
		Persistence\ManagerRegistry $managerRegistry,
		?Log\LoggerInterface $logger = null,
		?string $name = null
	) {
		$this->connectorsRepository = $connectorsRepository;
		$this->connectorsManager = $connectorsManager;
		$this->controlsManager = $controlsManager;

		$this->connectorsDataStorageRepository = $connectorsDataStorageRepository;

		$this->managerRegistry = $managerRegistry;

		$this->logger = $logger ?? new Log\NullLogger();

		parent::__construct($name);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function configure(): void
	{
		$this
			->setName('fb:homekit-connector:initialize')
			->setDescription('HomeKit connector initialization')
			->setDefinition(
				new Input\InputDefinition([
					new Input\InputOption('no-confirm', null, Input\InputOption::VALUE_NONE, 'Do not ask for any confirmation'),
				])
			);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBAL\Exception
	 */
	protected function execute(Input\InputInterface $input, Output\OutputInterface $output): int
	{
		$io = new Style\SymfonyStyle($input, $output);

		$io->title('HomeKit connector - initialization');

		$io->note('This action will create|update connector configuration.');

		if (!$input->getOption('no-confirm')) {
			$question = new Console\Question\ConfirmationQuestion(
				'Would you like to continue?',
				false
			);

			$continue = $io->askQuestion($question);

			if (!$continue) {
				return Console\Command\Command::SUCCESS;
			}
		}

		$question = new Console\Question\ChoiceQuestion(
			'What would you like to do?',
			[
				0 => self::CHOICE_QUESTION_CREATE_CONNECTOR,
				1 => self::CHOICE_QUESTION_EDIT_CONNECTOR,
				2 => self::CHOICE_QUESTION_DELETE_CONNECTOR,
			]
		);

		$question->setErrorMessage('Selected answer: "%s" is not valid.');

		$whatToDo = $io->askQuestion($question);

		if ($whatToDo === self::CHOICE_QUESTION_CREATE_CONNECTOR) {
			$this->createNewConfiguration($io);

		} elseif ($whatToDo === self::CHOICE_QUESTION_EDIT_CONNECTOR) {
			$this->editExistingConfiguration($io);

		} elseif ($whatToDo === self::CHOICE_QUESTION_DELETE_CONNECTOR) {
			$this->deleteExistingConfiguration($io);
		}

		return Console\Command\Command::SUCCESS;
	}

	/**
	 * @param Style\SymfonyStyle $io
	 *
	 * @return void
	 *
	 * @throws DBAL\Exception
	 */
	private function createNewConfiguration(Style\SymfonyStyle $io): void
	{
		$question = new Console\Question\Question('Provide connector identifier');

		$question->setValidator(function ($answer) {
			if ($answer !== null && $this->connectorsDataStorageRepository->findByIdentifier($answer) !== null) {
				throw new Exceptions\Runtime('This identifier is already used');
			}

			return $answer;
		});

		$identifier = $io->askQuestion($question);

		if ($identifier === '' || $identifier === null) {
			$identifierPattern = 'homekit-%d';

			for ($i = 1; $i <= 100; $i++) {
				$identifier = sprintf($identifierPattern, $i);

				if ($this->connectorsDataStorageRepository->findByIdentifier($identifier) === null) {
					break;
				}
			}
		}

		if ($identifier === '') {
			$io->error('Connector identifier have to provided');

			return;
		}

		$question = new Console\Question\Question('Provide connector name');

		$name = $io->askQuestion($question);

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$connector = $this->connectorsManager->create(Utils\ArrayHash::from([
				'entity'     => Entities\HomeKitConnector::class,
				'identifier' => $identifier,
				'name'       => $name === '' ? null : $name,
			]));

			$this->controlsManager->create(Utils\ArrayHash::from([
				'name'      => Types\ConnectorControlName::NAME_REBOOT,
				'connector' => $connector,
			]));

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(sprintf(
				'New connector "%s" was successfully created',
				$connector->getName() ?? $connector->getIdentifier()
			));
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error('An unhandled error occurred', [
				'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type'      => 'initialize-cmd',
				'exception' => [
					'message' => $ex->getMessage(),
					'code'    => $ex->getCode(),
				],
			]);

			$io->error('Something went wrong, connector could not be created. Error was logged.');
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}
	}

	/**
	 * @param Style\SymfonyStyle $io
	 *
	 * @return void
	 *
	 * @throws DBAL\Exception
	 */
	private function editExistingConfiguration(Style\SymfonyStyle $io): void
	{
		$io->newLine();

		$connectors = [];

		foreach ($this->connectorsDataStorageRepository as $connector) {
			if ($connector->getType() !== Entities\HomeKitConnector::CONNECTOR_TYPE) {
				continue;
			}

			$connectors[$connector->getIdentifier()] = $connector->getIdentifier() . ($connector->getName() ? ' [' . $connector->getName() . ']' : '');
		}

		if (count($connectors) === 0) {
			$io->warning('No HomeKit connectors registered in system');

			$question = new Console\Question\ConfirmationQuestion(
				'Would you like to create new HomeKit connector configuration?',
				false
			);

			$continue = $io->askQuestion($question);

			if ($continue) {
				$this->createNewConfiguration($io);
			}

			return;
		}

		$question = new Console\Question\ChoiceQuestion(
			'Please select connector to configure',
			array_values($connectors)
		);

		$question->setErrorMessage('Selected connector: "%s" is not valid.');

		$connectorIdentifier = array_search($io->askQuestion($question), $connectors);

		if ($connectorIdentifier === false) {
			$io->error('Something went wrong, connector could not be loaded');

			$this->logger->alert('Connector identifier was not able to get from answer', [
				'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type'   => 'initialize-cmd',
			]);

			return;
		}

		$findConnectorQuery = new DevicesModuleQueries\FindConnectorsQuery();
		$findConnectorQuery->byIdentifier($connectorIdentifier);

		$connector = $this->connectorsRepository->findOneBy($findConnectorQuery);

		if ($connector === null) {
			$io->error('Something went wrong, connector could not be loaded');

			$this->logger->alert('Connector was not found', [
				'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type'   => 'initialize-cmd',
			]);

			return;
		}

		$question = new Console\Question\Question('Provide connector name', $connector->getName());

		$name = $io->askQuestion($question);

		$enabled = $connector->isEnabled();

		if ($connector->isEnabled()) {
			$question = new Console\Question\ConfirmationQuestion(
				'Do you want to disable connector?',
				false
			);

			if ($io->askQuestion($question)) {
				$enabled = false;
			}
		} else {
			$question = new Console\Question\ConfirmationQuestion(
				'Do you want to enable connector?',
				false
			);

			if ($io->askQuestion($question)) {
				$enabled = true;
			}
		}

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$connector = $this->connectorsManager->update($connector, Utils\ArrayHash::from([
				'name'    => $name === '' ? null : $name,
				'enabled' => $enabled,
			]));

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(sprintf(
				'Connector "%s" was successfully updated',
				$connector->getName() ?? $connector->getIdentifier()
			));
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error('An unhandled error occurred', [
				'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type'      => 'initialize-cmd',
				'exception' => [
					'message' => $ex->getMessage(),
					'code'    => $ex->getCode(),
				],
			]);

			$io->error('Something went wrong, connector could not be updated. Error was logged.');
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}
	}

	/**
	 * @param Style\SymfonyStyle $io
	 *
	 * @return void
	 *
	 * @throws DBAL\Exception
	 */
	private function deleteExistingConfiguration(Style\SymfonyStyle $io): void
	{
		$io->newLine();

		$connectors = [];

		foreach ($this->connectorsDataStorageRepository as $connector) {
			if ($connector->getType() !== Entities\HomeKitConnector::CONNECTOR_TYPE) {
				continue;
			}

			$connectors[$connector->getIdentifier()] = $connector->getIdentifier() . ($connector->getName() ? ' [' . $connector->getName() . ']' : '');
		}

		if (count($connectors) === 0) {
			$io->info('No HomeKit connectors registered in system');

			return;
		}

		$question = new Console\Question\ChoiceQuestion(
			'Please select connector to remove',
			array_values($connectors)
		);

		$question->setErrorMessage('Selected connector: "%s" is not valid.');

		$connectorIdentifier = array_search($io->askQuestion($question), $connectors);

		if ($connectorIdentifier === false) {
			$io->error('Something went wrong, connector could not be loaded');

			$this->logger->alert('Connector identifier was not able to get from answer', [
				'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type'   => 'initialize-cmd',
			]);

			return;
		}

		$findConnectorQuery = new DevicesModuleQueries\FindConnectorsQuery();
		$findConnectorQuery->byIdentifier($connectorIdentifier);

		$connector = $this->connectorsRepository->findOneBy($findConnectorQuery);

		if ($connector === null) {
			$io->error('Something went wrong, connector could not be loaded');

			$this->logger->alert('Connector was not found', [
				'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type'   => 'initialize-cmd',
			]);

			return;
		}

		$question = new Console\Question\ConfirmationQuestion(
			'Would you like to continue?',
			false
		);

		$continue = $io->askQuestion($question);

		if (!$continue) {
			return;
		}

		try {
			// Start transaction connection to the database
			$this->getOrmConnection()->beginTransaction();

			$this->connectorsManager->delete($connector);

			// Commit all changes into database
			$this->getOrmConnection()->commit();

			$io->success(sprintf(
				'Connector "%s" was successfully removed',
				$connector->getName() ?? $connector->getIdentifier()
			));
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error('An unhandled error occurred', [
				'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type'      => 'initialize-cmd',
				'exception' => [
					'message' => $ex->getMessage(),
					'code'    => $ex->getCode(),
				],
			]);

			$io->error('Something went wrong, connector could not be removed. Error was logged.');
		} finally {
			// Revert all changes when error occur
			if ($this->getOrmConnection()->isTransactionActive()) {
				$this->getOrmConnection()->rollBack();
			}
		}
	}

	/**
	 * @return DBAL\Connection
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