<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260303101213 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE campaign (
              name VARCHAR(255) NOT NULL,
              partner VARCHAR(255) NOT NULL,
              starts_at DATETIME NOT NULL,
              ends_at DATETIME DEFAULT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE consent (
              scope VARCHAR(100) NOT NULL,
              policy_version VARCHAR(50) NOT NULL,
              given_at DATETIME NOT NULL,
              source VARCHAR(100) NOT NULL,
              ip_address VARCHAR(45) DEFAULT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              lead_id INT NOT NULL,
              INDEX IDX_6312081055458D (lead_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE leads (
              email VARCHAR(180) NOT NULL,
              first_name VARCHAR(255) NOT NULL,
              last_name VARCHAR(255) NOT NULL,
              attributes JSON NOT NULL,
              status VARCHAR(20) NOT NULL,
              exported_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL,
              deleted_at DATETIME DEFAULT NULL,
              id INT AUTO_INCREMENT NOT NULL,
              campaign_id INT DEFAULT NULL,
              INDEX IDX_289161CBF639F774 (campaign_id),
              INDEX lead_email_campaign_created_idx (email, campaign_id, created_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              consent
            ADD
              CONSTRAINT FK_6312081055458D FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              leads
            ADD
              CONSTRAINT FK_289161CBF639F774 FOREIGN KEY (campaign_id) REFERENCES campaign (id) ON DELETE
            SET
              NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE consent DROP FOREIGN KEY FK_6312081055458D');
        $this->addSql('ALTER TABLE leads DROP FOREIGN KEY FK_289161CBF639F774');
        $this->addSql('DROP TABLE campaign');
        $this->addSql('DROP TABLE consent');
        $this->addSql('DROP TABLE leads');
    }
}
