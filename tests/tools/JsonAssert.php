<?php declare(strict_types = 1);

namespace Tests\Tools;

use Closure;
use Nette\StaticClass;
use Nette\Utils;
use Tester\Assert;
use Tester\AssertException;

class JsonAssert
{

	use StaticClass;

	/**
	 * @param string $fixturePath
	 * @param string $actualJson
	 * @param Closure|null $transformFixture
	 *
	 * @throws AssertException
	 *
	 * @throws Utils\JsonException
	 */
	public static function assertFixtureMatch(
		string $fixturePath,
		string $actualJson,
		?Closure $transformFixture = null
	): void {
		$expectation = Utils\FileSystem::read($fixturePath);

		if ($transformFixture !== null) {
			$expectation = $transformFixture($expectation);
		}

		self::assertMatch($expectation, $actualJson);
	}

	/**
	 * @param string $expectedJson
	 * @param string $actualJson
	 *
	 * @throws AssertException
	 *
	 * @throws Utils\JsonException
	 */
	public static function assertMatch(
		string $expectedJson,
		string $actualJson
	): void {
		$decodedExpectedJson = self::jsonDecode($expectedJson, 'Expected-json');
		$decodedInput = self::jsonDecode($actualJson, 'Actual-json');

		try {
			Assert::equal($decodedExpectedJson, $decodedInput);

		} catch (AssertException $e) {
			throw new AssertException(
				'%1 should be equal to %2',
				self::makeJsonPretty($expectedJson),
				self::makeJsonPretty($actualJson)
			);
		}
	}

	/**
	 * @param string $input
	 * @param string $nameForMessage
	 *
	 * @return mixed[]
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
			throw new Utils\JsonException(sprintf('%s is invalid: "%s"', $nameForMessage, $e->getMessage()), $e->getCode(), $e);
		}
	}

	/**
	 * @param string $jsonString
	 *
	 * @return string
	 *
	 * @throws Utils\JsonException
	 */
	private static function makeJsonPretty(string $jsonString): string
	{
		return Utils\Json::encode(Utils\Json::decode($jsonString), Utils\Json::PRETTY);
	}

}
