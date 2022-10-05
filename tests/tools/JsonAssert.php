<?php declare(strict_types = 1);

namespace Tests\Tools;

use Closure;
use Nette\StaticClass;
use Nette\Utils;
use Tester\Assert;
use Tester\AssertException;
use function sprintf;

class JsonAssert
{

	use StaticClass;

	/**
	 * @throws AssertException
	 *
	 * @throws Utils\JsonException
	 */
	public static function assertFixtureMatch(
		string $fixturePath,
		string $actualJson,
		Closure|null $transformFixture = null,
	): void
	{
		$expectation = Utils\FileSystem::read($fixturePath);

		if ($transformFixture !== null) {
			$expectation = $transformFixture($expectation);
		}

		self::assertMatch($expectation, $actualJson);
	}

	/**
	 * @throws AssertException
	 *
	 * @throws Utils\JsonException
	 */
	public static function assertMatch(
		string $expectedJson,
		string $actualJson,
	): void
	{
		$decodedExpectedJson = self::jsonDecode($expectedJson, 'Expected-json');
		$decodedInput = self::jsonDecode($actualJson, 'Actual-json');

		try {
			Assert::equal($decodedExpectedJson, $decodedInput);

		} catch (AssertException) {
			throw new AssertException(
				'%1 should be equal to %2',
				self::makeJsonPretty($expectedJson),
				self::makeJsonPretty($actualJson),
			);
		}
	}

	/**
	 * @return Array<mixed>
	 *
	 * @throws Utils\JsonException
	 */
	public static function jsonDecode(string $input, string $nameForMessage): array
	{
		if ($input === '') {
			return [];
		}

		try {
			return Utils\Json::decode($input, Utils\Json::FORCE_ARRAY);
		} catch (Utils\JsonException $e) {
			throw new Utils\JsonException(
				sprintf('%s is invalid: "%s"', $nameForMessage, $e->getMessage()),
				$e->getCode(),
				$e,
			);
		}
	}

	/**
	 * @throws Utils\JsonException
	 */
	private static function makeJsonPretty(string $jsonString): string
	{
		return Utils\Json::encode(Utils\Json::decode($jsonString), Utils\Json::PRETTY);
	}

}
