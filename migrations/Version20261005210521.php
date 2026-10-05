<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005210521 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seen and unwanted films reference the work';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE seen_film ADD work_id BINARY(16) DEFAULT NULL');
        $this->addSql('UPDATE seen_film m INNER JOIN film f ON f.slug = m.film_slug SET m.work_id = f.work_id');
        // One mark per user and work: the oldest one stays.
        $this->addSql('DELETE a FROM seen_film a INNER JOIN seen_film b ON a.user_id = b.user_id AND a.work_id = b.work_id
            AND (a.seen_at > b.seen_at OR (a.seen_at = b.seen_at AND a.id > b.id))');
        $this->addSql('ALTER TABLE seen_film DROP FOREIGN KEY `FK_451F712DFD327161`');
        $this->addSql('DROP INDEX IDX_451F712DFD327161 ON seen_film');
        $this->addSql('DROP INDEX UNIQ_451F712DA76ED395FD327161 ON seen_film');
        $this->addSql('ALTER TABLE seen_film DROP film_slug, MODIFY work_id BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE seen_film ADD CONSTRAINT FK_451F712DBB3453DB FOREIGN KEY (work_id) REFERENCES work (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_451F712DA76ED395BB3453DB ON seen_film (user_id, work_id)');
        $this->addSql('CREATE INDEX IDX_451F712DBB3453DB ON seen_film (work_id)');
        $this->addSql('ALTER TABLE unwanted_film ADD work_id BINARY(16) DEFAULT NULL');
        $this->addSql('UPDATE unwanted_film m INNER JOIN film f ON f.slug = m.film_slug SET m.work_id = f.work_id');
        // One mark per user and work: the oldest one stays.
        $this->addSql('DELETE a FROM unwanted_film a INNER JOIN unwanted_film b ON a.user_id = b.user_id AND a.work_id = b.work_id
            AND (a.created_at > b.created_at OR (a.created_at = b.created_at AND a.id > b.id))');
        $this->addSql('ALTER TABLE unwanted_film DROP FOREIGN KEY `FK_6FE3CC4BFD327161`');
        $this->addSql('DROP INDEX IDX_6FE3CC4BFD327161 ON unwanted_film');
        $this->addSql('DROP INDEX UNIQ_6FE3CC4BA76ED395FD327161 ON unwanted_film');
        $this->addSql('ALTER TABLE unwanted_film DROP film_slug, MODIFY work_id BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE unwanted_film ADD CONSTRAINT FK_6FE3CC4BBB3453DB FOREIGN KEY (work_id) REFERENCES work (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6FE3CC4BA76ED395BB3453DB ON unwanted_film (user_id, work_id)');
        $this->addSql('CREATE INDEX IDX_6FE3CC4BBB3453DB ON unwanted_film (work_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE seen_film ADD film_slug VARCHAR(150) DEFAULT NULL');
        $this->addSql('UPDATE seen_film m SET m.film_slug = (SELECT MIN(f.slug) FROM film f WHERE f.work_id = m.work_id)');
        $this->addSql('ALTER TABLE seen_film DROP FOREIGN KEY FK_451F712DBB3453DB');
        $this->addSql('DROP INDEX UNIQ_451F712DA76ED395BB3453DB ON seen_film');
        $this->addSql('DROP INDEX IDX_451F712DBB3453DB ON seen_film');
        $this->addSql('ALTER TABLE seen_film DROP work_id, MODIFY film_slug VARCHAR(150) NOT NULL');
        $this->addSql('ALTER TABLE seen_film ADD CONSTRAINT `FK_451F712DFD327161` FOREIGN KEY (film_slug) REFERENCES film (slug) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_451F712DFD327161 ON seen_film (film_slug)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_451F712DA76ED395FD327161 ON seen_film (user_id, film_slug)');
        $this->addSql('ALTER TABLE unwanted_film ADD film_slug VARCHAR(150) DEFAULT NULL');
        $this->addSql('UPDATE unwanted_film m SET m.film_slug = (SELECT MIN(f.slug) FROM film f WHERE f.work_id = m.work_id)');
        $this->addSql('ALTER TABLE unwanted_film DROP FOREIGN KEY FK_6FE3CC4BBB3453DB');
        $this->addSql('DROP INDEX UNIQ_6FE3CC4BA76ED395BB3453DB ON unwanted_film');
        $this->addSql('DROP INDEX IDX_6FE3CC4BBB3453DB ON unwanted_film');
        $this->addSql('ALTER TABLE unwanted_film DROP work_id, MODIFY film_slug VARCHAR(150) NOT NULL');
        $this->addSql('ALTER TABLE unwanted_film ADD CONSTRAINT `FK_6FE3CC4BFD327161` FOREIGN KEY (film_slug) REFERENCES film (slug) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_6FE3CC4BFD327161 ON unwanted_film (film_slug)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6FE3CC4BA76ED395FD327161 ON unwanted_film (user_id, film_slug)');
    }
}
