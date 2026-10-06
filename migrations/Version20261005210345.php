<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005210345 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Works: every film gets one';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE work (id BINARY(16) NOT NULL, original_title VARCHAR(255) NOT NULL, year INT DEFAULT NULL, directors JSON DEFAULT NULL, fingerprint VARCHAR(255) DEFAULT NULL, wikidata_id VARCHAR(20) DEFAULT NULL, imdb_id VARCHAR(20) DEFAULT NULL, tmdb_id VARCHAR(20) DEFAULT NULL, link_status VARCHAR(16) NOT NULL, link_attempted_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_534E68802A67038D (wikidata_id), INDEX IDX_534E6880FC0B754A (fingerprint), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE film ADD work_id BINARY(16) DEFAULT NULL');
        // One work per existing film, described from the film (directors unknown: read at the next sync).
        $this->addSql('UPDATE film SET work_id = UUID_TO_BIN(UUID(), 1)');
        $this->addSql("INSERT INTO work (id, original_title, year, directors, fingerprint, wikidata_id, imdb_id, tmdb_id, link_status, link_attempted_at, created_at)
            SELECT work_id, title, YEAR(release_date), NULL, NULL, NULL, NULL, NULL, 'unlinked', NULL, UTC_TIMESTAMP() FROM film");
        $this->addSql('ALTER TABLE film MODIFY work_id BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE film ADD CONSTRAINT FK_8244BE22BB3453DB FOREIGN KEY (work_id) REFERENCES work (id)');
        $this->addSql('CREATE INDEX IDX_8244BE22BB3453DB ON film (work_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE film DROP FOREIGN KEY FK_8244BE22BB3453DB');
        $this->addSql('DROP INDEX IDX_8244BE22BB3453DB ON film');
        $this->addSql('ALTER TABLE film DROP work_id');
        $this->addSql('DROP TABLE work');
    }
}
