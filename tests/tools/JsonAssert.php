<?php declare(strict_types = 1);

namespace FastyBird\Connector\HomeKit\Tests\Tools;

use Closure;
use Nette;
use Nette\StaticClass;
use Nette\Utils;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\Comparator\ComparisonFailure;
use function array_keys;
use function count;
use function is_array;
use function ksort;
use function range;
use function sprintf;
use function strcmp;
use function strval;
use function usort;

class JsonAssert
{

	use StaticClass;

	/**
	 * @throws ExpectationFailedException
	 * @throws Nette\IOException
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
	 * @throws ExpectationFailedException
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

		self::recursiveSort($decodedExpectedJson);
		self::recursiveSort($decodedInput);

		try {
			TestCase::assertEquals($decodedExpectedJson, $decodedInput);

		} catch (ExpectationFailedException) {
			throw new ExpectationFailedException(
				'%1 should be equal to %2',
				new ComparisonFailure(
					$expectedJson,
					$actualJson,
					self::makeJsonPretty($expectedJson),
					self::makeJsonPretty($actualJson),
				),
			);
		}
	}

	/**
	 * @return array<mixed>
	 *
	 * @throws Utils\JsonException
	 */
	public static function jsonDecode(string $input, string $nameForMessage): array
	{
		if ($input === '') {
			return [];
		}

		try {
			return (array) Utils\Json::decode($input, forceArrays: true);
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
		return Utils\Json::encode(Utils\Json::decode($jsonString), pretty: true);
	}

	/**
	 * @throws Utils\JsonException
	 */
	private static function recursiveSort(mixed &$array): void
	{
		if (!is_array($array)) {
			return;
		}

		foreach ($array as &$value) {
			if (is_array($value)) {
				self::recursiveSort($value);
			}
		}

		// Sort by keys for associative arrays
		if (self::isAssoc($array)) {
			ksort($array);

		} else {
			// Sort by values for indexed arrays
			usort($array, function ($a, $b): int {
				if (is_array($a) && is_array($b)) {
					return strcmp(Utils\Json::encode($a), Utils\Json::encode($b));
				}

				if (is_array($a)) {
					return strcmp(Utils\Json::encode($a), strval($b));
				}

				if (is_array($b)) {
					return strcmp(strval($a), Utils\Json::encode($b));
				}

				return strcmp(strval($a), strval($b));
			});
		}
	}

	/**
	 * @param array<mixed> $array
	 *
	 * @return bool
	 */
	private static function isAssoc(array $array): bool
	{
		if ($array === []) return false;

		return array_keys($array) !== range(0, count($array) - 1);
	}

}
