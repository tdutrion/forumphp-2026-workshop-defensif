<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005115554 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cinemas the users excluded';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE excluded_cinema (id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, user_id BINARY(16) NOT NULL, cinema_slug VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_44CDCEBDA76ED395A9BA7738 (user_id, cinema_slug), INDEX IDX_44CDCEBDA76ED395 (user_id), INDEX IDX_44CDCEBDA9BA7738 (cinema_slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE excluded_cinema ADD CONSTRAINT FK_44CDCEBDA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE excluded_cinema ADD CONSTRAINT FK_44CDCEBDA9BA7738 FOREIGN KEY (cinema_slug) REFERENCES cinema (slug) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE excluded_cinema DROP FOREIGN KEY FK_44CDCEBDA76ED395');
        $this->addSql('ALTER TABLE excluded_cinema DROP FOREIGN KEY FK_44CDCEBDA9BA7738');
        $this->addSql('DROP TABLE excluded_cinema');
    }
}
