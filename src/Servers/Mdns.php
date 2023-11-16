<?php declare(strict_types = 1);

/**
 * Mdns.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Servers
 * @since          1.0.0
 *
 * @date           17.09.22
 */

namespace FastyBird\Connector\HomeKit\Servers;

use FastyBird\Connector\HomeKit;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Bootstrap\Helpers as BootstrapHelpers;
use FastyBird\Library\Metadata\Documents as MetadataDocuments;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Constants as DevicesConstants;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use Nette;
use Nette\Utils;
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
use function implode;
use function is_array;
use function mt_rand;
use function preg_match;
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

	private const IP_ADDRESS_REGEX = '/^(\d[\d.]+):(\d+)\b/';

	private const LEADING_TRAILING_SPACE_DASH = '/^[ -]+|[ -]+$/';

	private const DASH_REGEX = '/[-]+/';

	/** @var array<string, array<int, array<int, array<Dns\Model\Record>>>> */
	private array $resourceRecords = [];

	private string|null $localIpAddress = null;

	private Dns\Protocol\Parser $parser;

	private Dns\Protocol\BinaryDumper $dumper;

	private Datagram\SocketInterface|null $socket = null;

	public function __construct(
		private readonly HomeKit\Entities\HomeKitConnector $connector,
		private readonly EventLoop\LoopInterface $eventLoop,
		private readonly HomeKit\Logger $logger,
		private readonly DevicesModels\Entities\Connectors\Properties\PropertiesManager $connectorsPropertiesManager,
	)
	{
		$this->parser = new Dns\Protocol\Parser();
		$this->dumper = new Dns\Protocol\BinaryDumper();
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function connect(): void
	{
		$this->createZone();

		$this->logger->debug(
			'Creating mDNS server',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'mdns-server',
				'connector' => [
					'id' => $this->connector->getId()->toString(),
				],
			],
		);

		$factory = new Datagram\Factory($this->eventLoop);

		$factory->createServer(self::DNS_ADDRESS . ':' . self::DNS_PORT)
			->then(function (Datagram\Socket $socket): void {
				$this->socket = $socket;

				$this->socket->on('message', function (string $message, string $remoteAddress): void {
					if (
						preg_match(self::IP_ADDRESS_REGEX, $remoteAddress, $matches) === false
						|| $matches[1] === $this->localIpAddress
					) {
						return;
					}

					$request = $this->parser->parseMessage($message);

					$response = clone $request;
					$response->qr = true;
					$response->ra = false;
					$response->aa = $request->questions !== [];

					$response->answers = $this->getAnswers($request->questions);
					$response->additional = $this->getAdditional($response->answers);

					if ($response->answers !== []) {
						$this->socket?->send(
							$this->dumper->toBinary($response),
							self::DNS_ADDRESS . ':' . self::DNS_PORT,
						);
					}
				});

				$this->socket->on('error', function (Throwable $ex): void {
					$this->logger->error(
						'An error occurred during server handling',
						[
							'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
							'type' => 'mdns-server',
							'exception' => BootstrapHelpers\Logger::buildException($ex),
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

				$this->socket->on('close', function (): void {
					$this->logger->info(
						'Server was closed',
						[
							'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
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
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
						'type' => 'mdns-server',
						'exception' => BootstrapHelpers\Logger::buildException($ex),
						'connector' => [
							'id' => $this->connector->getId()->toString(),
						],
					],
				);
			});

		$this->connectorsPropertiesManager->on(
			DevicesConstants::EVENT_ENTITY_CREATED,
			[$this, 'refresh'],
		);

		$this->connectorsPropertiesManager->on(
			DevicesConstants::EVENT_ENTITY_UPDATED,
			[$this, 'refresh'],
		);

		$this->connectorsPropertiesManager->on(
			DevicesConstants::EVENT_ENTITY_DELETED,
			[$this, 'refresh'],
		);
	}

	public function disconnect(): void
	{
		$this->logger->debug(
			'Closing mDNS server',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'mdns-server',
				'connector' => [
					'id' => $this->connector->getId()->toString(),
				],
			],
		);

		$this->connectorsPropertiesManager->removeListener(
			DevicesConstants::EVENT_ENTITY_CREATED,
			[$this, 'refresh'],
		);

		$this->connectorsPropertiesManager->removeListener(
			DevicesConstants::EVENT_ENTITY_UPDATED,
			[$this, 'refresh'],
		);

		$this->connectorsPropertiesManager->removeListener(
			DevicesConstants::EVENT_ENTITY_DELETED,
			[$this, 'refresh'],
		);

		$this->socket?->close();

		$this->socket = null;
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function refresh(
		DevicesEntities\Connectors\Properties\Property|MetadataDocuments\DevicesModule\ConnectorVariableProperty $property,
	): void
	{
		if (
			(
				(
					$property instanceof DevicesEntities\Connectors\Properties\Variable
					&& $property->getConnector()->getId()->equals($this->connector->getId())
				) || (
					$property instanceof MetadataDocuments\DevicesModule\ConnectorVariableProperty
					&& $property->getConnector()->equals($this->connector->getId())
				)
			)
			&& (
				$property->getIdentifier() === Types\ConnectorPropertyIdentifier::PAIRED
				|| $property->getIdentifier() === Types\ConnectorPropertyIdentifier::CONFIG_VERSION
			)
		) {
			$this->logger->debug(
				'Connector configuration changed. Refreshing mDNS broadcast',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'mdns-server',
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
				],
			);

			$this->createZone();
			$this->broadcastZone();
		}
	}

	private function broadcastZone(): void
	{
		$this->logger->debug(
			'Broadcasting connector DNS zone',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
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

		$this->socket?->send(
			$this->dumper->toBinary($message),
			self::DNS_ADDRESS . ':' . self::DNS_PORT,
		);
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
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

		$macAddress = $this->connector->getMacAddress();

		$shortMacAddress = str_replace(':', '', Utils\Strings::substring($macAddress, -8));

		$setupHash = base64_encode(
			substr(
				hash('sha512', $this->connector->getSetupId() . $macAddress, true),
				0,
				4,
			),
		);

		$resourceRecords = [];

		$resourceRecords[] = new Dns\Model\Record(
			self::HAP_SERVICE_TYPE,
			Dns\Model\Message::TYPE_PTR,
			Dns\Model\Message::CLASS_IN,
			4_500,
			$name . ' ' . $shortMacAddress . '.' . self::HAP_SERVICE_TYPE,
		);

		$this->localIpAddress = Helpers\Protocol::getLocalAddress();

		if ($this->localIpAddress !== null) {
			$resourceRecords[] = new Dns\Model\Record(
				$hostName . '-' . $shortMacAddress . '.local',
				Dns\Model\Message::TYPE_A,
				Dns\Model\Message::CLASS_IN,
				120,
				$this->localIpAddress,
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
				'port' => (string) $this->connector->getPort(),
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
				'id=' . $macAddress,
				// Represents the 'configuration version' of an Accessory.
				// Increasing this 'version number' signals iOS devices to
				// re-fetch accessories data
				'c#=' . $this->connector->getVersion(),
				's#=1', // Accessory state
				'ff=0',
				'ci=' . HomeKit\Types\AccessoryCategory::BRIDGE,
				// 'sf == 1' means "discoverable by HomeKit iOS clients"
				'sf=' . ($this->connector->isPaired() ? 0 : 1),
				'sh=' . $setupHash,
			],
		);

		$this->resourceRecords = [];

		foreach ($resourceRecords as $record) {
			$this->resourceRecords[$record->name][$record->type][$record->class][] = $record;
		}
	}

	/**
	 * @param array<Dns\Query\Query> $queries
	 *
	 * @return array<Dns\Model\Record>
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
	 * @param array<Dns\Model\Record> $answers
	 *
	 * @return array<Dns\Model\Record>
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
				is_array($answer->data) ? implode($answer->data) : $answer->data,
				Dns\Model\Message::TYPE_SRV,
				Dns\Model\Message::CLASS_IN,
			);

			$queries[] = new Dns\Query\Query(
				is_array($answer->data) ? implode($answer->data) : $answer->data,
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
