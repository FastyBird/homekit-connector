<?php declare(strict_types = 1);

/**
 * Mdns.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Servers
 * @since          0.19.0
 *
 * @date           17.09.22
 */

namespace FastyBird\Connector\HomeKit\Servers;

use Doctrine\DBAL;
use FastyBird\Connector\HomeKit;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Library\Metadata;
use FastyBird\Library\Metadata\Entities as MetadataEntities;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use Nette;
use Nette\Utils;
use Psr\Log;
use Ramsey\Uuid;
use React\Datagram;
use React\Dns;
use React\EventLoop;
use Throwable;
use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function base64_encode;
use function hash;
use function is_array;
use function mt_rand;
use function preg_replace;
use function React\Async\async;
use function str_replace;
use function strval;
use function substr;

/**
 * mDNS connector discovery server
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Servers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Mdns implements Server
{

	use Nette\SmartObject;

	private const DNS_ADDRESS = '224.0.0.251';

	private const DNS_PORT = 5_353;

	private const DNS_BROADCAST_INTERVAL = 60;

	private const HAP_SERVICE_TYPE = '_hap._tcp.local';

	private const VALID_MDNS_REGEX = '/[^A-Za-z0-9\-]+/';

	private const LEADING_TRAILING_SPACE_DASH = '/^[ -]+|[ -]+$/';

	private const DASH_REGEX = '/[-]+/';

	/** @var Array<string, Array<int, Array<int, Array<Dns\Model\Record>>>> */
	private array $resourceRecords = [];

	private Dns\Protocol\Parser $parser;

	private Dns\Protocol\BinaryDumper $dumper;

	private Datagram\SocketInterface|null $server = null;

	private Log\LoggerInterface $logger;

	public function __construct(
		private readonly MetadataEntities\DevicesModule\Connector $connector,
		private readonly Helpers\Connector $connectorHelper,
		private readonly EventLoop\LoopInterface $eventLoop,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();

		$this->parser = new Dns\Protocol\Parser();
		$this->dumper = new Dns\Protocol\BinaryDumper();

		$this->connectorHelper->on(
			'updated',
			function (
				Uuid\UuidInterface $connectorId,
				HomeKit\Types\ConnectorPropertyIdentifier $type,
			): void {
				if (
					$this->connector->getId()->equals($connectorId)
					&& $type->equalsValue(HomeKit\Types\ConnectorPropertyIdentifier::IDENTIFIER_PAIRED)
				) {
					$this->logger->debug(
						'Paired status has been changed',
						[
							'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
							'type' => 'mdns-server',
							'connector' => [
								'id' => $this->connector->getId()->toString(),
							],
						],
					);

					$this->createZone();
					$this->broadcastZone();
				}
			},
		);
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\FileNotFound
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidData
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Logic
	 * @throws MetadataExceptions\MalformedInput
	 */
	public function connect(): void
	{
		$this->createZone();

		$this->logger->debug(
			'Creating mDNS server',
			[
				'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type' => 'mdns-server',
				'connector' => [
					'id' => $this->connector->getId()->toString(),
				],
			],
		);

		$factory = new Datagram\Factory($this->eventLoop);

		$factory->createServer(self::DNS_ADDRESS . ':' . self::DNS_PORT)
			->then(function (Datagram\Socket $server): void {
				$this->server = $server;

				$this->server->on('message', function (string $message): void {
					$request = $this->parser->parseMessage($message);

					$response = clone $request;
					$response->qr = true;
					$response->ra = false;
					$response->aa = $request->questions !== [];

					$response->answers = $this->getAnswers($request->questions);
					$response->additional = $this->getAdditional($response->answers);

					$this->server?->send(
						$this->dumper->toBinary($response),
						self::DNS_ADDRESS . ':' . self::DNS_PORT,
					);
				});

				$this->server->on('error', function (Throwable $ex): void {
					$this->logger->error(
						'An error occurred during server handling',
						[
							'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
							'type' => 'mdns-server',
							'exception' => [
								'message' => $ex->getMessage(),
								'code' => $ex->getCode(),
							],
							'connector' => [
								'id' => $this->connector->getId()->toString(),
							],
						],
					);

					throw new DevicesExceptions\Terminate(
						'Discovery broadcast server was terminated',
						$ex->getCode(),
						$ex,
					);
				});

				$this->server->on('close', function (): void {
					$this->logger->info(
						'Server was closed',
						[
							'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
							'type' => 'mdns-server',
							'connector' => [
								'id' => $this->connector->getId()->toString(),
							],
						],
					);
				});

				$this->eventLoop->futureTick(function (): void {
					$this->broadcastZone();
				});

				$this->eventLoop->addPeriodicTimer(
					self::DNS_BROADCAST_INTERVAL,
					async(function (): void {
						$this->broadcastZone();
					}),
				);
			})
			->otherwise(function (Throwable $ex): void {
				$this->logger->error(
					'Could not create mDNS discovery server',
					[
						'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
						'type' => 'mdns-server',
						'exception' => [
							'message' => $ex->getMessage(),
							'code' => $ex->getCode(),
						],
						'connector' => [
							'id' => $this->connector->getId()->toString(),
						],
					],
				);
			});
	}

	public function disconnect(): void
	{
		$this->logger->debug(
			'Closing mDNS server',
			[
				'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type' => 'mdns-server',
				'connector' => [
					'id' => $this->connector->getId()->toString(),
				],
			],
		);

		$this->server?->close();
	}

	private function broadcastZone(): void
	{
		$this->logger->debug(
			'Broadcasting connector DNS zone',
			[
				'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type' => 'mdns-server',
				'connector' => [
					'id' => $this->connector->getId()->toString(),
				],
			],
		);

		$message = new Dns\Model\Message();
		$message->id = mt_rand(0, 0xffff);
		$message->qr = true;
		$message->rd = true;
		$message->answers = $this->getAnswers([
			new Dns\Query\Query(
				'',
				Dns\Model\Message::TYPE_ANY,
				Dns\Model\Message::CLASS_IN,
			),
		]);

		if ($message->answers === []) {
			return;
		}

		$this->server?->send(
			$this->dumper->toBinary($message),
			self::DNS_ADDRESS . ':' . self::DNS_PORT,
		);
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\FileNotFound
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidData
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Logic
	 * @throws MetadataExceptions\MalformedInput
	 */
	private function createZone(): void
	{
		$name = preg_replace(
			self::LEADING_TRAILING_SPACE_DASH,
			'',
			strval(preg_replace(
				self::VALID_MDNS_REGEX,
				' ',
				$this->connector->getName() ?? $this->connector->getIdentifier(),
			)),
		);

		$hostName = preg_replace(
			self::DASH_REGEX,
			'-',
			Utils\Strings::trim(
				str_replace(
					' ',
					'-',
					Utils\Strings::trim(
						strval(preg_replace(
							self::VALID_MDNS_REGEX,
							' ',
							$this->connector->getName() ?? $this->connector->getIdentifier(),
						)),
					),
				),
				'-',
			),
		);

		$port = $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			HomeKit\Types\ConnectorPropertyIdentifier::get(
				HomeKit\Types\ConnectorPropertyIdentifier::IDENTIFIER_PORT,
			),
		);

		$macAddress = $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			HomeKit\Types\ConnectorPropertyIdentifier::get(
				HomeKit\Types\ConnectorPropertyIdentifier::IDENTIFIER_MAC_ADDRESS,
			),
		);

		$shortMacAddress = str_replace(':', '', Utils\Strings::substring((string) $macAddress, -8));

		$version = $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			HomeKit\Types\ConnectorPropertyIdentifier::get(
				HomeKit\Types\ConnectorPropertyIdentifier::IDENTIFIER_CONFIG_VERSION,
			),
		);

		$paired = $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			HomeKit\Types\ConnectorPropertyIdentifier::get(
				HomeKit\Types\ConnectorPropertyIdentifier::IDENTIFIER_PAIRED,
			),
		);

		$setupId = $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			HomeKit\Types\ConnectorPropertyIdentifier::get(
				HomeKit\Types\ConnectorPropertyIdentifier::IDENTIFIER_SETUP_ID,
			),
		);

		$setupHash = substr(
			base64_encode(hash('sha512', (string) $setupId . (string) $macAddress, true)),
			0,
			4,
		);

		$resourceRecords = [];

		$resourceRecords[] = new Dns\Model\Record(
			self::HAP_SERVICE_TYPE,
			Dns\Model\Message::TYPE_PTR,
			Dns\Model\Message::CLASS_IN,
			4_500,
			$name . ' ' . $shortMacAddress . '.' . self::HAP_SERVICE_TYPE,
		);

		$localIpAddress = Helpers\Protocol::getLocalAddress();

		if ($localIpAddress !== null) {
			$resourceRecords[] = new Dns\Model\Record(
				$hostName . '-' . $shortMacAddress . '.local',
				Dns\Model\Message::TYPE_A,
				Dns\Model\Message::CLASS_IN,
				120,
				$localIpAddress,
			);
		}

		$resourceRecords[] = new Dns\Model\Record(
			$name . ' ' . $shortMacAddress . '.' . self::HAP_SERVICE_TYPE,
			Dns\Model\Message::TYPE_SRV,
			Dns\Model\Message::CLASS_IN,
			120,
			[
				'priority' => '0',
				'weight' => '0',
				'port' => (string) $port,
				'target' => $hostName . '-' . $shortMacAddress . '.local',
			],
		);

		$resourceRecords[] = new Dns\Model\Record(
			$name . ' ' . $shortMacAddress . '.' . self::HAP_SERVICE_TYPE,
			Dns\Model\Message::TYPE_TXT,
			Dns\Model\Message::CLASS_IN,
			4_500,
			[
				'md=' . $name,
				'pv=' . HomeKit\Constants::HAP_PROTOCOL_SHORT_VERSION,
				'id=' . ((string) $macAddress),
				// Represents the 'configuration version' of an Accessory.
				// Increasing this 'version number' signals iOS devices to
				// re-fetch accessories data
				'c#=' . ((int) $version),
				's#=1', // Accessory state
				'ff=0',
				'ci=' . HomeKit\Types\AccessoryCategory::CATEGORY_BRIDGE,
				// 'sf == 1' means "discoverable by HomeKit iOS clients"
				'sf=' . ((bool) $paired === true ? 0 : 1),
				'sh=' . $setupHash,
			],
		);

		$this->resourceRecords = [];

		foreach ($resourceRecords as $record) {
			$this->resourceRecords[$record->name][$record->type][$record->class][] = $record;
		}
	}

	/**
	 * @param Array<Dns\Query\Query> $queries
	 *
	 * @return Array<Dns\Model\Record>
	 */
	private function getAnswers(array $queries): array
	{
		$answers = [];

		foreach ($queries as $query) {
			if ($query->type === Dns\Model\Message::TYPE_ANY) {
				foreach ($this->resourceRecords as $rrByName) {
					foreach ($rrByName as $rrByType) {
						foreach ($rrByType as $rrByClass) {
							$answers = array_merge($answers, $rrByClass);
						}
					}
				}
			} else {
				$answer = $this->resourceRecords[$query->name][$query->type][$query->class] ?? [];

				if ($answer === []) {
					continue;
				}

				$answers = array_merge($answers, $answer);
			}
		}

		return $answers;
	}

	/**
	 * Populate the additional records of a message if required
	 *
	 * @param Array<Dns\Model\Record> $answers
	 *
	 * @return Array<Dns\Model\Record>
	 */
	private function getAdditional(array $answers): array
	{
		$additional = [];

		$queries = [];

		foreach ($answers as $answer) {
			if ($answer->type !== Dns\Model\Message::TYPE_PTR) {
				continue;
			}

			$queries[] = new Dns\Query\Query(
				strval($answer->data),
				Dns\Model\Message::TYPE_SRV,
				Dns\Model\Message::CLASS_IN,
			);

			$queries[] = new Dns\Query\Query(
				strval($answer->data),
				Dns\Model\Message::TYPE_TXT,
				Dns\Model\Message::CLASS_IN,
			);
		}

		// To populate the A and AAAA records, we need to get a set of unique
		// targets from the SRV record
		$srvRecordTargets = array_map(
			static fn (Dns\Model\Record $record): string => is_array($record->data) ? strval(
				$record->data['target'],
			) : '',
			array_filter(
				$additional,
				static fn (Dns\Model\Record $record): bool => $record->type === Dns\Model\Message::TYPE_SRV,
			),
		);
		$srvRecordTargets = array_unique($srvRecordTargets);

		foreach ($srvRecordTargets as $srvRecordTarget) {
			$queries[] = new Dns\Query\Query(
				$srvRecordTarget,
				Dns\Model\Message::TYPE_A,
				Dns\Model\Message::CLASS_IN,
			);

			$queries[] = new Dns\Query\Query(
				$srvRecordTarget,
				Dns\Model\Message::TYPE_AAAA,
				Dns\Model\Message::CLASS_IN,
			);
		}

		if ($queries !== []) {
			foreach ($this->getAnswers($queries) as $record) {
				$additional[] = $record;
			}
		}

		return $additional;
	}

}
