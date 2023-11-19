<?php declare(strict_types = 1);

namespace FastyBird\Connector\HomeKit\Tests\Cases\Unit\Controllers;

use Error;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Middleware;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Servers;
use FastyBird\Connector\HomeKit\Tests\Cases\Unit\DbTestCase;
use FastyBird\Connector\HomeKit\Tests\Tools;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Bootstrap\Exceptions as BootstrapExceptions;
use FastyBird\Library\Metadata\Documents as MetadataDocuments;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use InvalidArgumentException;
use IPub\SlimRouter\Http as SlimRouterHttp;
use Nette;
use Nette\Utils;
use Ramsey\Uuid;
use React\Http\Message\ServerRequest;
use RuntimeException;
use function assert;
use function call_user_func;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class AccessoriesTest extends DbTestCase
{

	/**
	 * @throws BootstrapExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\MalformedInput
	 * @throws Nette\DI\MissingServiceException
	 * @throws RuntimeException
	 * @throws Error
	 */
	public function setUp(): void
	{
		parent::setUp();

		$repository = $this->getContainer()->getByType(DevicesModels\Configuration\Connectors\Repository::class);

		$findConnectorQuery = new DevicesQueries\Configuration\FindConnectors();
		$findConnectorQuery->byId(Uuid\Uuid::fromString('f5a8691b-4917-4866-878f-5217193cf14b'));

		$connector = $repository->findOneBy($findConnectorQuery);
		assert($connector instanceof MetadataDocuments\DevicesModule\Connector);

		$accessoryFactory = $this->getContainer()->getByType(Entities\Protocol\AccessoryFactory::class);

		$accessory = $accessoryFactory->create(
			$connector,
			null,
			Types\AccessoryCategory::get(Types\AccessoryCategory::BRIDGE),
		);

		$accessoryDriver = $this->getContainer()->getByType(Protocol\Driver::class);

		assert($accessory instanceof Entities\Protocol\Bridge);

		$accessoryDriver->addBridge($accessory);
	}

	/**
	 * @throws InvalidArgumentException
	 * @throws Nette\DI\MissingServiceException
	 * @throws RuntimeException
	 * @throws Error
	 * @throws Utils\JsonException
	 *
	 * @dataProvider accessoriesRead
	 */
	public function testRead(string $url, int $statusCode, string $fixture): void
	{
		$middleware = $this->getContainer()->getByType(Middleware\Router::class);

		$headers = [];

		$request = new ServerRequest(
			RequestMethodInterface::METHOD_GET,
			$url,
			$headers,
			'',
			'1.1',
			[
				'REMOTE_ADDR' => '127.0.0.1',
			],
		);

		$request = $request->withAttribute(
			Servers\Http::REQUEST_ATTRIBUTE_CONNECTOR,
			'f5a8691b-4917-4866-878f-5217193cf14b',
		);

		$response = call_user_func($middleware, $request);

		self::assertTrue($response instanceof SlimRouterHttp\Response);
		self::assertSame($statusCode, $response->getStatusCode());
		Tools\JsonAssert::assertFixtureMatch(
			$fixture,
			(string) $response->getBody(),
		);
	}

	/**
	 * @return array<string, array<int|string>>
	 */
	public static function accessoriesRead(): array
	{
		return [
			// Valid responses
			//////////////////
			'readAll' => [
				'/accessories',
				StatusCodeInterface::STATUS_OK,
				__DIR__ . '/../../../fixtures/Controllers/responses/accessories.index.json',
			],
		];
	}

}
