<?php declare(strict_types = 1);

/**
 * Mdns.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Clients
 * @since          0.19.0
 *
 * @date           17.09.22
 */

namespace FastyBird\HomeKitConnector\Clients;

use FastyBird\HomeKitConnector\Clients;
use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata;
use FastyBird\Metadata\Entities as MetadataEntities;
use Nette;
use Psr\Log;
use React\Datagram;
use React\Dns;
use React\EventLoop;
use Throwable;

/**
 * mDNS connector discovery client
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Mdns implements Client
{

	use Nette\SmartObject;

	private const DNS_ADDRESS = '224.0.0.251';
	private const DNS_PORT = 5353;

	/** @var Array<string, Array<int, Array<int, Clients\Mdns\ResourceRecord[]>>> */
	private $resourceRecords = [];

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var Dns\Protocol\Parser */
	private Dns\Protocol\Parser $parser;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/** @var Datagram\SocketInterface|null */
	private ?Datagram\SocketInterface $server = null;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		EventLoop\LoopInterface $eventLoop,
		?Log\LoggerInterface $logger = null
	) {
		$this->connector = $connector;
		$this->eventLoop = $eventLoop;

		$this->logger = $logger ?? new Log\NullLogger();

		$this->parser = new Dns\Protocol\Parser();
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect(): void
	{
		$factory = new Datagram\Factory($this->eventLoop);

		$factory->createServer(self::DNS_ADDRESS . ':' . self::DNS_PORT)
			->then(function (Datagram\Socket $server): void {
				$server->on('message', function (string $message, string $address): void {
					$request = $this->parser->parseMessage($message);

					foreach ($request->answers as $answer) {
						var_dump($answer->name);
						var_dump($answer->type);
						var_dump($answer->data);
					}
					return;

					try {
						$responseHeader = new Clients\Mdns\Header(
							$request->getHeader()->getId(),
							true,
							$request->getHeader()->getOpcode(),
							$request->getQuestions() !== [],
							$request->getHeader()->isTruncated(),
							$request->getHeader()->isRecursionDesired(),
							false,
							$request->getHeader()->getZ(),
							$request->getHeader()->getRcode(),
							$request->getHeader()->getQuestionCount(),
							$request->getHeader()->getAnswerCount(),
							$request->getHeader()->getNameServerCount(),
							$request->getHeader()->getAdditionalRecordsCount(),
						);

						$additional = $this->needsAdditionalRecords($request);

						$response = new Clients\Mdns\Message(
							$responseHeader,
							$request->getQuestions(),
							$this->getAnswer($request->getQuestions()),
							$request->getAuthoritatives(),
							$additional,
						);

					} catch (Exceptions\UnsupportedType) {
						$responseHeader = new Clients\Mdns\Header(
							$request->getHeader()->getId(),
							true,
							$request->getHeader()->getOpcode(),
							$request->getQuestions() !== [],
							$request->getHeader()->isTruncated(),
							$request->getHeader()->isRecursionDesired(),
							false,
							$request->getHeader()->getZ(),
							Clients\Mdns\Header::RCODE_NOT_IMPLEMENTED,
							$request->getHeader()->getQuestionCount(),
							$request->getHeader()->getAnswerCount(),
							$request->getHeader()->getNameServerCount(),
							$request->getHeader()->getAdditionalRecordsCount(),
						);

						$response = new Clients\Mdns\Message(
							$responseHeader,
							$request->getQuestions(),
							[],
							$request->getAuthoritatives(),
							$request->getAdditionals(),
						);
					}

					$this->server?->send(
						Clients\Mdns\Encoder::encodeMessage($response),
						$address
					);
				});

				$server->on('error', function (Throwable $ex): void {
					$this->logger->error(
						'An error occurred during server handling',
						[
							'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
							'type'      => 'mdns-client',
							'exception' => [
								'message' => $ex->getMessage(),
								'code'    => $ex->getCode(),
							],
						]
					);
				});

				$server->on('close', function (): void {
					$this->logger->info(
						'Server was closed',
						[
							'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
							'type'   => 'mdns-client',
						]
					);
				});
			})
			->otherwise(function (Throwable $ex): void {
				$this->logger->error(
					'Could not create mDNS discovery server',
					[
						'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
						'type'      => 'mdns-client',
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
					]
				);
			});
	}

	/**
	 * {@inheritDoc}
	 */
	public function disconnect(): void
	{
		$this->server?->close();
	}

	/**
	 * {@inheritDoc}
	 */
	public function isConnected(): bool
	{
		return $this->server !== null;
	}

	public function writeDeviceControl(MetadataEntities\Actions\IActionDeviceControlEntity $action): void
	{
		// TODO: Implement writeDeviceControl() method.
	}

	public function writeChannelControl(MetadataEntities\Actions\IActionChannelControlEntity $action): void
	{
		// TODO: Implement writeChannelControl() method.
	}

	/**
	 * Populate the additional records of a message if required
	 *
	 * @param Clients\Mdns\Message $message
	 *
	 * @return Clients\Mdns\ResourceRecord[]
	 */
	private function needsAdditionalRecords(Clients\Mdns\Message $message): array
	{
		$additional = [];

		foreach ($message->getAnswers() as $answer) {
			$name = null;

			switch ($answer->getType()) {
				case Types\ResourceRecordType::TYPE_NS:
					$name = $answer->getRdata();
					break;

				case Types\ResourceRecordType::TYPE_MX:
					if (is_array($answer->getRdata()) && array_key_exists('exchange', $answer->getRdata())) {
						$name = $answer->getRdata()['exchange'];
					}

					break;

				case Types\ResourceRecordType::TYPE_SRV:
					if (is_array($answer->getRdata()) && array_key_exists('target', $answer->getRdata())) {
						$name = $answer->getRdata()['target'];
					}

					break;
			}

			if (!is_string($name)) {
				continue;
			}

			$query = [
				new Clients\Mdns\ResourceRecord(
					$name,
					Types\ResourceRecordType::TYPE_A,
					300,
					'',
					Types\ResourceRecordClass::CLASS_INTERNET,
					true
				),
				new Clients\Mdns\ResourceRecord(
					$name,
					Types\ResourceRecordType::TYPE_AAAA,
					300,
					'',
					Types\ResourceRecordClass::CLASS_INTERNET,
					true
				),
			];

			foreach ($this->getAnswer($query) as $record) {
				$additional[] = $record;
			}
		}

		return $additional;
	}

	/**
	 * @return void
	 */
	private function createZone(): void
	{
		$resourceRecords = [];

		$resourceRecords[] = new Clients\Mdns\ResourceRecord(
			'hap.local',
			Types\ResourceRecordType::TYPE_PTR,
			4500,
			'Bridge AB33CD.hap.local',
			Types\ResourceRecordClass::CLASS_INTERNET
		);

		$resourceRecords[] = new Clients\Mdns\ResourceRecord(
			'Bridge-AB33CD.local',
			Types\ResourceRecordType::TYPE_A,
			120,
			'10.10.10.100'
		);

		$resourceRecords[] = new Clients\Mdns\ResourceRecord(
			'Bridge AB33CD.hap.local',
			Types\ResourceRecordType::TYPE_NSEC,
			4500,
			[
				'nextDomain' => 'Bridge-AB33CD.local',
				'rrtypes'    => ['AAAA'],
			]
		);

		$resourceRecords[] = new Clients\Mdns\ResourceRecord(
			'Bridge AB33CD.hap.local',
			Types\ResourceRecordType::TYPE_SRV,
			120,
			[
				'priority' => 0,
				'weight'   => 0,
				'port'     => 'Bridge-AB33CD.local',
				'target'   => 51234,
			]
		);

		$resourceRecords[] = new Clients\Mdns\ResourceRecord(
			'Bridge AB33CD.hap.local',
			Types\ResourceRecordType::TYPE_TXT,
			4500,
			[
				'md=Bridge',
				'pv=1.1',
				'id=AA:2B:BC:2D:4F:20',
				'c#=2',
				's#=1',
				'ff=0',
				'ci=2',
				'sf=1',
				'sh=zTFuOw==',
			]
		);

		$this->resourceRecords = [];

		foreach ($resourceRecords as $record) {
			$this->resourceRecords[$record->getName()][$record->getType()][$record->getClass()][] = $record;
		}
	}

	/**
	 * @param Clients\Mdns\ResourceRecord[] $queries
	 *
	 * @return Clients\Mdns\ResourceRecord[]
	 */
	private function getAnswer(array $queries): array
	{
		$answers = [];

		foreach ($queries as $query) {
			$answer = $this->resourceRecords[$query->getName()][$query->getType()][$query->getClass()] ?? [];

			if ($answer === []) {
				continue;
			}

			$answers = array_merge($answers, $answer);
		}

		return $answers;
	}

}
