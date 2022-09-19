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

use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\HomeKitConnector;
use FastyBird\HomeKitConnector\Helpers;
use FastyBird\Metadata;
use FastyBird\Metadata\Entities as MetadataEntities;
use Nette;
use Nette\Utils;
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

	private const HAP_SERVICE_TYPE = '_hap._tcp.local.';

	private const VALID_MDNS_REGEX = '/[^A-Za-z0-9\-]+/';
	private const LEADING_TRAILING_SPACE_DASH = '/^[ -]+|[ -]+$/';
	private const DASH_REGEX = '/[-]+/';

	/** @var Array<string, Array<int, Array<int, Dns\Model\Record[]>>> */
	private array $resourceRecords = [];

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var Helpers\Connector */
	private Helpers\Connector $connectorHelper;

	/** @var Dns\Protocol\Parser */
	private Dns\Protocol\Parser $parser;

	/** @var Dns\Protocol\BinaryDumper */
	private Dns\Protocol\BinaryDumper $dumper;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/** @var Datagram\SocketInterface|null */
	private ?Datagram\SocketInterface $server = null;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Helpers\Connector $connectorHelper
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		Helpers\Connector $connectorHelper,
		EventLoop\LoopInterface $eventLoop,
		?Log\LoggerInterface $logger = null
	) {
		$this->connector = $connector;
		$this->connectorHelper = $connectorHelper;
		$this->eventLoop = $eventLoop;

		$this->logger = $logger ?? new Log\NullLogger();

		$this->parser = new Dns\Protocol\Parser();
		$this->dumper = new Dns\Protocol\BinaryDumper();
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect(): void
	{
		$this->createZone();

		$factory = new Datagram\Factory($this->eventLoop);

		$factory->createServer(self::DNS_ADDRESS . ':' . self::DNS_PORT)
			->then(function (Datagram\Socket $server): void {
				$this->server = $server;

				$this->server->on('message', function (string $message, string $address): void {
					$request = $this->parser->parseMessage($message);

					$response = clone $request;
					$response->qr = true;
					$response->ra = false;
					$response->aa = $request->questions !== [];

					$response->answers = $this->getAnswers($request->questions);
					$response->additional = $this->getAdditional($response->answers);

					$this->server?->send(
						$this->dumper->toBinary($response),
						self::DNS_ADDRESS . ':' . self::DNS_PORT
					);
				});

				$this->server->on('error', function (Throwable $ex): void {
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

					throw new DevicesModuleExceptions\TerminateException(
						'Discovery broadcast server was terminated',
						$ex->getCode(),
						$ex
					);
				});

				$this->server->on('close', function (): void {
					$this->logger->info(
						'Server was closed',
						[
							'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
							'type'   => 'mdns-client',
						]
					);
				});

				$this->eventLoop->addPeriodicTimer(
					3,
					function (): void {
						$message = new Dns\Model\Message();
						$message->id = mt_rand(0, 0xffff);
						$message->qr = true;
						$message->rd = true;
						$message->answers = $this->getAnswers([
							new Dns\Query\Query(
								'',
								Dns\Model\Message::TYPE_ANY,
								Dns\Model\Message::CLASS_IN
							),
						]);

						if ($message->answers === []) {
							return;
						}

						$this->server?->send(
							$this->dumper->toBinary($message),
							self::DNS_ADDRESS . ':' . self::DNS_PORT
						);
					}
				);
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
	 * @return void
	 */
	private function createZone(): void
	{
		$name = preg_replace(
			self::LEADING_TRAILING_SPACE_DASH,
			'',
			strval(preg_replace(
				self::VALID_MDNS_REGEX,
				' ',
				$this->connector->getName() ?? $this->connector->getIdentifier()
			))
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
							$this->connector->getName() ?? $this->connector->getIdentifier()
						))
					)
				),
				'-'
			)
		);

		$port = $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			HomeKitConnector\Types\ConnectorPropertyIdentifier::get(
				HomeKitConnector\Types\ConnectorPropertyIdentifier::IDENTIFIER_PORT
			)
		);

		$macAddress = $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			HomeKitConnector\Types\ConnectorPropertyIdentifier::get(
				HomeKitConnector\Types\ConnectorPropertyIdentifier::IDENTIFIER_MAC_ADDRESS
			)
		);

		$shortMacAddress = str_replace(':', '', Utils\Strings::substring((string) $macAddress, -8));

		$version = $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			HomeKitConnector\Types\ConnectorPropertyIdentifier::get(
				HomeKitConnector\Types\ConnectorPropertyIdentifier::IDENTIFIER_CONFIG_VERSION
			)
		);

		$paired = $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			HomeKitConnector\Types\ConnectorPropertyIdentifier::get(
				HomeKitConnector\Types\ConnectorPropertyIdentifier::IDENTIFIER_PAIRED
			)
		);

		$setupId = $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			HomeKitConnector\Types\ConnectorPropertyIdentifier::get(
				HomeKitConnector\Types\ConnectorPropertyIdentifier::IDENTIFIER_SETUP_ID
			)
		);

		$setupHash = substr(
			base64_encode(hash('sha512', ((string) $setupId . (string) $macAddress), true)),
			0,
			4
		);

		$resourceRecords = [];

		$resourceRecords[] = new Dns\Model\Record(
			self::HAP_SERVICE_TYPE,
			Dns\Model\Message::TYPE_PTR,
			Dns\Model\Message::CLASS_IN,
			4500,
			$name . ' ' . $shortMacAddress . '.' . self::HAP_SERVICE_TYPE,
		);

		$resourceRecords[] = new Dns\Model\Record(
			$hostName . '-' . $shortMacAddress . '.local',
			Dns\Model\Message::TYPE_A,
			Dns\Model\Message::CLASS_IN,
			120,
			gethostbyname(strval(gethostname()))
		);

		$resourceRecords[] = new Dns\Model\Record(
			$name . ' ' . $shortMacAddress . '.' . self::HAP_SERVICE_TYPE,
			Dns\Model\Message::TYPE_SRV,
			Dns\Model\Message::CLASS_IN,
			120,
			[
				'priority' => '0',
				'weight'   => '0',
				'port'     => (string) $port,
				'target'   => $hostName . '-' . $shortMacAddress . '.local',
			]
		);

		$resourceRecords[] = new Dns\Model\Record(
			$name . ' ' . $shortMacAddress . '.' . self::HAP_SERVICE_TYPE,
			Dns\Model\Message::TYPE_TXT,
			Dns\Model\Message::CLASS_IN,
			4500,
			[
				'md=' . $name,
				'pv=' . HomeKitConnector\Constants::HAP_PROTOCOL_SHORT_VERSION,
				'id=' . ((string) $macAddress),
				// Represents the 'configuration version' of an Accessory.
				// Increasing this 'version number' signals iOS devices to
				// re-fetch accessories data
				'c#=' . ((int) $version),
				's#=1', // Accessory state
				'ff=0',
				'ci=' . HomeKitConnector\Types\Category::CATEGORY_BRIDGE,
				// 'sf == 1' means "discoverable by HomeKit iOS clients"
				'sf=' . ((bool) $paired === true ? 0 : 1),
				'sh=' . $setupHash,
			]
		);

		$this->resourceRecords = [];

		foreach ($resourceRecords as $record) {
			$this->resourceRecords[$record->name][$record->type][$record->class][] = $record;
		}
	}

	/**
	 * @param Dns\Query\Query[] $queries
	 *
	 * @return Dns\Model\Record[]
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
	 * @param Dns\Model\Record[] $answers
	 *
	 * @return Dns\Model\Record[]
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
			function (Dns\Model\Record $record): string {
				return is_array($record->data) ? strval($record->data['target']) : '';
			},
			array_filter(
				$additional,
				function (Dns\Model\Record $record): bool {
					return $record->type === Dns\Model\Message::TYPE_SRV;
				}
			)
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
