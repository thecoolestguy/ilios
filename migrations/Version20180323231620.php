<?php declare(strict_types = 1);

namespace Ilios\Migrations;

use App\Classes\MysqlMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Removes <code>permission</code> table.
 */
final class Version20180323231620 extends MysqlMigration
{
    public function up(Schema $schema) : void
    {
        $this->addSql('DROP TABLE permission');
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('CREATE TABLE permission (permission_id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, can_read TINYINT(1) NOT NULL, can_write TINYINT(1) NOT NULL, table_row_id INT DEFAULT NULL, table_name VARCHAR(30) NOT NULL COLLATE utf8_unicode_ci, UNIQUE INDEX user_table_k (user_id, table_name, table_row_id), INDEX IDX_E04992AAA76ED395 (user_id), PRIMARY KEY(permission_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE permission ADD CONSTRAINT FK_E04992AAA76ED395 FOREIGN KEY (user_id) REFERENCES user (user_id)');
    }
}
