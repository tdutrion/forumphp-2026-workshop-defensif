<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261006204350 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'User emails in lower case, compared byte for byte (josé@ is not jose@)';
    }

    public function up(Schema $schema): void
    {
        // Under the former case-insensitive collation, two emails cannot differ by case only: no duplicate.
        $this->addSql('UPDATE `user` SET email = LOWER(email) WHERE email IS NOT NULL');
        $this->addSql('ALTER TABLE `user` CHANGE email email VARCHAR(180) DEFAULT NULL COLLATE `utf8mb4_bin`');
    }

    public function down(Schema $schema): void
    {
        // Fails if two emails now only differ by an accent.
        $this->addSql('ALTER TABLE `user` CHANGE email email VARCHAR(180) DEFAULT NULL');
    }
}
