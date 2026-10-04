<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261004154901 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accounts: users, linked connections, seen films, API tokens';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE api_token (id BINARY(16) NOT NULL, name VARCHAR(100) NOT NULL, token_hash VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, user_id BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_7BA2F5EBB3BC57DA (token_hash), INDEX IDX_7BA2F5EBA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE linked_account (id BINARY(16) NOT NULL, provider VARCHAR(32) NOT NULL, provider_user_id VARCHAR(255) NOT NULL, email VARCHAR(180) DEFAULT NULL, email_verified TINYINT NOT NULL, created_at DATETIME NOT NULL, user_id BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_167E6E3392C4739C57367132 (provider, provider_user_id), INDEX IDX_167E6E33A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE seen_film (id BINARY(16) NOT NULL, seen_at DATETIME NOT NULL, user_id BINARY(16) NOT NULL, film_slug VARCHAR(150) NOT NULL, UNIQUE INDEX UNIQ_451F712DA76ED395FD327161 (user_id, film_slug), INDEX IDX_451F712DA76ED395 (user_id), INDEX IDX_451F712DFD327161 (film_slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id BINARY(16) NOT NULL, email VARCHAR(180) DEFAULT NULL, display_name VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE api_token ADD CONSTRAINT FK_7BA2F5EBA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE linked_account ADD CONSTRAINT FK_167E6E33A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE seen_film ADD CONSTRAINT FK_451F712DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE seen_film ADD CONSTRAINT FK_451F712DFD327161 FOREIGN KEY (film_slug) REFERENCES film (slug) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE api_token DROP FOREIGN KEY FK_7BA2F5EBA76ED395');
        $this->addSql('ALTER TABLE linked_account DROP FOREIGN KEY FK_167E6E33A76ED395');
        $this->addSql('ALTER TABLE seen_film DROP FOREIGN KEY FK_451F712DA76ED395');
        $this->addSql('ALTER TABLE seen_film DROP FOREIGN KEY FK_451F712DFD327161');
        $this->addSql('DROP TABLE api_token');
        $this->addSql('DROP TABLE linked_account');
        $this->addSql('DROP TABLE seen_film');
        $this->addSql('DROP TABLE `user`');
    }
}
