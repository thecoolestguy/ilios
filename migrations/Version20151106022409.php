<?php
declare(strict_types=1);

namespace Ilios\Migrations;

use App\Classes\MysqlMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Changes program titles to be not nullable, and program short titles to be nullable.
 *
 * @link https://github.com/ilios/ilios/issues/1083
 */
final class Version20151106022409 extends MysqlMigration
{
    public function up(Schema $schema) : void
    {
        $this->addSql('UPDATE program SET title = short_title WHERE title IS NULL');
        $this->addSql('ALTER TABLE program CHANGE title title VARCHAR(200) NOT NULL, CHANGE short_title short_title VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE program CHANGE title title VARCHAR(200) DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE short_title short_title VARCHAR(10) NOT NULL COLLATE utf8_unicode_ci');
    }
}
