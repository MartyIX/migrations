<?php

namespace Nextras\Migrations\Tests;

use Nextras\Migrations\Engine\OrderResolver;
use Nextras\Migrations\Engine\Runner;
use Nextras\Migrations\Entities\Group;
use Nextras\Migrations\Entities\File;
use Nextras\Migrations\Entities\Migration;
use Mockery;
use Tester;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


/**
 * @testCase
 */
class OrderResolverTest extends Tester\TestCase
{

	public function testFirstRun()
	{
		$resolver = new OrderResolver;

		$groupA = $this->createGroup('structures');
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupA);

		// 1s* 2s*
		Assert::same([$fileA, $fileB], $resolver->resolve(
			[],
			[$groupA],
			[$fileB, $fileA],
			Runner::MODE_CONTINUE
		));
	}


	public function testFirstRunTwoGroups()
	{
		$resolver = new OrderResolver;

		$groupA = $this->createGroup('1g');
		$groupB = $this->createGroup('2g');
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupB);
		$fileC = $this->createFile('3s', $groupA);

		// 1s* 2s* 3s*
		Assert::same([$fileA, $fileB, $fileC], $resolver->resolve(
			[],
			[$groupA, $groupB],
			[$fileC, $fileB, $fileA],
			Runner::MODE_CONTINUE
		));
	}


	public function testSecondRunContinue()
	{
		$resolver = new OrderResolver;

		$groupA = $this->createGroup('structures');
		$migrationA = $this->createMigration($groupA->name, '1s');
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupA);

		// 1s 2s*
		Assert::same([$fileB], $resolver->resolve(
			[$migrationA],
			[$groupA],
			[$fileB, $fileA],
			Runner::MODE_CONTINUE
		));
	}


	public function testSecondRunContinueNothingToDo()
	{
		$resolver = new OrderResolver;

		$groupA = $this->createGroup('structures');
		$migrationA = $this->createMigration($groupA->name, '1s');
		$migrationB = $this->createMigration($groupA->name, '2s');
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupA);

		// 1s 2s
		Assert::same([], $resolver->resolve(
			[$migrationA, $migrationB],
			[$groupA],
			[$fileB, $fileA],
			Runner::MODE_CONTINUE
		));
	}


	public function testSecondRunContinueTwoGroups()
	{
		$resolver = new OrderResolver;

		$groupA = $this->createGroup('structures');
		$groupB = $this->createGroup('data');

		$migrationA = $this->createMigration($groupA->name, '1s');
		$migrationB = $this->createMigration($groupB->name, '2d');

		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2d', $groupB);
		$fileC = $this->createFile('3s', $groupA);
		$fileD = $this->createFile('4d', $groupB);

		// 1s 2d 3s* 4d*
		Assert::same([$fileC, $fileD], $resolver->resolve(
			[$migrationB, $migrationA],
			[$groupA, $groupB],
			[$fileB, $fileA, $fileD, $fileC],
			Runner::MODE_CONTINUE
		));
	}


	public function testSecondRunContinueDisabledGroup()
	{
		$resolver = new OrderResolver;

		$groupA = $this->createGroup('structures');
		$groupB = $this->createGroup('data', FALSE);

		$migrationA = $this->createMigration($groupA->name, '1s');
		$migrationB = $this->createMigration($groupB->name, '2d');

		$fileA = $this->createFile('1s', $groupA);
		$fileD = $this->createFile('4s', $groupA);

		// 1s 2d 3d* 4s*
		Assert::same([$fileD], $resolver->resolve(
			[$migrationB, $migrationA],
			[$groupA, $groupB],
			[$fileD, $fileA],
			Runner::MODE_CONTINUE
		));
	}


	public function testSecondRunReset()
	{
		$resolver = new OrderResolver;

		$groupA = $this->createGroup('structures');
		$migrationA = $this->createMigration($groupA->name, '1s');
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupA);

		Assert::same([$fileA, $fileB], $resolver->resolve(
			[$migrationA],
			[$groupA],
			[$fileB, $fileA],
			Runner::MODE_RESET
		));
	}


	public function testNewMigrationInTheMiddleOfExistingOnes()
	{
		$resolver = new OrderResolver;

		$groupA = $this->createGroup('structures');
		$migrationA = $this->createMigration($groupA->name, '1s');
		$migrationC = $this->createMigration($groupA->name, '3s');
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupA);
		$fileC = $this->createFile('3s', $groupA);

		// 1s 2s* 3s
		Assert::same([$fileB], $resolver->resolve(
			[$migrationC, $migrationA],
			[$groupA],
			[$fileA, $fileB, $fileC],
			Runner::MODE_CONTINUE
		));
	}


	public function testRunWithDisabledGroups()
	{
		$groupA = $this->createGroup('structures');
		$groupB = $this->createGroup('data', FALSE, ['structures']);
		$groupC = $this->createGroup('test-data', FALSE, ['data']);

		$method = new \ReflectionMethod('Nextras\Migrations\Engine\OrderResolver', 'validateGroups');
		$method->setAccessible(TRUE);
		$method->invoke(new OrderResolver, [
			'structures' => $groupA,
			'data' => $groupB,
			'test-data' => $groupC,
		]);
	}


	public function testErrorRemovedFile()
	{
		$resolver = new OrderResolver;

		$groupA = $this->createGroup('structures');
		$migrationA = $this->createMigration($groupA->name, '1s');
		$fileB = $this->createFile('2s', $groupA);

		// 1s 2s*
		Assert::exception(function () use ($resolver, $groupA, $migrationA, $fileB) {
			$resolver->resolve(
				[$migrationA],
				[$groupA],
				[$fileB],
				Runner::MODE_CONTINUE
			);
		}, 'Nextras\Migrations\LogicException', 'Previously executed migration "structures/1s" is missing.');
	}


	public function testErrorChangedChecksum()
	{
		$resolver = new OrderResolver;

		$groupA = $this->createGroup('structures');
		$migrationA = $this->createMigration($groupA->name, '1s', '1s.md5.X');
		$fileA = $this->createFile('1s', $groupA, '1s.md5.Y');
		$fileB = $this->createFile('2s', $groupA);

		// 1s 2s*
		Assert::exception(function () use ($resolver, $groupA, $migrationA, $fileA, $fileB) {
			$resolver->resolve(
				[$migrationA],
				[$groupA],
				[$fileB, $fileA],
				Runner::MODE_CONTINUE
			);
		}, 'Nextras\Migrations\LogicException', 'Previously executed migration "structures/1s" has been changed.');
	}


	public function testErrorIncompleteMigration()
	{
		$resolver = new OrderResolver;

		$groupA = $this->createGroup('structures');
		$migrationA = $this->createMigration($groupA->name, '1s', NULL, FALSE);
		$fileA = $this->createFile('1s', $groupA);
		$fileB = $this->createFile('2s', $groupA);

		// 1s 2s*
		Assert::exception(function () use ($resolver, $groupA, $migrationA, $fileA, $fileB) {
			$resolver->resolve(
				[$migrationA],
				[$groupA],
				[$fileB, $fileA],
				Runner::MODE_CONTINUE
			);
		}, 'Nextras\Migrations\LogicException', 'Previously executed migration "structures/1s" did not succeed. Please fix this manually or reset the migrations.');
	}


	public function testErrorMigrationDependingOnUnknownGroup()
	{
		$resolver = new OrderResolver;

		$migrationA = $this->createMigration('foo', '1s');

		Assert::exception(function () use ($resolver, $migrationA) {
			$resolver->resolve(
				[$migrationA],
				[],
				[],
				Runner::MODE_CONTINUE
			);
		}, 'Nextras\Migrations\LogicException', 'Existing migrations depend on unknown group "foo".');
	}


	public function testErrorGroupDependingOnUnknownGroup()
	{
		$resolver = new OrderResolver;

		$groupB = $this->createGroup('data', TRUE, ['structures']);

		Assert::exception(function () use ($resolver, $groupB) {
			$resolver->resolve(
				[],
				[$groupB],
				[],
				Runner::MODE_CONTINUE
			);
		}, 'Nextras\Migrations\LogicException', 'Group "data" depends on unknown group "structures".');
	}


	public function testErrorDisablingRequiredGroup()
	{
		$resolver = new OrderResolver;

		$groupA = $this->createGroup('structures', FALSE);
		$groupB = $this->createGroup('data', TRUE, ['structures']);

		Assert::exception(function () use ($resolver, $groupA, $groupB) {
			$resolver->resolve(
				[],
				[$groupA, $groupB],
				[],
				Runner::MODE_CONTINUE
			);
		}, 'Nextras\Migrations\LogicException', 'Group "data" depends on disabled group "structures". Please enable group "structures" to continue.');
	}


	private function createMigration($groupName, $fileName, $checksum = NULL, $completed = TRUE)
	{
		$migration = new Migration;
		$migration->group = $groupName;
		$migration->filename = $fileName;
		$migration->checksum = $checksum ?: "$fileName.md5";
		$migration->completed = $completed;
		return $migration;
	}


	private function createFile($name, $group, $checksum = NULL)
	{
		$file = new File;
		$file->name = $name;
		$file->group = $group;
		$file->checksum = $checksum ?: "$name.md5";
		return $file;
	}


	private function createGroup($name, $enabled = TRUE, $deps = [])
	{
		$group = new Group;
		$group->name = $name;
		$group->enabled = $enabled;
		$group->dependencies = $deps;
		return $group;
	}

}

$test = new OrderResolverTest();
$test->run();
