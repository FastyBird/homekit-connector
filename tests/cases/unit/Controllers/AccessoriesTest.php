<?php declare(strict_types = 1);

namespace FastyBird\Connector\HomeKit\Tests\Cases\Unit\Controllers;

use Doctrine\DBAL;
use Error;
use FastyBird\Connector\HomeKit\Documents;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Middleware;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Queries;
use FastyBird\Connector\HomeKit\Servers;
use FastyBird\Connector\HomeKit\Tests;
use FastyBird\Core\Application\EventLoop as ApplicationEventLoop;
use FastyBird\Core\Application\Exceptions as ApplicationExceptions;
use FastyBird\Core\Tools\Exceptions as ToolsExceptions;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use InvalidArgumentException;
use IPub\SlimRouter\Http as SlimRouterHttp;
use Nette;
use Nette\Utils;
use Ramsey\Uuid;
use React\Http\Message\ServerRequest;
use RuntimeException;
use z4kn4fein\SemVer;
use function call_user_func;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class AccessoriesTest extends Tests\Cases\Unit\DbTestCase
{

	private Servers\Http|null $httpServer = null;

	/**
	 * @throws ApplicationExceptions\InvalidArgument
	 * @throws ApplicationExceptions\InvalidState
	 * @throws ApplicationExceptions\MalformedInput
	 * @throws ApplicationExceptions\Mapping
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Nette\DI\MissingServiceException
	 * @throws SemVer\SemverException
	 * @throws RuntimeException
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws Error
	 */
	public function setUp(): void
	{
		parent::setUp();

		$eventLoop = $this->createMock(ApplicationEventLoop\Wrapper::class);

		$this->mockContainerService(ApplicationEventLoop\Wrapper::class, $eventLoop);

		$repository = $this->getContainer()->getByType(DevicesModels\Configuration\Connectors\Repository::class);

		$findConnectorQuery = new Queries\Configuration\FindConnectors();
		$findConnectorQuery->byId(Uuid\Uuid::fromString('f5a8691b-4917-4866-878f-5217193cf14b'));

		$connector = $repository->findOneBy(
			$findConnectorQuery,
			Documents\Connectors\Connector::class,
		);
		self::assertInstanceOf(Documents\Connectors\Connector::class, $connector);

		$accessoryLoader = $this->getContainer()->getByType(Protocol\Loader::class);

		$accessoryLoader->load($connector);

		$httpServerFactory = $this->getContainer()->getByType(Servers\HttpFactory::class);

		$this->httpServer = $httpServerFactory->create($connector);
		$this->httpServer->initialize();
	}

	protected function tearDown(): void
	{
		parent::tearDown();

		$this->httpServer?->disconnect();
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
		Tests\Tools\JsonAssert::assertFixtureMatch(
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
