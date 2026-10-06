<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005101602 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Language of the cinemas (from their chain) and original language of the films';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cinema ADD language VARCHAR(2) NOT NULL');
        // Every cinema synchronized so far belongs to Pathé (French).
        $this->addSql("UPDATE cinema SET language = 'fr' WHERE chain = 'pathe'");
        $this->addSql('ALTER TABLE film ADD original_language VARCHAR(2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cinema DROP language');
        $this->addSql('ALTER TABLE film DROP original_language');
    }
}
