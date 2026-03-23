<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260321190709 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client_contact_message (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, email VARCHAR(180) NOT NULL, subject VARCHAR(200) NOT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE services CHANGE created_by_id created_by_id INT NOT NULL, CHANGE price price NUMERIC(10, 2) NOT NULL, CHANGE status status VARCHAR(20) NOT NULL, CHANGE delivery_time delivery_time INT NOT NULL, CHANGE category category VARCHAR(100) NOT NULL, CHANGE tools_used tools_used VARCHAR(255) DEFAULT NULL, CHANGE revision_limit revision_limit VARCHAR(50) DEFAULT NULL, CHANGE is_active is_active TINYINT(1) DEFAULT 1 NOT NULL, CHANGE pricing_unit pricing_unit VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE phone phone VARCHAR(25) DEFAULT NULL, CHANGE is_verified is_verified TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE user RENAME INDEX uniq_identifier_email TO UNIQ_8D93D649E7927C74');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE client_contact_message');
        $this->addSql('ALTER TABLE user CHANGE phone phone VARCHAR(20) DEFAULT NULL, CHANGE is_verified is_verified TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE user RENAME INDEX uniq_8d93d649e7927c74 TO UNIQ_IDENTIFIER_EMAIL');
        $this->addSql('ALTER TABLE services CHANGE created_by_id created_by_id INT DEFAULT NULL, CHANGE price price DOUBLE PRECISION NOT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE pricing_unit pricing_unit VARCHAR(255) DEFAULT NULL, CHANGE delivery_time delivery_time DOUBLE PRECISION NOT NULL, CHANGE category category VARCHAR(255) NOT NULL, CHANGE tools_used tools_used VARCHAR(255) NOT NULL, CHANGE revision_limit revision_limit LONGTEXT NOT NULL, CHANGE is_active is_active TINYINT(1) NOT NULL');
    }
}
