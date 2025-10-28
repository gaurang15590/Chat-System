<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241028000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users and messages tables for chat system';
    }

    public function up(Schema $schema): void
    {
        // Create users table
        $this->addSql('CREATE TABLE users (
            id INT AUTO_INCREMENT NOT NULL,
            username VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            last_seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            is_online TINYINT(1) NOT NULL,
            UNIQUE INDEX UNIQ_1483A5E9F85E0677 (username),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create messages table
        $this->addSql('CREATE TABLE messages (
            id INT AUTO_INCREMENT NOT NULL,
            sender_id INT NOT NULL,
            content LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            room_id VARCHAR(50) NOT NULL,
            message_type VARCHAR(20) NOT NULL,
            metadata JSON DEFAULT NULL,
            INDEX IDX_DB021E96F624B39D (sender_id),
            INDEX IDX_DB021E9654177093 (room_id),
            INDEX IDX_DB021E96B03A8386 (created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add foreign key constraint
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E96F624B39D FOREIGN KEY (sender_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_DB021E96F624B39D');
        $this->addSql('DROP TABLE messages');
        $this->addSql('DROP TABLE users');
    }
}