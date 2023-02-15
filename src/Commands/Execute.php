<?php declare(strict_types = 1);

/**
 * Execute.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Commands
 * @since          1.0.0
 *
 * @date           17.09.22
 */

namespace FastyBird\Connector\HomeKit\Commands;

use Endroid\QrCode;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Module\Devices\Commands as DevicesCommands;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use Nette\Localization;
use Ramsey\Uuid;
use Symfony\Component\Console;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output;
use Symfony\Component\Console\Style;
use function array_key_exists;
use function array_key_first;
use function array_search;
use function array_values;
use function assert;
use function count;
use function intval;
use function is_string;
use function sprintf;
use function usort;

/**
 * Connector execute command
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Commands
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Execute extends Console\Command\Command
{

	public const NAME = 'fb:homekit-connector:execute';

	public function __construct(
		private readonly DevicesModels\Connectors\ConnectorsRepository $connectorsRepository,
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
			->setDescription('HomeKit connector service')
			->setDefinition(
				new Input\InputDefinition([
					new Input\InputOption(
						'connector',
						'c',
						Input\InputOption::VALUE_OPTIONAL,
						'Run devices module connector',
						true,
					),
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
	 * @throws Console\Exception\ExceptionInterface
	 * @throws Console\Exception\InvalidArgumentException
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidData
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Logic
	 * @throws MetadataExceptions\MalformedInput
	 */
	protected function execute(Input\InputInterface $input, Output\OutputInterface $output): int
	{
		$symfonyApp = $this->getApplication();

		if ($symfonyApp === null) {
			return Console\Command\Command::FAILURE;
		}

		$io = new Style\SymfonyStyle($input, $output);

		$io->title($this->translator->translate('//homekit-connector.cmd.execute.title'));

		$io->note($this->translator->translate('//homekit-connector.cmd.execute.subtitle'));

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

		if (
			$input->hasOption('connector')
			&& is_string($input->getOption('connector'))
			&& $input->getOption('connector') !== ''
		) {
			$connectorId = $input->getOption('connector');

			$findConnectorQuery = new DevicesQueries\FindConnectors();

			if (Uuid\Uuid::isValid($connectorId)) {
				$findConnectorQuery->byId(Uuid\Uuid::fromString($connectorId));
			} else {
				$findConnectorQuery->byIdentifier($connectorId);
			}

			$connector = $this->connectorsRepository->findOneBy($findConnectorQuery, Entities\HomeKitConnector::class);

			if ($connector === null) {
				$io->warning(
					$this->translator->translate('//homekit-connector.cmd.execute.messages.connector.notFound'),
				);

				return Console\Command\Command::FAILURE;
			}
		} else {
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
				$io->warning($this->translator->translate('//homekit-connector.cmd.execute.messages.noConnectors'));

				return Console\Command\Command::SUCCESS;
			}

			if (count($connectors) === 1) {
				$connectorIdentifier = array_key_first($connectors);

				$findConnectorQuery = new DevicesQueries\FindConnectors();
				$findConnectorQuery->byIdentifier($connectorIdentifier);

				$connector = $this->connectorsRepository->findOneBy(
					$findConnectorQuery,
					Entities\HomeKitConnector::class,
				);

				if ($connector === null) {
					$io->warning(
						$this->translator->translate('//homekit-connector.cmd.execute.messages.connector.notFound'),
					);

					return Console\Command\Command::FAILURE;
				}

				if ($input->getOption('no-confirm') === false) {
					$question = new Console\Question\ConfirmationQuestion(
						$this->translator->translate(
							'//homekit-connector.cmd.execute.questions.execute',
							['connector' => $connector->getName() ?? $connector->getIdentifier()],
						),
						false,
					);

					if ($io->askQuestion($question) === false) {
						return Console\Command\Command::SUCCESS;
					}
				}
			} else {
				$question = new Console\Question\ChoiceQuestion(
					$this->translator->translate('//homekit-connector.cmd.execute.questions.select.connector'),
					array_values($connectors),
				);
				$question->setErrorMessage(
					$this->translator->translate('//homekit-connector.cmd.base.messages.answerNotValid'),
				);
				$question->setValidator(
					function (string|int|null $answer) use ($connectors): Entities\HomeKitConnector {
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
					},
				);

				$connector = $io->askQuestion($question);
				assert($connector instanceof Entities\HomeKitConnector);
			}
		}

		assert($connector instanceof Entities\HomeKitConnector);

		if (!$connector->isEnabled()) {
			$io->warning($this->translator->translate('//homekit-connector.cmd.execute.messages.connector.disabled'));

			return Console\Command\Command::SUCCESS;
		}

		$qrCode = QrCode\Builder\Builder::create()
			->writer(new QrCode\Writer\ConsoleWriter())
			->data($connector->getXhmUri())
			->encoding(new QrCode\Encoding\Encoding('UTF-8'))
			->errorCorrectionLevel(new QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh())
			->labelText($connector->getPinCode())
			->labelAlignment(new QrCode\Label\Alignment\LabelAlignmentCenter())
			->validateResult(false)
			->build();

		$io->note(
			$this->translator->translate(
				'//homekit-connector.cmd.execute.messages.uriPath',
				['path' => $connector->getXhmUri()],
			),
		);

		$io->info($this->translator->translate('//homekit-connector.cmd.execute.messages.scanCode'));

		$io->writeln($qrCode->getString());

		$serviceCmd = $symfonyApp->find(DevicesCommands\Connector::NAME);

		$io->info(
			$this->translator->translate(
				'//homekit-connector.cmd.execute.messages.pinCode',
				['code' => $connector->getPinCode()],
			),
		);

		$result = $serviceCmd->run(new Input\ArrayInput([
			'--connector' => $connector->getPlainId(),
			'--no-confirm' => true,
			'--quiet' => true,
		]), $output);

		if ($result !== Console\Command\Command::SUCCESS) {
			$io->error($this->translator->translate('//homekit-connector.cmd.execute.messages.error'));

			return Console\Command\Command::FAILURE;
		}

		return Console\Command\Command::SUCCESS;
	}

}
