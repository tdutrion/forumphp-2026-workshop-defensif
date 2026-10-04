<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261004154211 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catalog: cities, cinemas, films, showtimes';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cinema (slug VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, chain VARCHAR(32) NOT NULL, country VARCHAR(2) NOT NULL, timezone VARCHAR(64) NOT NULL, address VARCHAR(255) DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, town VARCHAR(100) DEFAULT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, hall_count INT DEFAULT NULL, open TINYINT NOT NULL, city_slug VARCHAR(100) NOT NULL, INDEX IDX_D48304B47BD3A60A (city_slug), PRIMARY KEY (slug)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE city (slug VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, chain VARCHAR(32) NOT NULL, country VARCHAR(2) NOT NULL, PRIMARY KEY (slug)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE film (slug VARCHAR(150) NOT NULL, title VARCHAR(255) NOT NULL, chain VARCHAR(32) NOT NULL, duration INT DEFAULT NULL, release_date DATE DEFAULT NULL, genres JSON NOT NULL, poster_url VARCHAR(500) DEFAULT NULL, content_rating VARCHAR(100) DEFAULT NULL, PRIMARY KEY (slug)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE showtime (id VARCHAR(32) NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, local_date DATE NOT NULL, version VARCHAR(8) NOT NULL, status VARCHAR(20) NOT NULL, booking_url VARCHAR(255) NOT NULL, reservable_until DATETIME DEFAULT NULL, auditorium VARCHAR(50) DEFAULT NULL, capacity VARCHAR(10) DEFAULT NULL, film_slug VARCHAR(150) NOT NULL, cinema_slug VARCHAR(100) NOT NULL, INDEX IDX_3248D9155A0507C (starts_at), INDEX IDX_3248D9196815DA9 (local_date), INDEX IDX_3248D91FD327161 (film_slug), INDEX IDX_3248D91A9BA7738 (cinema_slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE cinema ADD CONSTRAINT FK_D48304B47BD3A60A FOREIGN KEY (city_slug) REFERENCES city (slug)');
        $this->addSql('ALTER TABLE showtime ADD CONSTRAINT FK_3248D91FD327161 FOREIGN KEY (film_slug) REFERENCES film (slug) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE showtime ADD CONSTRAINT FK_3248D91A9BA7738 FOREIGN KEY (cinema_slug) REFERENCES cinema (slug) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cinema DROP FOREIGN KEY FK_D48304B47BD3A60A');
        $this->addSql('ALTER TABLE showtime DROP FOREIGN KEY FK_3248D91FD327161');
        $this->addSql('ALTER TABLE showtime DROP FOREIGN KEY FK_3248D91A9BA7738');
        $this->addSql('DROP TABLE cinema');
        $this->addSql('DROP TABLE city');
        $this->addSql('DROP TABLE film');
        $this->addSql('DROP TABLE showtime');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
