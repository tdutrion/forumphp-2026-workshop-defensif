<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261007113619 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The capacity of a showtime is a number of seats, not the string Pathé sends';
    }

    public function up(Schema $schema): void
    {
        // What is not a number of seats becomes an unknown capacity, as Showtime::$capacity reads it.
        $this->addSql("UPDATE showtime SET capacity = NULL WHERE capacity IS NOT NULL AND capacity NOT REGEXP '^[0-9]+$'");
        $this->addSql('ALTER TABLE showtime CHANGE capacity capacity INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE showtime CHANGE capacity capacity VARCHAR(10) DEFAULT NULL');
    }
}
