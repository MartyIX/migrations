<?php

/**
 * This file is part of the Nextras community extensions of Nette Framework
 *
 * @license    New BSD License
 * @link       https://github.com/nextras/migrations
 */

namespace Nextras\Migrations\Extensions;

use Nette;
use Nextras\Migrations\Dbal\NetteAdapter;
use Nextras\Migrations\Drivers\MySqlDriver;
use Nextras\Migrations\Drivers\PgSqlDriver;


/**
 * @deprecated
 */
class NetteDbSql extends SqlHandler
{
	public function __construct(Nette\Database\Context $context)
	{
		$connection = $context->getConnection();
		$driver = $connection->getSupplementalDriver();
		$dbal = new NetteAdapter($connection);

		if ($driver instanceof Nette\Database\Drivers\PgSqlDriver) {
			parent::__construct(new PgSqlDriver($dbal, 'migrations'));

		} elseif ($driver instanceof Nette\Database\Drivers\MySqlDriver) {
			parent::__construct(new MySqlDriver($dbal, 'migrations'));

		} else {
			throw new \LogicException();
		}
	}
}
