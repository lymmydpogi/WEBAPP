<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251010135216 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` CHANGE client_id client_id INT DEFAULT NULL, CHANGE status status VARCHAR(50) NOT NULL, CHANGE total_price total_price DOUBLE PRECISION DEFAULT NULL, CHANGE notes notes LONGTEXT DEFAULT NULL, CHANGE delivery_date delivery_date DATETIME DEFAULT NULL, CHANGE clien_email client_email VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` CHANGE client_id client_id INT NOT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE total_price total_price DOUBLE PRECISION NOT NULL, CHANGE notes notes LONGTEXT NOT NULL, CHANGE delivery_date delivery_date DATETIME NOT NULL, CHANGE client_email clien_email VARCHAR(255) NOT NULL');
    }
}
