<?php declare(strict_types = 1);

namespace Tests\Cases\Unit\DI;

use FastyBird\HomeKitConnector\Hydrators;
use FastyBird\HomeKitConnector\Schemas;
use Nette;
use Tests\Cases\Unit\BaseTestCase;

final class ServicesTest extends BaseTestCase
{

	/**
	 * @throws Nette\DI\MissingServiceException
	 */
	public function testServicesRegistration(): void
	{
		$container = $this->createContainer();

		self::assertNotNull($container->getByType(Schemas\HomeKitDevice::class, false));
		self::assertNotNull($container->getByType(Schemas\HomeKitConnector::class, false));

		self::assertNotNull($container->getByType(Hydrators\HomeKitDevice::class, false));
		self::assertNotNull($container->getByType(Hydrators\HomeKitConnector::class, false));
	}

}
