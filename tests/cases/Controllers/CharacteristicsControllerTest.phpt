<?php declare(strict_types = 1);

namespace Tests\Cases;

use FastyBird\DevicesModule\DataStorage as DevicesModuleDataStorage;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\HomeKitConnector\Entities;
use FastyBird\HomeKitConnector\Middleware;
use FastyBird\HomeKitConnector\Servers;
use FastyBird\HomeKitConnector\Types;
use Fig\Http\Message\RequestMethodInterface;
use IPub\SlimRouter\Http as SlimRouterHttp;
use Ramsey\Uuid;
use React\Http\Message\ServerRequest;
use Tester\Assert;
use Tests\Tools;
use function call_user_func;
use function is_object;

require_once __DIR__ . '/../../bootstrap.php';
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
	}

	/**
	 * @param string $url
	 * @param int $statusCode
	 * @param string $fixture
	 *
	 * @dataProvider ./../../fixtures/Controllers/characteristicsRead.php
	 */
	public function testRead(string $url, int $statusCode, string $fixture): void
	{
		/** @var DevicesModuleModels\DataStorage\ConnectorsRepository $repository */
		$repository = $this->getContainer()->getByType(DevicesModuleModels\DataStorage\ConnectorsRepository::class);

		$connector = $repository->findById(Uuid\Uuid::fromString('f5a8691b-4917-4866-878f-5217193cf14b'));

		Assert::true(is_object($connector));

		/** @var Entities\Protocol\AccessoryFactory $acccessoryFactory */
		$acccessoryFactory = $this->getContainer()->getByType(Entities\Protocol\AccessoryFactory::class);

		$accessory = $acccessoryFactory->create($connector, null, Types\AccessoryCategory::get(Types\AccessoryCategory::CATEGORY_BRIDGE));

		Assert::true(is_object($accessory));

		/** @var Middleware\RouterMiddleware $middleware */
		$middleware = $this->getContainer()->getByType(Middleware\RouterMiddleware::class);

		$headers = [];

		$request = new ServerRequest(
			RequestMethodInterface::METHOD_GET,
			$url,
			$headers
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
