<?php declare(strict_types = 1);

namespace Tests\Cases\Unit;

use FastyBird\DevicesModule\DataStorage as DevicesModuleDataStorage;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\HomeKitConnector\Entities;
use FastyBird\HomeKitConnector\Middleware;
use FastyBird\HomeKitConnector\Protocol;
use FastyBird\HomeKitConnector\Servers;
use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata\Entities as MetadataEntities;
use Fig\Http\Message\RequestMethodInterface;
use IPub\SlimRouter\Http as SlimRouterHttp;
use Ramsey\Uuid;
use React\Http\Message\ServerRequest;
use Tester\Assert;
use Tests\Tools;
use function call_user_func;

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../DbTestCase.php';

/**
 * @testCase
 */
final class CharacteristicsControllerTest extends DbTestCase
{

	public function setUp(): void
	{
		parent::setUp();

		$writer = $this->getContainer()->getByType(DevicesModuleDataStorage\Writer::class);
		$reader = $this->getContainer()->getByType(DevicesModuleDataStorage\Reader::class);

		$writer->write();
		$reader->read();

		/** @var DevicesModuleModels\DataStorage\ConnectorsRepository $repository */
		$repository = $this->getContainer()->getByType(DevicesModuleModels\DataStorage\ConnectorsRepository::class);

		/** @var MetadataEntities\DevicesModule\Connector $connector */
		$connector = $repository->findById(Uuid\Uuid::fromString('f5a8691b-4917-4866-878f-5217193cf14b'));

		/** @var Entities\Protocol\AccessoryFactory $acccessoryFactory */
		$accessoryFactory = $this->getContainer()->getByType(Entities\Protocol\AccessoryFactory::class);

		$accessory = $accessoryFactory->create($connector, null, Types\AccessoryCategory::get(Types\AccessoryCategory::CATEGORY_BRIDGE));

		/** @var Protocol\Driver $accessoryDriver */
		$accessoryDriver = $this->getContainer()->getByType(Protocol\Driver::class);

		$accessoryDriver->addBridge($accessory);
	}

	/**
	 * @param string $url
	 * @param int $statusCode
	 * @param string $fixture
	 *
	 * @dataProvider ./../../../fixtures/Controllers/characteristicsRead.php
	 */
	public function testRead(string $url, int $statusCode, string $fixture): void
	{
		/** @var Middleware\Router $middleware */
		$middleware = $this->getContainer()->getByType(Middleware\Router::class);

		$headers = [];

		$request = new ServerRequest(
			RequestMethodInterface::METHOD_GET,
			$url,
			$headers,
			'',
			'1.1',
			[
				'REMOTE_ADDR' => '127.0.0.1'
			],
		);

		$request = $request->withAttribute(Servers\Http::REQUEST_ATTRIBUTE_CONNECTOR, 'f5a8691b-4917-4866-878f-5217193cf14b');

		$response = call_user_func($middleware, $request);

		Tools\JsonAssert::assertFixtureMatch(
			$fixture,
			(string) $response->getBody()
		);
		Assert::same($statusCode, $response->getStatusCode());
		Assert::type(SlimRouterHttp\Response::class, $response);
	}

}

$test_case = new CharacteristicsControllerTest();
$test_case->run();
