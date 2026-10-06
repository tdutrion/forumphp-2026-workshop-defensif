<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261004215238 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Films the users do not want to see';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE unwanted_film (id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, user_id BINARY(16) NOT NULL, film_slug VARCHAR(150) NOT NULL, UNIQUE INDEX UNIQ_6FE3CC4BA76ED395FD327161 (user_id, film_slug), INDEX IDX_6FE3CC4BA76ED395 (user_id), INDEX IDX_6FE3CC4BFD327161 (film_slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE unwanted_film ADD CONSTRAINT FK_6FE3CC4BA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE unwanted_film ADD CONSTRAINT FK_6FE3CC4BFD327161 FOREIGN KEY (film_slug) REFERENCES film (slug) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE unwanted_film DROP FOREIGN KEY FK_6FE3CC4BA76ED395');
        $this->addSql('ALTER TABLE unwanted_film DROP FOREIGN KEY FK_6FE3CC4BFD327161');
        $this->addSql('DROP TABLE unwanted_film');
    }
}
